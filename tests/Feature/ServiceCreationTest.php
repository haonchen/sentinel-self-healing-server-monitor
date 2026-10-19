<?php

namespace Tests\Feature;

use App\Models\Service;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ServiceCreationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_http_service_is_saved_and_added_to_monitoring(): void
    {
        $response = $this->from('/')->post(route('services.store'), [
            'name' => 'Payments API',
            'container_name' => 'payments-api',
            'check_type' => 'http',
            'check_target' => 'https://payments.internal/health',
            'cooldown_seconds' => 300,
            'description' => 'Handles payment requests.',
        ]);

        $response->assertRedirect(route('dashboard'))
            ->assertSessionHas('status', 'Payments API is now monitored.')
            ->assertSessionHasNoErrors();

        $service = Service::query()->sole();
        $this->assertModelExists($service);
        $this->assertSame('payments-api', $service->slug);
        $this->assertSame('unknown', $service->status);
        $this->assertSame('https://payments.internal/health', $service->check_target);
    }

    public function test_tcp_service_accepts_a_valid_host_and_port(): void
    {
        $response = $this->from('/')->post(route('services.store'), [
            'name' => 'Cache',
            'container_name' => 'sentinel-cache',
            'check_type' => 'tcp',
            'check_target' => 'cache:6379',
            'cooldown_seconds' => 300,
        ]);

        $response->assertRedirect(route('dashboard'));
        $this->assertDatabaseHas('services', [
            'slug' => 'cache',
            'check_type' => 'tcp',
            'check_target' => 'cache:6379',
        ]);
    }

    public function test_tcp_service_rejects_a_port_above_the_valid_range(): void
    {
        $response = $this->from('/')->post(route('services.store'), [
            'name' => 'Cache',
            'container_name' => 'sentinel-cache',
            'check_type' => 'tcp',
            'check_target' => 'cache:70000',
            'cooldown_seconds' => 300,
        ]);

        $response->assertRedirect('/')
            ->assertSessionHasErrors([
                'check_target' => 'Enter a hostname and port from 1 to 65535, for example cache:6379.',
            ]);
        $this->assertDatabaseCount('services', 0);
    }
}
