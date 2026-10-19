<?php

namespace App\Jobs;

use App\Models\Incident;
use App\Models\IncidentReport;
use App\Services\DockerEngine;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class AnalyzeIncident implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 150;

    public function __construct(public readonly int $incidentId) {}

    public function handle(DockerEngine $docker): void
    {
        $incident = Incident::query()->with('service')->findOrFail($this->incidentId);
        $logs = $docker->logs($incident->service->container_name, 80);
        $model = config('services.sentinel.ollama_model');
        $prompt = <<<PROMPT
You are an on-premises operations assistant. Analyze the container logs below.
Return only a JSON object with string fields: severity (low, medium, high, or critical), likely_cause, suggested_fix.
Do not claim facts that are not supported by the logs. Keep each explanation concise.

Service: {$incident->service->name}
Failure: {$incident->failure_reason}
Logs:
{$logs}
PROMPT;

        $response = Http::baseUrl(rtrim(config('services.sentinel.ollama_url'), '/'))
            ->timeout($this->timeout - 10)
            ->post('/api/generate', [
                'model' => $model,
                'prompt' => $prompt,
                'stream' => false,
                'format' => 'json',
            ])
            ->throw()
            ->json('response');

        $analysis = is_string($response) ? json_decode($response, true) : null;
        if (! is_array($analysis)) {
            throw new RuntimeException('Ollama returned an invalid incident analysis.');
        }

        $severity = strtolower((string) ($analysis['severity'] ?? 'medium'));
        if (! in_array($severity, ['low', 'medium', 'high', 'critical'], true)) {
            $severity = 'medium';
        }

        IncidentReport::query()->updateOrCreate(
            ['incident_id' => $incident->id],
            [
                'severity' => $severity,
                'likely_cause' => trim((string) ($analysis['likely_cause'] ?? 'Analysis did not identify a likely cause.')),
                'suggested_fix' => trim((string) ($analysis['suggested_fix'] ?? 'Review the container logs and health check configuration.')),
                'log_excerpt' => mb_substr($logs, 0, 20000),
                'model' => $model,
            ],
        );
    }
}
