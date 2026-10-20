<?php

namespace Modules\AI\app\Contracts\Repositories;

use Illuminate\Support\Collection;
use Modules\AI\app\Models\AiChatSession;

interface ChatSessionRepositoryInterface
{
    public function create(array $data): AiChatSession;

    public function find(int $id): ?AiChatSession;

    public function findForUser(
        int $sessionId,
        ?int $customerId,
        ?string $guestId
    ): ?AiChatSession;

    public function listForUser(
        ?int $customerId,
        ?string $guestId,
        int $limit = 10
    ): Collection;

    public function updateTitle(int $sessionId, string $title): bool;

    public function updateContext(int $sessionId, array $context): bool;

    public function touch(int $sessionId): bool;

    public function delete(int $sessionId): bool;
}
