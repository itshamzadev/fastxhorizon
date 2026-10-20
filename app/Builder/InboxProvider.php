<?php

namespace App\Builder;

use App\Enums\GlobalConstant;
use App\Events\ChattingEvent;
use App\Models\Chatting;
use App\Models\Seller;
use App\Models\Shop;
use App\Models\User;
use App\Traits\FileManagerTrait;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Modules\Builder\Contracts\InboxProvider as InboxProviderContract;
use Modules\Builder\ValueObjects\Storefront\ConversationDTO;
use Modules\Builder\ValueObjects\Storefront\MessageDTO;

/**
 * 6Valley host adapter for the storefront inbox.
 *
 * 6Valley stores chat as per-message `chattings` rows rather than
 * Conversation/Message aggregates, so a "conversation" is the customer's thread
 * with one shop, identified by `seller_id` (which is what the module treats as
 * the conversation id). Messages, unread counts and last-message previews are
 * derived from those rows.
 *
 * Conversation id `0` is the in-house store — the module's "synthetic vendor"
 * sentinel. 6Valley persists that thread against `admin_id = 0` with a NULL
 * `seller_id` (see `RestAPI/v1/ChatController::send`), so it is NOT addressable
 * as `seller_id = 0`; every read and write branches on it.
 *
 * Delivery-man threads are deliberately absent: `capabilities.features
 * .deliveryManChat` is false for 6Valley, and OrderProvider withholds the
 * delivery man id so the chat entry point never renders.
 */
class InboxProvider implements InboxProviderContract
{
    use FileManagerTrait;

    private const ATTACHMENT_PREVIEW = '📎 Attachment';

    /** In-house store thread — `admin_id = 0`, not a seller row. */
    private const ADMIN_CONVERSATION_ID = 0;

    public function conversations(int $customerId, ?int $storeId, ?string $search = null, int $limit = 30, int $offset = 1, ?string $openWith = null): array
    {
        // Vendor storefront: the chattings table holds the customer's threads
        // with every seller platform-wide, so without scoping the inbox lists
        // other vendors the customer has messaged. Limit to this shop's vendor.
        $storeSellerId = $this->storeSellerId($storeId);
        $conversationIds = $this->reachableConversationIds($customerId, $storeSellerId);

        $openId = $this->parseOpenWith($openWith);
        // On a scoped storefront only the shop's own thread may be injected via
        // the deep-link hint, so it can't surface another vendor's thread.
        if ($openId !== null && ($storeSellerId === null || $openId === $storeSellerId)
            && !in_array($openId, $conversationIds, true)) {
            $conversationIds[] = $openId;
        }

        // `openRequested` keeps a message-less row alive: the store's own thread
        // and any deep-linked one must still render so the customer has
        // something to click. Without it InboxMessageList showed "no
        // conversations" while the right pane sat on a ready composer.
        $rows = collect($conversationIds)
            ->map(fn (int $conversationId) => $this->buildConversationRow(
                $customerId,
                $conversationId,
                openRequested: $conversationId === $storeSellerId || $conversationId === $openId,
            ))
            ->filter()
            ->values();

        if ($search) {
            $needle = mb_strtolower(trim($search));
            $rows = $rows->filter(fn (array $row) => str_contains(mb_strtolower($row['conversation']->name), $needle));
        }

        $sorted = $this->sortByRecency($rows);

        // Contract shape is array{conversations, total} — the module and the
        // NullInboxProvider both return this; returning a bare list here made
        // the frontend read `.conversations` as undefined ("no conversation
        // found") even when the customer had threads.
        return [
            'conversations' => $sorted
                ->slice(max(0, ($offset - 1) * $limit), $limit)
                ->map(fn (array $row) => $row['conversation']->toArray())
                ->values()
                ->all(),
            'total'         => $sorted->count(),
        ];
    }

