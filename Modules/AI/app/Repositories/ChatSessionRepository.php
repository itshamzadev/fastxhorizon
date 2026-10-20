<?php

namespace Modules\AI\app\Repositories;

use Illuminate\Support\Collection;
use Modules\AI\app\Contracts\Repositories\ChatSessionRepositoryInterface;
use Modules\AI\app\Models\AiChatSession;

class ChatSessionRepository implements ChatSessionRepositoryInterface
{
    public function __construct(private readonly AiChatSession $model) {}

    public function create(array $data): AiChatSession
    {
        return $this->model->create($data);
    }

    public function find(int $id): ?AiChatSession
    {
        return $this->model->find($id);
    }

    public function findForUser(int $sessionId, ?int $customerId, ?string $guestId): ?AiChatSession
    {
        // No identity → no access. Without this guard an empty owner constraint
        // would resolve the session by id alone (IDOR).
        if (!$customerId && !$guestId) {
            return null;
        }

        return $this->model
            ->where('id', $sessionId)
            ->where(fn($q) => $q
                ->when($customerId, fn($q) => $q->orWhere('customer_id', $customerId))
                ->when($guestId, fn($q) => $q->orWhere('guest_id', $guestId)))
            ->first();
    }

    public function listForUser(?int $customerId, ?string $guestId, int $limit = 10): Collection
    {
        // No identity → empty list. Prevents an empty owner constraint from
        // returning every session in the table.
        if (!$customerId && !$guestId) {
            return new Collection();
        }

        return $this->model
            ->withCount('messages')
            ->where(fn($q) => $q
                ->when($customerId, fn($q) => $q->orWhere('customer_id', $customerId))
                ->when($guestId, fn($q) => $q->orWhere('guest_id', $guestId)))
            // Exclude empty sessions (lazily created on open but never messaged) so
            // the chat history never shows a blank, untitled conversation.
            ->having('messages_count', '>=', 1)
            ->orderByDesc('last_activity_at')
            ->limit($limit)
            ->get();
    }

    public function updateTitle(int $sessionId, string $title): bool
    {
        return (bool) $this->model->where('id', $sessionId)->update(['title' => $title]);
    }

    public function updateContext(int $sessionId, array $context): bool
    {
        return (bool) $this->model->where('id', $sessionId)->update(['context' => $context]);
    }

    public function touch(int $sessionId): bool
    {
        return (bool) $this->model->where('id', $sessionId)
            ->update(['last_activity_at' => now()]);
    }

    public function delete(int $sessionId): bool
    {
        return (bool) $this->model->where('id', $sessionId)->delete();
    }
}
