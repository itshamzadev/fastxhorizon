<?php

namespace Modules\AI\app\Console;

use Illuminate\Console\Command;
use Modules\AI\app\Models\AiChatSession;

/**
 * Prunes stale guest sessions (messages cascade via FK) so the chat tables stay bounded;
 * customer-owned sessions are retained as history.
 */
class PruneChatSessions extends Command
{
    protected $signature = 'ai:prune-chat-sessions
        {--days=30 : Delete guest sessions inactive for this many days}
        {--empty-hours=24 : Delete any session with zero messages older than this many hours}';

    protected $description = 'Delete stale guest AI shopping-assistant sessions and their messages.';

    public function handle(): int
    {
        $days   = max(1, (int) $this->option('days'));
        $cutoff = now()->subDays($days);

        $deleted = AiChatSession::whereNull('customer_id')
            ->where(function ($q) use ($cutoff) {
                $q->where('last_activity_at', '<', $cutoff)
                  ->orWhereNull('last_activity_at')->where('created_at', '<', $cutoff);
            })
            ->delete();

        $this->info("Pruned {$deleted} guest chat session(s) inactive for {$days}+ days.");

        // Empty sessions (lazily created, never messaged) never surface in history but still
        // accumulate rows — prune them once old enough to be certainly abandoned.
        $emptyHours  = max(1, (int) $this->option('empty-hours'));
        $emptyCutoff = now()->subHours($emptyHours);

        $emptyDeleted = AiChatSession::doesntHave('messages')
            ->where('created_at', '<', $emptyCutoff)
            ->delete();

        $this->info("Pruned {$emptyDeleted} empty chat session(s) older than {$emptyHours}h.");

        return self::SUCCESS;
    }
}
