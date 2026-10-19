<?php

namespace Tests\Feature;

use App\Jobs\AnalyzeIncident;
use App\Models\HealthCheck;
use App\Models\Incident;
use App\Models\Service;
use App\Services\DockerEngine;
use App\Services\ServiceMonitor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;
use Mockery\MockInterface;
use Tests\TestCase;

class ServiceMonitorTest extends TestCase
{
    use RefreshDatabase;

    private Service $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = Service::query()->create([
            'name' => 'Public API',
            'slug' => 'public-api',
            'container_name' => 'api-container',
            'check_target' => 'http://api.test/health',
        ]);
    }

    public function test_healthy_http_probe_is_recorded_and_resolves_an_open_incident(): void
    {
        $incident = Incident::query()->create([
            'service_id' => $this->service->id,
            'status' => 'open',
            'detected_at' => now()->subMinute(),
        ]);
        $this->mock(DockerEngine::class, fn (MockInterface $mock) => $mock
            ->shouldReceive('isRunning')->once()->with('api-container')->andReturn(true));
        Http::fake(['api.test/health' => Http::response(['ok' => true], 200)]);

        $checked = app(ServiceMonitor::class)->run();

        $this->assertSame(1, $checked);
        $this->assertSame('healthy', $this->service->fresh()->status);
        $this->assertSame('resolved', $incident->fresh()->status);
        $this->assertSame('healthy', HealthCheck::query()->sole()->status);
    }

    public function test_failed_probe_opens_one_incident_and_restarts_once_during_cooldown(): void
    {
        Bus::fake();
        $docker = $this->mock(DockerEngine::class, fn (MockInterface $mock) => $mock
            ->shouldReceive('isRunning')->twice()->with('api-container')->andReturn(true)
            ->shouldReceive('restart')->once()->with('api-container'));
        Http::fake(['api.test/health' => Http::response('unavailable', 503)]);

        app(ServiceMonitor::class)->run();
        app(ServiceMonitor::class)->run();

        $this->assertSame(1, Incident::query()->count());
        $this->assertSame(2, HealthCheck::query()->count());
        $this->assertSame('degraded', $this->service->fresh()->status);
        Bus::assertDispatchedTimes(AnalyzeIncident::class, 1);
        $docker->shouldHaveReceived('restart')->once();
    }
}
