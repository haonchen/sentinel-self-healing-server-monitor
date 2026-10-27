<?php

namespace Tests\Feature;

use App\Jobs\ChatWithOllama;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Tests\TestCase;

class OllamaChatTest extends TestCase
{
    use RefreshDatabase;

    public function test_chat_queues_bounded_conversation_and_returns_a_polling_url(): void
    {
        config(['cache.default' => 'array']);
        Queue::fake([ChatWithOllama::class]);

        $messages = [];
        for ($index = 0; $index < 8; $index++) {
            $messages[] = [
                'role' => $index % 2 === 0 ? 'assistant' : 'user',
                'content' => "Message {$index}",
            ];
        }
        $messages[7] = ['role' => 'user', 'content' => str_repeat('x', 2000)];

        $response = $this->postJson(route('ollama.chat'), ['messages' => $messages]);

        $response->assertStatus(202)
            ->assertJsonPath('status', 'pending')
            ->assertJsonPath('status_url', route('ollama.chat.status', $response->json('request_id'), false));
        $this->getJson(route('ollama.chat.status', $response->json('request_id')))
            ->assertStatus(202)
            ->assertJsonPath('status', 'pending');

        Queue::assertPushed(ChatWithOllama::class, fn (ChatWithOllama $job): bool => count($job->messages) === 6
            && $job->messages[0]['content'] === 'Message 2'
            && mb_strlen($job->messages[5]['content']) === 1500);
        $this->assertSame(
            ['status' => 'pending'],
            Cache::get(ChatWithOllama::statusCacheKey($response->json('request_id'))),
        );
    }

    public function test_chat_rejects_history_that_does_not_end_with_a_user_message(): void
    {
        config(['cache.default' => 'array']);
        Queue::fake([ChatWithOllama::class]);

        $response = $this->postJson(route('ollama.chat'), [
            'messages' => [
                ['role' => 'assistant', 'content' => 'Previous answer.'],
            ],
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('message', 'Send a user message to continue the conversation.');
        Queue::assertNothingPushed();
    }

    public function test_unknown_chat_request_returns_404(): void
    {
        config(['cache.default' => 'array']);

        $this->getJson(route('ollama.chat.status', Str::uuid()))
            ->assertNotFound()
            ->assertJsonPath('message', 'This assistant request was not found or has expired.');
    }
}
