<?php

namespace App\Http\Controllers;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class OllamaChatController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'messages' => ['required', 'array', 'min:1', 'max:12'],
            'messages.*' => ['array:role,content'],
            'messages.*.role' => ['required', 'in:user,assistant'],
            'messages.*.content' => ['required', 'string', 'max:4000'],
        ]);
        $messages = array_values($validated['messages']);

        if (end($messages)['role'] !== 'user') {
            return response()->json(['message' => 'Send a user message to continue the conversation.'], 422);
        }

        $model = config('services.sentinel.ollama_model');
        $payload = [
            'model' => $model,
            'stream' => false,
            'messages' => [
                [
                    'role' => 'system',
                    'content' => 'You are Sentinel, a concise operations assistant running entirely on this machine. Help with Docker services, health checks, incident timelines, and troubleshooting. Only treat service data included in the conversation as evidence; do not claim access to live monitoring data unless it is provided. Be clear when you are uncertain.',
                ],
                ...array_slice($messages, -10),
            ],
        ];

        try {
            $response = Http::connectTimeout(3)
                ->timeout(120)
                ->post(rtrim(config('services.sentinel.ollama_url'), '/').'/api/chat', $payload);
        } catch (ConnectionException) {
            return response()->json([
                'message' => 'Ollama is unavailable. Check that it is running and that OLLAMA_URL is correct.',
            ], 503);
        }

        if (! $response->successful()) {
            return response()->json([
                'message' => 'Ollama returned an error. Check the local server and model configuration.',
            ], 502);
        }

        $answer = $response->json('message.content');
        if (! is_string($answer) || trim($answer) === '') {
            return response()->json(['message' => 'Ollama returned an empty response. Please try again.'], 502);
        }

        return response()->json([
            'message' => trim($answer),
            'model' => $model,
        ]);
    }
}