    public function conversation(int $customerId, ?int $storeId, int $conversationId, int $limit = 30, int $offset = 1): ?array
    {
        // On a vendor storefront the only reachable thread is with this shop's
        // vendor; reject any other seller id (e.g. a stale/forged X-Inbox-Cid).
        $storeSellerId = $this->storeSellerId($storeId);
        if ($storeSellerId !== null && $conversationId !== $storeSellerId) {
            return null;
        }

        $total = $this->threadQuery($customerId, $conversationId)->count();
        if ($total === 0 && !Shop::where('seller_id', $conversationId)->exists()) {
            return null;
        }

        // Mark inbound messages seen before the summary is built so the header
        // and the list row agree on a zero unread count for the open thread.
        $this->threadQuery($customerId, $conversationId)
            ->where('sent_by_customer', 0)
            ->where('seen_by_customer', 0)
            ->update(['seen_by_customer' => 1]);

        $page = max(1, $offset);
        // Page 1 is the newest slice — a long thread must not ship in full — and
        // is then reversed so the pane renders oldest-first like every chat UI.
        $messages = $this->threadQuery($customerId, $conversationId)
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->forPage($page, $limit)
            ->get()
            ->reverse()
            ->map(fn (Chatting $chat) => $this->mapMessage($chat)->toArray())
            ->values()
            ->all();

        $row = $this->buildConversationRow($customerId, $conversationId, openRequested: true);
        if ($row === null) {
            return null;
        }

        // ConversationDTO is module-owned and models the summary only —
        // `messages` / `pagination` are not fields on it, so passing them
        // through `fromArray()` silently drops them (which is what left the
        // pane empty). The frontend reads the active conversation flat, so the
        // thread is merged onto the DTO's wire shape here, in the host adapter.
        return array_merge($row['conversation']->toArray(), [
            'messages'   => $messages,
            'pagination' => [
                'page'    => $page,
                'perPage' => $limit,
                'total'   => $total,
                'hasMore' => $page * $limit < $total,
            ],
        ]);
    }

    public function sendMessage(int $customerId, ?int $storeId, array $input, ?array $files = null): array
    {
        $conversationId = $this->resolveRecipientConversationId($input, $storeId);
        if ($conversationId === null) {
            return ['error' => translate('Invalid_conversation') ?: 'Invalid conversation.'];
        }

        $message = trim((string) ($input['message'] ?? ''));
        $attachments = $this->uploadAttachments($files ?? []);
        if ($message === '' && $attachments === []) {
            return ['error' => translate('Write_a_message_or_attach_a_file') ?: 'Write a message or attach a file.'];
        }

        // Column layout mirrors RestAPI/v1/ChatController::send so a storefront
        // message lands in the same inbox the vendor panel / admin panel read.
        // Writing the in-house thread as `seller_id = 0` produced an orphan row:
        // the admin queries `admin_id = 0`, so the message was never delivered.
        $isAdminThread = $conversationId === self::ADMIN_CONVERSATION_ID;
        $shop = Shop::where('seller_id', $conversationId)->first();

        $chat = Chatting::create([
            'user_id'               => $customerId,
            'seller_id'             => $isAdminThread ? null : $conversationId,
            'admin_id'              => $isAdminThread ? self::ADMIN_CONVERSATION_ID : null,
            'shop_id'               => $shop?->id,
            'message'               => $message,
            'attachment'            => json_encode($attachments),
            'sent_by_customer'      => 1,
            'seen_by_customer'      => 1,
            'seen_by_seller'        => 0,
            'seen_by_admin'         => $isAdminThread ? 0 : null,
            'notification_receiver' => $isAdminThread ? 'admin' : 'seller',
            'created_at'            => now(),
        ]);

        // Notify the vendor's app/panel of the new message. The storefront only
        // creates the Chatting row, so without firing this event (which the
        // legacy web/API chat send also fires) the seller's Firebase push never
        // goes out — ChattingListener → chattingNotification reads the vendor's
        // cm_firebase_token. The admin thread fires nothing, matching legacy.
        $vendor = $isAdminThread ? null : Seller::with('shop')->find($conversationId);
        $customer = User::find($customerId);
        if ($vendor && $customer) {
            event(new ChattingEvent(
                key: 'message_from_customer',
                type: 'seller',
                userData: $vendor,
                messageForm: $customer,
            ));
        }

        return ['conversationId' => $conversationId, 'status' => (bool) $chat->exists];
    }

