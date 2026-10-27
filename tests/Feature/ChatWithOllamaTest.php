<?php

namespace Tests\Feature;

use App\Jobs\ChatWithOllama;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

class ChatWithOllamaTest extends TestCase
{
    public function test_job_stores_the_local_model_response_for_polling(): void
    {
        config([
            'cache.default' => 'array',
            'services.sentinel.ollama_url' => 'http://ollama.test',
            'services.sentinel.ollama_model' => 'llama3.1:8b',
        ]);
        Http::preventStrayRequests();
        Http::fake([
            'ollama.test/api/chat' => Http::response([
                'message' => ['content' => 'Three services are healthy.'],
            ]),
        ]);

        $requestId = (string) Str::uuid();
        $messages = [['role' => 'user', 'content' => 'Summarize service health.']];
        Cache::put(ChatWithOllama::statusCacheKey($requestId), ['status' => 'pending'], now()->addMinutes(10));

        (new ChatWithOllama($requestId, $messages))->handle();

        $this->getJson(route('ollama.chat.status', $requestId))
            ->assertOk()
            ->assertJsonPath('status', 'completed')
            ->assertJsonPath('message', 'Three services are healthy.')
            ->assertJsonPath('model', 'llama3.1:8b');
        Http::assertSent(fn (Request $request): bool => str_contains($request->url(), 'ollama.test/api/chat')
            && $request['model'] === 'llama3.1:8b'
            && $request['stream'] === false
            && $request['options']['num_predict'] === 128
            && $request['messages'][0]['role'] === 'system'
            && $request['messages'][1]['content'] === 'Summarize service health.');
    }

    public function test_job_stores_a_clear_error_when_ollama_cannot_be_reached(): void
    {
        config([
            'cache.default' => 'array',
            'services.sentinel.ollama_url' => 'http://ollama.test',
        ]);
        Http::preventStrayRequests();
        Http::fake([
            'ollama.test/api/chat' => fn () => throw new ConnectionException('Connection refused.'),
        ]);

        $requestId = (string) Str::uuid();
        Cache::put(ChatWithOllama::statusCacheKey($requestId), ['status' => 'pending'], now()->addMinutes(10));

        (new ChatWithOllama($requestId, [['role' => 'user', 'content' => 'What is the status?']]))->handle();

        $this->getJson(route('ollama.chat.status', $requestId))
            ->assertStatus(502)
            ->assertJsonPath('status', 'failed')
            ->assertJsonPath('message', 'Ollama is unavailable. Check that it is running and that OLLAMA_URL is correct.');
    }
}
