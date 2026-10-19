<?php

namespace App\Services;

use App\Jobs\AnalyzeIncident;
use App\Models\HealthCheck;
use App\Models\Service;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class ServiceMonitor
{
    public function __construct(
        private readonly HealthProbe $probe,
        private readonly DockerEngine $docker,
    ) {}

    public function run(): int
    {
        $checked = 0;

        Service::query()->where('enabled', true)->each(function (Service $service) use (&$checked): void {
            try {
                $this->checkService($service);
                $checked++;
            } catch (Throwable $exception) {
                Log::error('Sentinel monitor failed for service.', [
                    'service' => $service->slug,
                    'exception' => $exception->getMessage(),
                ]);
            }
        });

        return $checked;
    }

    private function checkService(Service $service): void
    {
        $result = $this->probe->check($service);
        $now = now();
        $incidentToAnalyze = null;

        DB::transaction(function () use ($service, $result, $now, &$incidentToAnalyze): void {
            $current = Service::query()->lockForUpdate()->find($service->id);
            if (! $current || ! $current->enabled) {
                return;
            }

            HealthCheck::query()->create([
                'service_id' => $current->id,
                'status' => $result['healthy'] ? 'healthy' : 'unhealthy',
                'response_ms' => $result['response_ms'],
                'http_status' => $result['http_status'],
                'message' => $result['message'],
                'checked_at' => $now,
            ]);

            $openIncident = $current->incidents()->where('status', 'open')->latest('detected_at')->first();

            if ($result['healthy']) {
                $current->update([
                    'status' => 'healthy',
                    'consecutive_failures' => 0,
                    'last_checked_at' => $now,
                    'last_healthy_at' => $now,
                ]);

                if ($openIncident) {
                    $openIncident->update(['status' => 'resolved', 'recovered_at' => $now]);
                }

                return;
            }

            $failures = $current->consecutive_failures + 1;
            $current->update([
                'status' => $failures >= 3 ? 'down' : 'degraded',
                'consecutive_failures' => $failures,
                'last_checked_at' => $now,
            ]);

            if (! $openIncident) {
                $openIncident = $current->incidents()->create([
                    'status' => 'open',
                    'failure_reason' => $result['message'],
                    'detected_at' => $now,
                ]);
                $incidentToAnalyze = $openIncident->id;
            }
        });

        if ($result['healthy']) {
            return;
        }

        $current = $service->fresh();
        $openIncident = $current?->incidents()->where('status', 'open')->latest('detected_at')->first();
        if (! $current || ! $openIncident) {
            return;
        }

        if ($result['docker_available'] && $this->cooldownExpired($current)) {
            try {
                $this->docker->restart($current->container_name);
                $current->update(['last_restart_at' => $now]);
                $openIncident->update(['restarted_at' => $now]);
            } catch (Throwable $exception) {
                Log::warning('Sentinel could not restart container.', [
                    'service' => $current->slug,
                    'exception' => $exception->getMessage(),
                ]);
            }
        }

        if ($incidentToAnalyze !== null) {
            AnalyzeIncident::dispatch($incidentToAnalyze);
        }
    }

    private function cooldownExpired(Service $service): bool
    {
        return $service->last_restart_at === null
            || $service->last_restart_at->lte(now()->subSeconds($service->cooldown_seconds));
    }
}
