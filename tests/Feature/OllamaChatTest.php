<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class OllamaChatTest extends TestCase
{
    use RefreshDatabase;

    public function test_chat_sends_bounded_conversation_to_the_configured_local_model(): void
    {
        Http::preventStrayRequests();
        Http::fake([
            'ollama.test/api/chat' => Http::response([
                'message' => ['content' => 'All three services are awaiting their first check.'],
            ]),
        ]);
        config([
            'services.sentinel.ollama_url' => 'http://ollama.test',
            'services.sentinel.ollama_model' => 'llama3.1:8b',
        ]);

        $response = $this->postJson(route('ollama.chat'), [
            'messages' => [
                ['role' => 'user', 'content' => 'Summarize service health.'],
            ],
        ]);

        $response->assertOk()->assertJsonPath('message', 'All three services are awaiting their first check.')
            ->assertJsonPath('model', 'llama3.1:8b');
        Http::assertSent(fn (Request $request) => str_contains($request->url(), 'ollama.test/api/chat')
            && $request['model'] === 'llama3.1:8b'
            && $request['stream'] === false
            && $request['messages'][0]['role'] === 'system'
            && $request['messages'][1]['content'] === 'Summarize service health.');
    }

    public function test_chat_rejects_history_that_does_not_end_with_a_user_message(): void
    {
        Http::preventStrayRequests();

        $response = $this->postJson(route('ollama.chat'), [
            'messages' => [
                ['role' => 'assistant', 'content' => 'Previous answer.'],
            ],
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('message', 'Send a user message to continue the conversation.');
        Http::assertNothingSent();
    }

    public function test_chat_returns_a_local_service_error_when_ollama_cannot_be_reached(): void
    {
        Http::preventStrayRequests();
        Http::fake([
            'ollama.test/api/chat' => fn () => throw new ConnectionException('Connection refused.'),
        ]);
        config(['services.sentinel.ollama_url' => 'http://ollama.test']);

        $response = $this->postJson(route('ollama.chat'), [
            'messages' => [
                ['role' => 'user', 'content' => 'What is the status?'],
            ],
        ]);

        $response->assertStatus(503)
            ->assertJsonPath('message', 'Ollama is unavailable. Check that it is running and that OLLAMA_URL is correct.');
    }
}
