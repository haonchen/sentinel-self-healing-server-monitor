<?php

namespace Tests\Feature;

use App\Jobs\AnalyzeIncident;
use App\Models\Incident;
use App\Models\IncidentReport;
use App\Models\Service;
use App\Services\DockerEngine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Mockery\MockInterface;
use Tests\TestCase;

class AnalyzeIncidentTest extends TestCase
{
    use RefreshDatabase;

    public function test_job_sends_recent_logs_to_local_ollama_and_saves_structured_analysis(): void
    {
        $service = Service::query()->create([
            'name' => 'Public API',
            'slug' => 'public-api',
            'container_name' => 'api-container',
            'check_target' => 'http://api.test/health',
        ]);
        $incident = Incident::query()->create([
            'service_id' => $service->id,
            'status' => 'open',
            'failure_reason' => 'HTTP 503',
            'detected_at' => now(),
        ]);

        $docker = $this->mock(DockerEngine::class, fn (MockInterface $mock) => $mock
            ->shouldReceive('logs')->once()->with('api-container', 80)->andReturn('database connection refused'));
        Http::fake([
            'ollama.test/api/generate' => Http::response([
                'response' => json_encode([
                    'severity' => 'high',
                    'likely_cause' => 'The database connection was refused.',
                    'suggested_fix' => 'Check the database listener and credentials.',
                ]),
            ]),
        ]);
        config([
            'services.sentinel.ollama_url' => 'http://ollama.test',
            'services.sentinel.ollama_model' => 'llama3.1:8b',
        ]);

        (new AnalyzeIncident($incident->id))->handle($docker);

        $report = IncidentReport::query()->sole();
        $this->assertSame('high', $report->severity);
        $this->assertSame('database connection refused', $report->log_excerpt);
        $this->assertSame('llama3.1:8b', $report->model);
        Http::assertSent(fn (Request $request) => str_contains($request->url(), 'ollama.test/api/generate')
            && $request['model'] === 'llama3.1:8b'
            && str_contains($request['prompt'], 'database connection refused'));
    }
}
