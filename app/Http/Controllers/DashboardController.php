<?php

namespace App\Http\Controllers;

use App\Models\HealthCheck;
use App\Models\Incident;
use App\Models\Service;
use App\Services\DockerEngine;
use App\Services\HealthProbe;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Throwable;

class DashboardController extends Controller
{
    public function index(): View
    {
        $data = $this->dashboardData();
        $data['editingService'] = old('_method') === 'PUT'
            ? $data['services']->firstWhere('id', (int) old('service_id'))
            : null;

        return view('dashboard', $data);
    }

    public function storeService(Request $request): RedirectResponse
    {
        $validated = $this->validatedServiceInput($request);

        $baseSlug = Str::slug($validated['name']) ?: 'service';
        $slug = $baseSlug;
        $suffix = 2;

        while (Service::query()->where('slug', $slug)->exists()) {
            $slug = $baseSlug.'-'.$suffix++;
        }

        $service = Service::query()->create([
            ...$validated,
            'slug' => $slug,
            'enabled' => true,
            'status' => 'unknown',
        ]);

        return redirect()->route('dashboard')->with('status', "{$service->name} is now monitored.");
    }

    public function updateService(Request $request, Service $service): RedirectResponse
    {
        $service->update([
            ...$this->validatedServiceInput($request),
            'status' => 'unknown',
            'consecutive_failures' => 0,
            'last_checked_at' => null,
        ]);

        return redirect()->route('dashboard')->with('status', "{$service->name} configuration updated.");
    }

    public function destroyService(Service $service): RedirectResponse
    {
        $serviceName = $service->name;
        $service->delete();

        return redirect()->route('dashboard')->with('status', "{$serviceName} is no longer monitored.");
    }

