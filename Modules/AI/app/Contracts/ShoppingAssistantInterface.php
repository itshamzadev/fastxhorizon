<?php

namespace Modules\AI\app\Contracts;

use Illuminate\Support\Collection;
use Modules\AI\app\Models\AiChatSession;

interface ShoppingAssistantInterface
{
    public function sendMessage(
        int $sessionId,
        string $content,
        ?string $imageUrl = null
    ): array;

    public function startSession(
        ?int $customerId,
        ?string $guestId,
        string $locale = 'en'
    ): AiChatSession;

    public function listSessions(
        ?int $customerId,
        ?string $guestId,
        int $limit = 10
    ): Collection;

    public function getSession(
        int $sessionId,
        ?int $customerId,
        ?string $guestId
    ): ?AiChatSession;

    public function deleteSession(
        int $sessionId,
        ?int $customerId,
        ?string $guestId
    ): bool;
}
