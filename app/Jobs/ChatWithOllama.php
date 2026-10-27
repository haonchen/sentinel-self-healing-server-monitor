<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Throwable;

class ChatWithOllama implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 150;

    /**
     * @param  list<array{role: string, content: string}>  $messages
     */
    public function __construct(
        public readonly string $requestId,
        public readonly array $messages,
    ) {}

    public function handle(): void
    {
        $this->storeStatus(['status' => 'processing']);

        $model = config('services.sentinel.ollama_model');
        $payload = [
            'model' => $model,
            'stream' => false,
            'options' => [
                'num_predict' => 128,
                'temperature' => 0.2,
            ],
            'messages' => [
                [
                    'role' => 'system',
                    'content' => 'You are Sentinel, a concise operations assistant running entirely on this machine. Help with Docker services, health checks, incident timelines, and troubleshooting. Only treat service data included in the conversation as evidence; do not claim access to live monitoring data unless it is provided. Be clear when you are uncertain.',
                ],
                ...$this->messages,
            ],
        ];

        try {
            $response = Http::connectTimeout(3)
                ->timeout($this->timeout - 10)
                ->post(rtrim(config('services.sentinel.ollama_url'), '/').'/api/chat', $payload);
        } catch (ConnectionException) {
            $this->storeStatus([
                'status' => 'failed',
                'message' => 'Ollama is unavailable. Check that it is running and that OLLAMA_URL is correct.',
            ]);

            return;
        }

        if (! $response->successful()) {
            $this->storeStatus([
                'status' => 'failed',
                'message' => 'Ollama returned an error. Check the local server and model configuration.',
            ]);

            return;
        }

        $answer = $response->json('message.content');
        if (! is_string($answer) || trim($answer) === '') {
            $this->storeStatus([
                'status' => 'failed',
                'message' => 'Ollama returned an empty response. Please try again.',
            ]);

            return;
        }

        $this->storeStatus([
            'status' => 'completed',
            'message' => trim($answer),
            'model' => $model,
        ]);
    }

    public function failed(?Throwable $exception): void
    {
        $this->storeStatus([
            'status' => 'failed',
            'message' => 'The local assistant could not complete this request. Please try again.',
        ]);
    }

    public static function statusCacheKey(string $requestId): string
    {
        return 'sentinel:ollama-chat:'.$requestId;
    }

    /**
     * @param  array{status: string, message?: string, model?: string}  $status
     */
    private function storeStatus(array $status): void
    {
        Cache::put(self::statusCacheKey($this->requestId), $status, now()->addMinutes(10));
    }
}
