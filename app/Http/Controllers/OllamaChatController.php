<?php

namespace App\Http\Controllers;

use App\Jobs\ChatWithOllama;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

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
        // Keep prompts small so local models do not spend most of their time
        // re-reading an ever-growing chat history.
        $messages = array_slice(array_values($validated['messages']), -6);
        $messages = array_map(static function (array $message): array {
            $message['content'] = mb_substr($message['content'], -1500);

            return $message;
        }, $messages);

        if (end($messages)['role'] !== 'user') {
            return response()->json(['message' => 'Send a user message to continue the conversation.'], 422);
        }

        $requestId = (string) Str::uuid();

        Cache::put(ChatWithOllama::statusCacheKey($requestId), ['status' => 'pending'], now()->addMinutes(10));
        ChatWithOllama::dispatch($requestId, $messages);

        return response()->json([
            'status' => 'pending',
            'request_id' => $requestId,
            'status_url' => route('ollama.chat.status', $requestId, false),
        ], 202);
    }

    public function status(string $requestId): JsonResponse
    {
        $status = Cache::get(ChatWithOllama::statusCacheKey($requestId));

        if (! is_array($status) || ! isset($status['status'])) {
            return response()->json(['message' => 'This assistant request was not found or has expired.'], 404);
        }

        if (in_array($status['status'], ['pending', 'processing'], true)) {
            return response()->json(['status' => $status['status']], 202);
        }

        if ($status['status'] === 'failed') {
            return response()->json([
                'status' => 'failed',
                'message' => $status['message'] ?? 'The local assistant could not complete this request. Please try again.',
            ], 502);
        }

        if ($status['status'] !== 'completed' || ! isset($status['message'], $status['model'])) {
            return response()->json(['message' => 'The assistant request has an invalid status. Please try again.'], 500);
        }

        return response()->json([
            'status' => 'completed',
            'message' => $status['message'],
            'model' => $status['model'],
        ]);
    }
}