    public function mostRecentConversationId(int $customerId, ?int $storeId, ?string $openWith = null): ?int
    {
        $openId = $this->parseOpenWith($openWith);
        if ($openId !== null) {
            return $openId;
        }

        $storeSellerId = $this->storeSellerId($storeId);
        if ($storeSellerId !== null) {
            return $storeSellerId;
        }

        // Unscoped storefront: whichever thread the customer touched last.
        // Delivery-man rows are excluded — that surface is disabled here, so
        // defaulting the pane to one would render an unreachable conversation.
        $latest = Chatting::query()
            ->where('user_id', $customerId)
            ->whereNull('delivery_man_id')
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->first();

        if (!$latest) {
            return null;
        }

        return $latest->seller_id !== null
            ? (int) $latest->seller_id
            : ($latest->admin_id !== null ? self::ADMIN_CONVERSATION_ID : null);
    }

    /**
     * The vendor (seller_id) that owns the given storefront shop, or null when
     * there is no shop scope (global marketplace) so the inbox is unrestricted.
     * May be 0 — the in-house/admin thread — which is a real scope, hence the
     * null (not falsy) checks at the call sites.
     */
    private function storeSellerId(?int $storeId): ?int
    {
        if (!$storeId) {
            return null;
        }

        $sellerId = Shop::where('id', $storeId)->value('seller_id');

        return $sellerId === null ? null : (int) $sellerId;
    }

    /**
     * The thread an outgoing message belongs to, scope-guarded against the
     * storefront's own shop so a forged conversation/receiver id cannot open a
     * thread with another vendor. Null when nothing valid resolves.
     *
     * `0` is a legitimate result (the in-house store), so this cannot fall back
     * to truthiness the way a plain seller-id lookup would.
     */
    private function resolveRecipientConversationId(array $input, ?int $storeId): ?int
    {
        $storeSellerId = $this->storeSellerId($storeId);

        $candidate = $this->intOrNull($input['conversationId'] ?? null);
        if ($candidate === null && ($input['receiverType'] ?? null) === 'vendor') {
            $candidate = $this->intOrNull($input['receiverId'] ?? null);
        }
        $candidate ??= $storeSellerId;

        if ($candidate === null || $candidate < 0) {
            return null;
        }
        if ($storeSellerId !== null && $candidate !== $storeSellerId) {
            return null;
        }

        return $candidate;
    }

    private function intOrNull(mixed $value): ?int
    {
        return is_numeric($value) ? (int) $value : null;
    }

    /**
     * Conversation ids the customer can reach from this storefront. On the
     * in-house storefront that is only the admin thread; on a vendor storefront
     * only that vendor; unscoped, every seller the customer has messaged plus
     * the admin thread when one exists.
     *
     * @return int[]
     */
    private function reachableConversationIds(int $customerId, ?int $storeSellerId): array
    {
        if ($storeSellerId === self::ADMIN_CONVERSATION_ID) {
            return [self::ADMIN_CONVERSATION_ID];
        }

        $conversationIds = Chatting::query()
            ->where('user_id', $customerId)
            ->whereNotNull('seller_id')
            ->when($storeSellerId !== null, fn ($query) => $query->where('seller_id', $storeSellerId))
            ->select('seller_id')
            ->distinct()
            ->pluck('seller_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        if ($storeSellerId === null && $this->threadQuery($customerId, self::ADMIN_CONVERSATION_ID)->exists()) {
            $conversationIds[] = self::ADMIN_CONVERSATION_ID;
        }

        // The storefront's own thread is always reachable, message history or
        // not — the caller lists it as a synthetic row so a customer who has
        // never written has something to click.
        if ($storeSellerId !== null && !in_array($storeSellerId, $conversationIds, true)) {
            $conversationIds[] = $storeSellerId;
        }

        return $conversationIds;
    }