    /**
     * @return array{
     *     name: string,
     *     container_name: string,
     *     check_type: 'http'|'tcp',
     *     check_target: string,
     *     cooldown_seconds: int,
     *     description?: string|null,
     *     documentation_url?: string|null
     * }
     */
    private function validatedServiceInput(Request $request): array
    {
        $targetRule = match ($request->input('check_type')) {
            'http' => 'url:http,https',
            'tcp' => 'regex:/^[A-Za-z0-9](?:[A-Za-z0-9.-]*[A-Za-z0-9])?:(?:[1-9]\d{0,3}|[1-5]\d{4}|6[0-4]\d{3}|65[0-4]\d{2}|655[0-2]\d|6553[0-5])$/',
            default => null,
        };

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'container_name' => ['required', 'string', 'max:255', 'regex:/^[A-Za-z0-9][A-Za-z0-9_.-]*$/'],
            'check_type' => ['required', 'in:http,tcp'],
            'check_target' => array_values(array_filter(['required', 'string', 'max:255', $targetRule])),
            'cooldown_seconds' => ['required', 'integer', 'between:30,3600'],
            'description' => ['nullable', 'string', 'max:500'],
            'documentation_url' => ['nullable', 'url:http,https', 'max:2048'],
        ], [
            'check_target.url' => 'Enter a valid HTTP or HTTPS health-check URL.',
            'check_target.regex' => 'Enter a hostname and port from 1 to 65535, for example cache:6379.',
            'container_name.regex' => 'Use the Docker container name without spaces or a leading slash.',
        ]);

        $validated['cooldown_seconds'] = (int) $validated['cooldown_seconds'];

        return $validated;
    }

    public function snapshot(): JsonResponse
    {
        $data = $this->dashboardData();

        return response()->json([
            'services' => $data['services']->map(fn (Service $service) => [
                'id' => $service->id,
                'status' => $service->status,
                'last_checked_at' => $service->last_checked_at?->diffForHumans(),
            ]),
            'healthy' => $data['healthy'],
            'degraded' => $data['degraded'],
            'down' => $data['down'],
            'open_incidents' => $data['openIncidents'],
            'overall_status' => $data['overallStatus'],
            'activity_count' => $data['events']->count(),
            'activity_html' => view('dashboard.partials.activity-feed', ['events' => $data['events']])->render(),
            'incidents_html' => view('dashboard.partials.incident-list', [
                'incidents' => $data['incidents'],
                'openIncidents' => $data['openIncidents'],
            ])->render(),
            'checked_at' => now()->toIso8601String(),
        ]);
    }

    public function chaos(Service $service, DockerEngine $docker): RedirectResponse
    {
        abort_unless(config('services.sentinel.chaos_enabled'), 403);

        try {
            $docker->stop($service->container_name);

            return redirect()->route('dashboard')->with('status', "{$service->name} stopped for a chaos test.");
        } catch (Throwable $exception) {
            report($exception);

            return redirect()->route('dashboard')->withErrors([
                'chaos' => 'Sentinel could not stop the container. Check Docker Engine access.',
            ]);
        }
    }

    public function incident(Incident $incident): View
    {
        $incident->load(['service', 'report']);

        return view('incidents.show', compact('incident'));
    }

    public function serviceReport(Service $service): View
    {
        $service->load([
            'healthChecks' => fn ($query) => $query->latest('checked_at')->limit(100),
            'incidents' => fn ($query) => $query->with('report')->latest('detected_at')->limit(50),
        ]);

        $checks = $service->healthChecks;

        return view('services.report', [
            'service' => $service,
            'checks' => $checks,
            'incidents' => $service->incidents,
            'uptime' => $checks->isEmpty() ? null : $checks->where('status', 'healthy')->count() / $checks->count() * 100,
            'generatedAt' => now(),
        ]);
    }

    public function updateDocumentation(Request $request, Service $service): RedirectResponse
    {
        $validated = $request->validate([
            'documentation_url' => ['nullable', 'url:http,https', 'max:2048'],
        ]);

        $service->update($validated);

        return redirect()->route('services.report', $service)->with('status', 'Documentation link updated.');
    }

    public function checkService(Service $service, HealthProbe $probe): JsonResponse
    {
        $result = $probe->check($service);

        return response()->json([
            'service' => $service->name,
            'container' => $service->container_name,
            'healthy' => $result['healthy'],
            'status' => $result['healthy'] ? 'healthy' : 'unhealthy',
            'check_type' => $service->check_type,
            'check_target' => $service->check_target,
            'response_ms' => $result['response_ms'],
            'http_status' => $result['http_status'],
            'docker_available' => $result['docker_available'],
            'message' => $result['message'],
            'checked_at' => now()->toIso8601String(),
        ], $result['healthy'] ? 200 : 503);
    }

    private function dashboardData(): array
    {
        $services = Service::query()->where('enabled', true)->withCount([
            'incidents as open_incidents_count' => fn ($query) => $query->where('status', 'open'),
            'healthChecks as checks_last_24h_count' => fn ($query) => $query->where('checked_at', '>=', now()->subDay()),
            'healthChecks as healthy_checks_last_24h_count' => fn ($query) => $query
                ->where('status', 'healthy')
                ->where('checked_at', '>=', now()->subDay()),
        ])->orderBy('name')->get();

        return [
            'services' => $services,
            'incidents' => Incident::query()->with(['service', 'report'])->latest('detected_at')->limit(8)->get(),
            'events' => HealthCheck::query()->with('service')->where('checked_at', '>=', now()->subDay())->latest('checked_at')->limit(8)->get(),
            'healthy' => $services->where('status', 'healthy')->count(),
            'degraded' => $services->where('status', 'degraded')->count(),
            'down' => $services->where('status', 'down')->count(),
            'openIncidents' => Incident::query()->where('status', 'open')->count(),
            'lastCheck' => HealthCheck::query()->latest('checked_at')->value('checked_at'),
            'ollamaModel' => config('services.sentinel.ollama_model'),
            'overallStatus' => $services->isEmpty()
                ? 'Awaiting first check'
                : ($services->contains('status', 'down')
                    ? 'Attention needed'
                    : ($services->contains('status', 'degraded')
                        ? 'Some services degraded'
                        : ($services->contains('status', 'unknown')
                            ? 'Awaiting first check'
                            : 'All systems operational'))),
            'chaosEnabled' => (bool) config('services.sentinel.chaos_enabled'),
        ];
    }
}
