<?php

namespace Modules\AI\app\Http\Controllers\API\V1;

use App\Traits\StorageTrait;
use App\Utils\Helpers;
use App\Utils\ImageManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Modules\AI\app\Contracts\ShoppingAssistantInterface;
use Modules\AI\app\Http\Requests\ShoppingAssistant\SendMessageRequest;
use Modules\AI\app\Http\Requests\ShoppingAssistant\StartSessionRequest;
use Modules\AI\app\Services\ShoppingAssistant\CartSelectionContext;

class ShoppingAssistantController extends Controller
{
    use StorageTrait;

    public function __construct(
        private readonly ShoppingAssistantInterface $assistant,
        private readonly CartSelectionContext       $cartContext,
    ) {}

    public function startSession(StartSessionRequest $request): JsonResponse
    {
        [$customerId, $guestId] = $this->resolveIdentity($request);

        // Guest MUST send the app-wide guest id (from /get-guest-id) that /cart* keys on, else the cart is siloed.
        if (!$customerId && !$guestId) {
            return response()->json([
                'status'  => false,
                'message' => translate('A_guest_id_is_required_get_one_from_get-guest-id_and_send_it_with_every_request'),
            ], 422);
        }

        $session = $this->assistant->startSession(
            customerId: $customerId,
            guestId:    $guestId,
            locale:     app()->getLocale(),
        );

        return response()->json([
            'status'   => true,
            'session'  => $session,
            'guest_id' => $customerId ? null : $guestId,
        ], 201);
    }

    public function listSessions(Request $request): JsonResponse
    {
        [$customerId, $guestId] = $this->resolveIdentity($request);

        $sessions = $this->assistant->listSessions(
            customerId: $customerId,
            guestId:    $guestId,
        );

        return response()->json([
            'status'   => true,
            'sessions' => $sessions,
        ]);
    }

    public function getSession(Request $request, int $id): JsonResponse
    {
        [$customerId, $guestId] = $this->resolveIdentity($request);

        $session = $this->assistant->getSession(
            sessionId:  $id,
            customerId: $customerId,
            guestId:    $guestId,
        );

        if (!$session) {
            return response()->json(['status' => false, 'message' => translate('Session_not_found')], 404);
        }

        $session->load('messages');

        return response()->json(['status' => true, 'session' => $session]);
    }

    public function deleteSession(Request $request, int $id): JsonResponse
    {
        [$customerId, $guestId] = $this->resolveIdentity($request);

        $deleted = $this->assistant->deleteSession(
            sessionId:  $id,
            customerId: $customerId,
            guestId:    $guestId,
        );

        if (!$deleted) {
            return response()->json(['status' => false, 'message' => translate('Session_not_found')], 404);
        }

        return response()->json(['status' => true, 'message' => translate('Session_deleted')]);
    }

    public function uploadImage(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'image' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ], [
            'image.mimes' => translate('Only_JPG_JPEG_PNG_or_WEBP_images_are_allowed'),
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => Helpers::validationErrorProcessor($validator)], 422);
        }

        if (in_array($request->ip(), ['127.0.0.1', '::1'])) {
            $url = env('AI_DEV_SAMPLE_IMAGE_URL', 'https://images.unsplash.com/photo-1521572163474-6864f9cf17ab?w=600');

            return response()->json(['status' => true, 'url' => $url]);
        }

        $storageType = getWebConfig(name: 'storage_connection_type') ?? 'public';
        $fileName    = ImageManager::upload(dir: 'ai/shopping-assistant/', format: 'webp', image: $request->file('image'));

        return response()->json([
            'status' => true,
            'url'    => $this->storageLink('ai/shopping-assistant', $fileName, $storageType)['path'],
        ]);
    }

    public function sendMessage(SendMessageRequest $request, int $id): JsonResponse
    {
        [$customerId, $guestId] = $this->resolveIdentity($request);

        if (!$this->assistant->getSession(sessionId: $id, customerId: $customerId, guestId: $guestId)) {
            return response()->json(['status' => false, 'message' => translate('Session_not_found')], 404);
        }
        // Authenticated customer owns the cart (ignore stale guest_id); a guest falls back to session('guest_id'), else the API-passed id.
        $customer = auth('api')->user();

        $this->cartContext->set(
            customer:   $customer,
            guestId:    $customer ? null : (session('guest_id') ?? $guestId),
            selections: $request->input('cart_selections') ?? $request->input('cart_selection'),
        );

        $result = $this->assistant->sendMessage(
            sessionId: $id,
            content:   $request->input('message'),
            imageUrl:  $request->input('image_url'),
        );
        return response()->json([
            'status'   => true,
            'reply'    => $result['reply'],
            'intent'   => $result['intent'],
            'products' => $result['products'],
            'actions'  => $result['actions'] ?? [],
        ]);
    }

    private function resolveIdentity(Request $request): array
    {
        $customer   = auth('api')->user();
        $customerId = $customer?->id;
        $guestId    = $customer ? null : $request->input('guest_id');
        return [$customerId, $guestId];
    }
}
