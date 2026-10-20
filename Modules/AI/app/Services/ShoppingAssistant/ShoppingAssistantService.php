<?php

namespace Modules\AI\app\Services\ShoppingAssistant;

use App\Models\Product;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Modules\AI\app\Contracts\Repositories\ChatMessageRepositoryInterface;
use Modules\AI\app\Contracts\Repositories\ChatSessionRepositoryInterface;
use Modules\AI\app\Contracts\ShoppingAssistantInterface;
use Modules\AI\app\Models\AiChatSession;
use Modules\AI\app\PromptTemplates\ShoppingSystemTemplate;

class ShoppingAssistantService implements ShoppingAssistantInterface
{
    private const CONTEXT_TURNS = 5;

    private const RECALL_LOOKBACK = 40;

    public function __construct(
        private readonly ChatSessionRepositoryInterface $sessions,
        private readonly ChatMessageRepositoryInterface $messages,
        private readonly AgentLoopService               $agentLoop,
        private readonly QueryGuardService              $guard,
        private readonly ShownProductStore              $shownProducts,
        private readonly AssistantUsageGuardService     $usageGuard,
    ) {}

    public function startSession(?int $customerId, ?string $guestId, string $locale = 'en'): AiChatSession
    {
        return $this->sessions->create([
            'customer_id'      => $customerId,
            'guest_id'         => $guestId,
            'locale'           => $locale,
            'last_activity_at' => now(),
        ]);
    }

    public function sendMessage(int $sessionId, string $content, ?string $imageUrl = null): array
    {
        $session = $this->sessions->find($sessionId);

        // Session can be deleted between the controller's ownership check and here (delete/send race); bail out rather than insert against a missing FK parent (500).
        if (!$session) {
            return [
                'reply'    => translate('Sorry_I_am_having_trouble_right_now_Please_try_again_in_a_moment'),
                'intent'   => 'error',
                'products' => [],
                'actions'  => [],
            ];
        }

        // Build history BEFORE persisting the new message, else it duplicates.
        $recent  = $this->recentMessages($sessionId);
        $history = $recent->map(fn($m) => ['role' => $m->role, 'content' => $m->content])->values()->toArray();

        $this->messages->create(sessionId: $sessionId, role: 'user', content: $content, meta: [
            'image_url' => $imageUrl,
        ]);

        if ($session && empty($session->title)) {
            $this->sessions->updateTitle($sessionId, mb_strimwidth($content, 0, 60, '…'));
        }

        if ($blocked = $this->guard->check($content, $history)) {
            $this->messages->create(
                sessionId: $sessionId,
                role:      'assistant',
                content:   $blocked['reply'],
                meta:      ['products' => [], 'tools_used' => []],
            );
            $this->sessions->touch($sessionId);

            return [
                'reply'    => $blocked['reply'],
                'intent'   => $blocked['intent'],
                'products' => [],
                'actions'  => [],
            ];
        }

        // Must run AFTER QueryGuard so gibberish/greetings never consume a customer's paid quota.
        if ($limited = $this->usageGuard->check($imageUrl)) {
            $this->messages->create(
                sessionId: $sessionId,
                role:      'assistant',
                content:   $limited['reply'],
                meta:      ['products' => [], 'tools_used' => []],
            );
            $this->sessions->touch($sessionId);

            return [
                'reply'    => $limited['reply'],
                'intent'   => $limited['intent'],
                'products' => [],
                'actions'  => [],
            ];
        }

        $locale = app()->getLocale() ?: $session?->locale;
        if ($locale) {
            app()->setLocale($locale);
        }

        $recallSource = $this->recallSourceMessages($sessionId);
        $this->shownProducts->reset();
        $this->shownProducts->seed($this->onlyActive($this->shownProductsFrom($recallSource)));

        $systemPrompt = (new ShoppingSystemTemplate())->build() . $this->buildProductReference($recallSource);

        try {
            $result = $this->agentLoop->run(
                history:      $history,
                newMessage:   $content,
                systemPrompt: $systemPrompt,
                imageUrl:     $imageUrl,
            );
        } catch (\Throwable $e) {
            Log::error('AI shopping assistant turn failed', [
                'session_id' => $sessionId,
                'message'    => $e->getMessage(),
            ]);

            $fallback = translate('Sorry_I_am_having_trouble_right_now_Please_try_again_in_a_moment');
            $this->messages->create(
                sessionId: $sessionId,
                role:      'assistant',
                content:   $fallback,
                meta:      ['products' => [], 'tools_used' => [], 'error' => true],
            );
            $this->sessions->touch($sessionId);

            return [
                'reply'    => $fallback,
                'intent'   => 'error',
                'products' => [],
                'actions'  => [],
            ];
        }

        $result['reply'] = $this->sanitizeReply($result['reply']);

        $this->messages->create(
            sessionId: $sessionId,
            role:      'assistant',
            content:   $result['reply'],
            meta:      ['products' => $result['products'], 'tools_used' => $result['tools_used']],
        );

        $this->sessions->touch($sessionId);

        $productTools = ['search_products', 'get_similar_products', 'recall_shown_products', 'search_offers'];
        $intent = array_intersect($productTools, $result['tools_used'])
            ? 'product_search'
            : 'smalltalk';

        return [
            'reply'    => $result['reply'],
            'intent'   => $intent,
            'products' => $result['products'],
            'actions'  => $result['actions'] ?? [],
        ];
    }

