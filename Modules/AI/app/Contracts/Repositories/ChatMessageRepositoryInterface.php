<?php

namespace Modules\AI\app\Contracts\Repositories;

use Illuminate\Support\Collection;
use Modules\AI\app\Models\AiChatMessage;

interface ChatMessageRepositoryInterface
{
    public function create(
        int $sessionId,
        string $role,
        string $content,
        array $meta = []
    ): AiChatMessage;

    /** Last N messages ordered oldest → newest (for context window). */
    public function getLastN(int $sessionId, int $count = 10): Collection;

    /** All messages for a session ordered oldest → newest. */
    public function getForSession(int $sessionId): Collection;
}
