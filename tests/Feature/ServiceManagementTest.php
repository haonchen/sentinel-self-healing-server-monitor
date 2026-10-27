<?php

namespace Tests\Feature;

use App\Models\HealthCheck;
use App\Models\Incident;
use App\Models\IncidentReport;
use App\Models\Service;
use App\Services\DockerEngine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Mockery\MockInterface;
use Tests\TestCase;

class ServiceManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_dashboard_displays_service_management_actions(): void
    {
        config(['services.sentinel.chaos_enabled' => true]);
        $service = $this->createService();

        $this->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Manage')
            ->assertSee('Configure')
            ->assertSee('Remove')
            ->assertSee('Chaos test')
            ->assertSee(route('services.update', $service));
    }

    public function test_service_configuration_updates_settings_and_resets_status(): void
    {
        $service = $this->createService([
            'status' => 'down',
            'consecutive_failures' => 3,
            'last_checked_at' => now(),
        ]);

        $this->put(route('services.update', $service), [
            'name' => 'Payments API',
            'container_name' => 'payments-api',
            'check_type' => 'http',
            'check_target' => 'https://payments.internal/health',
            'cooldown_seconds' => 600,
            'description' => 'Handles payment requests.',
            'documentation_url' => 'https://docs.example.com/payments',
        ])
            ->assertRedirect(route('dashboard'))
            ->assertSessionHas('status', 'Payments API configuration updated.');

        $this->assertDatabaseHas('services', [
            'id' => $service->id,
            'name' => 'Payments API',
            'container_name' => 'payments-api',
            'check_type' => 'http',
            'check_target' => 'https://payments.internal/health',
            'cooldown_seconds' => 600,
            'description' => 'Handles payment requests.',
            'documentation_url' => 'https://docs.example.com/payments',
            'status' => 'unknown',
            'consecutive_failures' => 0,
            'last_checked_at' => null,
        ]);
    }

    public function test_service_configuration_rejects_an_invalid_health_target(): void
    {
        $service = $this->createService();

        $this->from(route('dashboard'))
            ->followingRedirects()
            ->put(route('services.update', $service), [
                'name' => 'Cache',
                'container_name' => 'cache',
                'check_type' => 'tcp',
                'check_target' => 'cache:70000',
                'cooldown_seconds' => 300,
            ])
            ->assertOk()
            ->assertSee('Enter a hostname and port from 1 to 65535, for example cache:6379.')
            ->assertSee(route('services.update', $service))
            ->assertSee('value="Cache"', false)
            ->assertSee('value="cache:70000"', false);

        $this->assertDatabaseHas('services', [
            'id' => $service->id,
            'check_target' => 'http://api.test/health',
        ]);
    }

    public function test_removing_a_service_deletes_its_health_checks_and_incidents(): void
    {
        $service = $this->createService();
        $healthCheck = HealthCheck::query()->create([
            'service_id' => $service->id,
            'status' => 'healthy',
            'checked_at' => now(),
        ]);
        $incident = Incident::query()->create([
            'service_id' => $service->id,
            'status' => 'open',
            'detected_at' => now(),
        ]);
        $incidentReport = IncidentReport::query()->create([
            'incident_id' => $incident->id,
            'severity' => 'low',
        ]);

        $this->delete(route('services.destroy', $service))
            ->assertRedirect(route('dashboard'))
            ->assertSessionHas('status', 'API is no longer monitored.');

        $this->assertDatabaseMissing('services', ['id' => $service->id]);
        $this->assertDatabaseMissing('health_checks', ['id' => $healthCheck->id]);
        $this->assertDatabaseMissing('incidents', ['id' => $incident->id]);
        $this->assertDatabaseMissing('incident_reports', ['id' => $incidentReport->id]);
    }

    public function test_chaos_test_stops_the_service_container_when_enabled(): void
    {
        config(['services.sentinel.chaos_enabled' => true]);
        $service = $this->createService();
        $this->mock(DockerEngine::class, fn (MockInterface $mock) => $mock
            ->shouldReceive('stop')->once()->with('api-container'));

        $this->post(route('services.chaos', $service))
            ->assertRedirect(route('dashboard'))
            ->assertSessionHas('status', 'API stopped for a chaos test.');
    }

    /**
     * @param  array{
     *     status?: string,
     *     consecutive_failures?: int,
     *     last_checked_at?: Carbon|null
     * }  $attributes
     */
    private function createService(array $attributes = []): Service
    {
        return Service::query()->create([
            'name' => 'API',
            'slug' => 'api',
            'container_name' => 'api-container',
            'check_target' => 'http://api.test/health',
            ...$attributes,
        ]);
    }
}