    /**
     * Resolve the `openWith` deep-link hint the storefront sends from the order
     * details page. Only two values are ever emitted by the module
     * (ProfileOrderDetails.jsx):
     *
     *   'vendor'  no-op — the store thread is always listed and is already the
     *             default active conversation, so there is nothing to inject.
     *   'dm:<id>' delivery-man chat, switched off for 6Valley
     *             (capabilities.features.deliveryManChat). Ignored rather than
     *             opening a thread the customer has no entry point to.
     *
     * A bare numeric id is still accepted so a hand-built vendor deep link works.
     */
    private function parseOpenWith(?string $openWith): ?int
    {
        if ($openWith === null || $openWith === '' || $openWith === 'vendor' || \str_starts_with($openWith, 'dm:')) {
            return null;
        }

        return is_numeric($openWith) ? (int) $openWith : null;
    }

    private function threadQuery(int $customerId, int $conversationId): Builder
    {
        $query = Chatting::query()->where('user_id', $customerId);

        // The in-house store thread is keyed off admin_id with a NULL seller_id,
        // so `seller_id = 0` matches none of its rows — reading it that way is
        // what left the in-house storefront's inbox permanently empty.
        return $conversationId === self::ADMIN_CONVERSATION_ID
            ? $query->whereNull('seller_id')->where('admin_id', self::ADMIN_CONVERSATION_ID)
            : $query->where('seller_id', $conversationId);
    }

    /**
     * Conversation DTO plus the raw activity timestamp used for ordering. The
     * wire shape carries only the formatted `lastTime`, which sorts
     * lexicographically ("09:41PM, 01 Feb 25" ahead of "10:02AM, 03 Mar 26") —
     * keeping the Carbon alongside it is what makes the list order by real
     * recency.
     *
     * @return array{conversation: ConversationDTO, lastActivityAt: ?Carbon}|null
     */
    private function buildConversationRow(int $customerId, int $conversationId, bool $openRequested): ?array
    {
        $latest = $this->threadQuery($customerId, $conversationId)
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->first();

        if (!$latest && !$openRequested) {
            return null;
        }

        // Conversation id 0 resolves the in-house shop row (seller_id 0), so the
        // admin thread gets the store's own name and logo rather than a
        // placeholder.
        $shop = Shop::where('seller_id', $conversationId)->first();
        $name = $shop?->name ?? translate('Shop');

        $unread = $this->threadQuery($customerId, $conversationId)
            ->where('sent_by_customer', 0)
            ->where('seen_by_customer', 0)
            ->count();

        $lastActivityAt = $latest?->created_at ? Carbon::parse($latest->created_at) : null;

        $summary = [
            'id'          => $conversationId,
            // InboxMessageList buckets rows by `type` and renders only the
            // 'vendor' and 'delivery' sections — anything else is dropped from
            // the list. Every 6Valley thread here is a store thread.
            'type'        => 'vendor',
            'name'        => $name,
            'avatar'      => $shop && !empty($shop->image) ? getStorageImages(path: $shop->image_full_url, type: 'shop') : null,
            'initials'    => $this->initials($name),
            'unread'      => $unread,
            'lastMessage' => $this->lastMessagePreview($latest),
            'lastTime'    => $lastActivityAt?->format('h:iA, d M y') ?? '',
        ];

        // `pristine` marks the synthetic row injected for a vendor the customer
        // has never messaged. The key must be absent (not null) on real threads —
        // ConversationDTO::fromArray keys off array_key_exists, so an explicit
        // null would be cast to `false` and shipped on every row.
        if (!$latest) {
            $summary['pristine'] = true;
        }

        return [
            'conversation'   => ConversationDTO::fromArray($summary),
            'lastActivityAt' => $lastActivityAt,
        ];
    }

