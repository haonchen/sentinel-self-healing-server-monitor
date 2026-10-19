<?php

namespace Tests\Feature;

use App\Models\Incident;
use App\Models\Service;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SentinelDashboardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_dashboard_displays_monitored_services_and_open_incidents(): void
    {
        $service = Service::query()->create([
            'name' => 'API Gateway',
            'slug' => 'api-gateway',
            'container_name' => 'sentinel-api',
            'check_target' => 'http://api:8080/health',
            'status' => 'healthy',
        ]);
        Incident::query()->create([
            'service_id' => $service->id,
            'status' => 'open',
            'failure_reason' => 'Health endpoint timed out',
            'detected_at' => now(),
        ]);

        $this->get('/')
            ->assertOk()
            ->assertSee('API Gateway')
            ->assertSee('Health endpoint timed out')
            ->assertSee('All systems operational');
    }

    public function test_dashboard_snapshot_returns_live_service_status_counts(): void
    {
        Service::query()->create([
            'name' => 'Queue',
            'slug' => 'queue',
            'container_name' => 'sentinel-queue',
            'check_target' => '127.0.0.1:6379',
            'check_type' => 'tcp',
            'status' => 'down',
        ]);

        $this->getJson('/dashboard/snapshot')
            ->assertOk()
            ->assertJsonPath('down', 1)
            ->assertJsonPath('services.0.status', 'down');
    }

    public function test_chaos_action_is_forbidden_until_explicitly_enabled(): void
    {
        $service = Service::query()->create([
            'name' => 'Queue',
            'slug' => 'queue',
            'container_name' => 'sentinel-queue',
            'check_target' => '127.0.0.1:6379',
        ]);
        config(['services.sentinel.chaos_enabled' => false]);

        $this->post(route('services.chaos', $service))->assertForbidden();
    }
}
