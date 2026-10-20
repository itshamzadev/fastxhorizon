<?php

namespace Modules\AI\app\Repositories;

use Illuminate\Support\Collection;
use Modules\AI\app\Contracts\Repositories\ChatMessageRepositoryInterface;
use Modules\AI\app\Models\AiChatMessage;

class ChatMessageRepository implements ChatMessageRepositoryInterface
{
    public function __construct(private readonly AiChatMessage $model) {}

    public function create(int $sessionId, string $role, string $content, array $meta = []): AiChatMessage
    {
        return $this->model->create([
            'session_id' => $sessionId,
            'role'       => $role,
            'content'    => $content,
            'meta'       => $meta ?: null,
        ]);
    }

    public function getLastN(int $sessionId, int $count = 10): Collection
    {
        return $this->model
            ->where('session_id', $sessionId)
            ->orderByDesc('created_at')
            ->limit($count)
            ->get()
            ->sortBy('created_at')
            ->values();
    }

    public function getForSession(int $sessionId): Collection
    {
        return $this->model
            ->where('session_id', $sessionId)
            ->orderBy('created_at')
            ->get();
    }
}