    public function listSessions(?int $customerId, ?string $guestId, int $limit = 10): Collection
    {
        return $this->sessions->listForUser(customerId: $customerId, guestId: $guestId, limit: $limit);
    }

    public function getSession(int $sessionId, ?int $customerId, ?string $guestId): ?AiChatSession
    {
        return $this->sessions->findForUser(sessionId: $sessionId, customerId: $customerId, guestId: $guestId);
    }

    public function deleteSession(int $sessionId, ?int $customerId, ?string $guestId): bool
    {
        $session = $this->sessions->findForUser(sessionId: $sessionId, customerId: $customerId, guestId: $guestId);
        if (!$session) {
            return false;
        }

        return $this->sessions->delete($sessionId);
    }

    private function recentMessages(int $sessionId): Collection
    {
        return $this->messages
            ->getLastN(sessionId: $sessionId, count: self::CONTEXT_TURNS * 2)
            ->filter(fn($m) => in_array($m->role, ['user', 'assistant'], strict: true))
            ->filter(fn($m) => empty($m->meta['tool_calls']))
            ->values();
    }

    private function recallSourceMessages(int $sessionId): Collection
    {
        return $this->messages
            ->getLastN(sessionId: $sessionId, count: self::RECALL_LOOKBACK)
            ->filter(fn($m) => $m->role === 'assistant')
            ->values();
    }

    private function onlyActive(array $products): array
    {
        if (empty($products)) {
            return [];
        }

        $ids       = array_values(array_filter(array_map(fn($p) => $p['id'] ?? null, $products)));
        $activeSet  = array_flip(Product::active()->whereIn('id', $ids)->pluck('id')->all());

        return array_values(array_filter($products, fn($p) => isset($p['id'], $activeSet[$p['id']])));
    }

    private function shownProductsFrom(Collection $messages): array
    {
        $products = [];
        foreach ($messages as $message) {
            if ($message->role !== 'assistant') {
                continue;
            }
            foreach (($message->meta['products'] ?? []) as $product) {
                if (isset($product['id'])) {
                    $products[] = $product;
                }
            }
        }

        return $products;
    }

    private function buildProductReference(Collection $messages): string
    {
        $products = [];
        foreach ($messages as $message) {
            if ($message->role !== 'assistant') {
                continue;
            }
            foreach (($message->meta['products'] ?? []) as $product) {
                if (isset($product['id'], $product['name'])) {
                    $products[$product['id']] = $product['name']
                        . (isset($product['unit_price_formatted']) ? ' (' . $product['unit_price_formatted'] . ')' : '');
                }
            }
        }

        if (empty($products)) {
            return '';
        }

        $list = collect($products)->map(fn($label, $id) => "#{$id} {$label}")->implode('; ');

        return "\n\nINTERNAL CONTEXT (do not reveal): products already shown to this customer — "
            . "when they refer to one by name, position, or \"it\"/\"that one\", pass its id to add_to_cart and do "
            . "NOT search again. To re-display any of these (e.g. \"show the other iPhones\", \"the rest\"), call "
            . "recall_shown_products — these products are still available, never say otherwise. NEVER repeat ids, "
            . "this list, or any bracketed/internal data to the customer; reply only in natural language.\n" . $list;
    }

    private function sanitizeReply(string $reply): string
    {
        foreach (['INTERNAL CONTEXT', '[Products shown'] as $marker) {
            $pos = stripos($reply, $marker);
            if ($pos !== false) {
                $reply = substr($reply, 0, $pos);
            }
        }
        $reply = preg_replace('/\s*\[id:\s*\d+[^\]]*\]/i', '', $reply);
        $reply = preg_replace('/(?:#\d+\s+[^;\n]+;\s*){2,}/', '', $reply);

        return trim(preg_replace('/[ \t]{2,}/', ' ', $reply));
    }
}