    /**
     * @param Collection<int,array{conversation: ConversationDTO, lastActivityAt: ?Carbon}> $rows
     * @return Collection<int,array{conversation: ConversationDTO, lastActivityAt: ?Carbon}>
     */
    private function sortByRecency(Collection $rows): Collection
    {
        return $rows
            ->sortByDesc(fn (array $row) => $row['lastActivityAt']?->getTimestamp() ?? 0)
            ->values();
    }

    private function lastMessagePreview(?Chatting $latest): string
    {
        if (!$latest) {
            return '';
        }

        $message = trim((string) ($latest->message ?? ''));
        if ($message !== '') {
            return $message;
        }

        return $latest->attachment_full_url ? self::ATTACHMENT_PREVIEW : '';
    }

    private function mapMessage(Chatting $chat): MessageDTO
    {
        // The module renders each attachment as { url, name, type }: images →
        // thumbnail + lightbox, everything else (pdf, zip, video, documents) →
        // a file chip. Type is derived from the stored file extension so mixed
        // attachments render correctly.
        $attachments = collect($chat->attachment_full_url ?? [])
            ->map(fn ($image) => getStorageImages(path: $image, type: 'backend-basic'))
            ->filter()
            ->map(function (string $url) {
                $name = basename(parse_url($url, PHP_URL_PATH) ?: $url);
                $extension = '.' . strtolower(pathinfo($name, PATHINFO_EXTENSION));
                return [
                    'url'  => $url,
                    'name' => $name,
                    'type' => in_array($extension, GlobalConstant::IMAGE_EXTENSION, true) ? 'image' : 'file',
                ];
            })
            ->values()
            ->all();

        return MessageDTO::fromArray([
            'id'          => (int) $chat->id,
            // The storefront viewer is always the customer, so their own
            // messages are 'me' (right-aligned) and vendor/admin replies are
            // 'them' (left). The module keys bubble alignment off this exact
            // pair; emitting customer/vendor/admin pushed every bubble left.
            'sender'      => $chat->sent_by_customer ? 'me' : 'them',
            'text'        => $chat->message !== null ? (string) $chat->message : null,
            'attachments' => $attachments,
            'time'        => $chat->created_at ? Carbon::parse($chat->created_at)->format('h:iA') : '',
            'createdAt'   => $chat->created_at ? Carbon::parse($chat->created_at)->toIso8601String() : null,
            'order'       => null,
        ]);
    }

    private function uploadAttachments(array $files): array
    {
        $storage = getWebConfig(name: 'storage_connection_type') ?? 'public';
        $out = [];
        foreach ($files as $file) {
            if (!$file) {
                continue;
            }
            $extension = '.' . strtolower((string) $file->getClientOriginalExtension());
            try {
                // Images are re-encoded to webp; every other allowed type (pdf,
                // zip, video, documents) is stored as-is via fileUpload(), which
                // does a raw put with no image processing. Mirrors the legacy
                // ChattingService::getAttachment() branch so a non-image is no
                // longer silently dropped by Intervention's image reader.
                $fileName = in_array($extension, GlobalConstant::IMAGE_EXTENSION, true)
                    ? $this->upload(dir: 'chatting/', format: 'webp', image: $file)
                    : $this->fileUpload(dir: 'chatting/', format: $file->getClientOriginalExtension(), file: $file);
                $out[] = ['file_name' => $fileName, 'storage' => $storage];
            } catch (\Throwable) {
            }
        }
        return $out;
    }

    private function initials(string $name): string
    {
        $parts = preg_split('/\s+/', trim($name)) ?: [];
        $initials = '';
        foreach (array_slice($parts, 0, 2) as $part) {
            $initials .= mb_strtoupper(mb_substr($part, 0, 1));
        }
        return $initials !== '' ? $initials : 'S';
    }
}
