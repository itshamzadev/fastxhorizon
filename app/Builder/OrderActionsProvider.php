<?php

namespace App\Builder;

use App\Events\RefundEvent;
use App\Models\Order;
use App\Models\OrderDetail;
use App\Models\OrderStatusHistory;
use App\Models\RefundRequest;
use App\Traits\PdfGenerator;
use App\Utils\ImageManager;
use App\Utils\OrderManager;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Modules\Builder\Contracts\OrderActionsProvider as OrderActionsProviderContract;
use Modules\Builder\ValueObjects\StorefrontScope;

/**
 * 6Valley host adapter for post-place-order customer actions.
 *
 * Mirrors the host Web\UserProfileController flows: cancellation is limited to
 * cash-on-delivery pending orders (with the same stock-restore + status-history
 * side effects), refunds are filed per order-detail into `refund_requests`, and
 * reorder/invoice reuse OrderManager::generateOrderAgain and the PdfGenerator
 * trait. 6Valley uses free-text cancel/refund reasons, so the reason lists are
 * empty. Digital re-payment (repay) is gated: it needs the host's payment-request
 * pipeline rather than a simple redirect builder.
 */
class OrderActionsProvider implements OrderActionsProviderContract
{
    use PdfGenerator;

    public function cancellationReasons(): array
    {
        // 6Valley captures the cancellation reason as free text.
        return [];
    }

    public function refundReasons(): array
    {
        // 6Valley captures the refund reason as free text.
        return [];
    }

    public function cancel(?StorefrontScope $scope, int $orderId, ?int $customerId, ?string $guestPhone, ?string $reason = null, ?string $note = null): array
    {
        $order = $this->ownedOrder($orderId, $customerId, $guestPhone);
        if (!$order) {
            return ['success' => false, 'error' => translate('Order_not_found') ?: 'Order not found.'];
        }

        if ($order->payment_method !== 'cash_on_delivery' || $order->order_status !== 'pending') {
            return ['success' => false, 'error' => translate('status_not_changable_now') ?: 'This order can no longer be cancelled.'];
        }

        try {
            OrderManager::getStockUpdateOnOrderStatusChange($order, 'canceled');
            OrderManager::removeOldStatusHistory(orderId: $orderId, orderStatus: 'canceled');
            $this->recordStatusHistory($orderId, $customerId, 'canceled');
            $order->cause = $note ?: $reason;
            $order->order_status = 'canceled';
            $order->save();
        } catch (\Throwable $exception) {
            Log::warning('Storefront order cancel failed', ['order_id' => $orderId, 'error' => $exception->getMessage()]);
            return ['success' => false, 'error' => translate('Could_not_cancel_the_order') ?: 'Could not cancel the order.'];
        }

        return ['success' => true, 'message' => translate('successfully_canceled') ?: 'Order cancelled.'];
    }

    public function requestRefund(?StorefrontScope $scope, int $orderId, int $customerId, string $customerReason, ?string $customerNote, array $imageFiles): array
    {
        $order = Order::where('id', $orderId)->where('customer_id', $customerId)->where('is_guest', 0)->first();
        if (!$order) {
            return ['success' => false, 'error' => translate('Order_not_found') ?: 'Order not found.'];
        }

        $details = OrderDetail::where('order_id', $orderId)
            ->where(function ($q) {
                $q->whereNull('refund_request')->orWhere('refund_request', 0);
            })
            ->get();

        if ($details->isEmpty()) {
            return ['success' => false, 'error' => translate('No_refundable_items_in_this_order') ?: 'No refundable items in this order.'];
        }

        $images = $this->uploadRefundImages($imageFiles);
        $reason = $customerNote ? ($customerReason . ' - ' . $customerNote) : $customerReason;

        try {
            foreach ($details as $orderDetail) {
                $refundRequest = new RefundRequest();
                $refundRequest->order_details_id = $orderDetail->id;
                $refundRequest->customer_id = $customerId;
                $refundRequest->status = 'pending';
                $refundRequest->amount = OrderManager::getRefundDetailsForSingleOrderDetails(orderDetailsId: $orderDetail->id)['total_refundable_amount'] ?? 0;
                $refundRequest->product_id = $orderDetail->product_id;
                $refundRequest->order_id = $orderId;
                $refundRequest->refund_reason = $reason;
                if ($images) {
                    $refundRequest->images = $images;
                }
                $refundRequest->save();

                $orderDetail->refund_request = 1;
                $orderDetail->save();

                event(new RefundEvent(status: 'refund_request', order: $order, refund: $refundRequest, orderDetails: $orderDetail));
            }
        } catch (\Throwable $exception) {
            Log::warning('Storefront refund request failed', ['order_id' => $orderId, 'error' => $exception->getMessage()]);
            return ['success' => false, 'error' => translate('Could_not_submit_the_refund_request') ?: 'Could not submit the refund request.'];
        }

        return ['success' => true, 'message' => translate('refund_requested_successful') ?: 'Refund requested.'];
    }

    public function switchToCod(?StorefrontScope $scope, int $orderId, ?int $customerId, ?string $guestPhone): array
    {
        $order = $this->ownedOrder($orderId, $customerId, $guestPhone);
        if (!$order) {
            return ['success' => false, 'error' => translate('Order_not_found') ?: 'Order not found.'];
        }

        $order->payment_method = 'cash_on_delivery';
        $order->order_status = 'pending';
        $order->save();

        return ['success' => true, 'message' => translate('Payment_method_updated') ?: 'Switched to cash on delivery.'];
    }

    public function repay(?StorefrontScope $scope, int $orderId, int $customerId, string $paymentMethod): array
    {
        // Digital re-payment needs the host payment-request pipeline; it is not
        // exposed as a simple redirect builder for the storefront yet.
        return ['success' => false, 'error' => translate('Online_repayment_is_currently_unavailable') ?: 'Online repayment is currently unavailable.'];
    }

    public function reorder(?StorefrontScope $scope, int $orderId, int $customerId): array
    {
        $order = Order::where('id', $orderId)->where('customer_id', $customerId)->where('is_guest', 0)->first();
        if (!$order) {
            return ['success' => false, 'error' => translate('Order_not_found') ?: 'Order not found.'];
        }

        $request = new Request();
        $request->merge(['order_id' => $orderId]);

        try {
            $data = OrderManager::generateOrderAgain($request);
        } catch (\Throwable $exception) {
            Log::warning('Storefront reorder failed', ['order_id' => $orderId, 'error' => $exception->getMessage()]);
            return ['success' => false, 'error' => translate('Could_not_reorder') ?: 'Could not reorder.'];
        }

        $added = (int) ($data['add_to_cart_count'] ?? 0);
        $errors = $data['errorMessages'] ?? [];

        return [
            'success' => $added > 0,
            'message' => $added > 0 ? (translate('added_to_cart_successfully') ?: 'Added to cart.') : (translate('Some_items_could_not_be_added') ?: 'Some items could not be added.'),
            'count'   => $added,
            'errors'  => array_values((array) $errors),
        ];
    }

    public function downloadInvoice(?StorefrontScope $scope, int $orderId, ?int $customerId, ?string $guestPhone = null): array
    {
        // Same ownership predicate as every other order action: signed-in
        // customer id, OR a guest whose contact phone matches the order — so a
        // guest can download their own invoice without being forced to log in.
        $order = $this->ownedOrder($orderId, $customerId, $guestPhone);
        if (!$order) {
            return ['success' => false, 'error' => translate('Order_not_found') ?: 'Order not found.'];
        }

        $order->load(['seller', 'shipping', 'latestEditHistory']);

        try {
            // The legacy invoice blade reads session('currency_code') directly
            // (getCurrencyCode('web'), a `: string` return) on its very first
            // line. The storefront only sets that key when a shopper switches
            // currency, so on single-currency 6valley it's often unset and the
            // render throws a TypeError. Prime the currency session first — same
            // initialisation the legacy web request already carries.
            loadCurrency();
            $invoiceSettings = getWebConfig(name: 'invoice_settings');
            $view = \View::make(VIEW_FILE_NAMES['order_invoice'], compact('order', 'invoiceSettings'));
            $this->generatePdf(view: $view, filePrefix: 'order_invoice_', filePostfix: $order->id, pdfType: 'invoice', requestFrom: 'web');
        } catch (\Throwable $exception) {
            Log::warning('Storefront invoice generation failed', ['order_id' => $orderId, 'error' => $exception->getMessage()]);
            return ['success' => false, 'error' => translate('Could_not_generate_the_invoice') ?: 'Could not generate the invoice.'];
        }

        return ['success' => true];
    }

    /**
     * Storefront digital-product download. Mirrors the legacy
     * WebController::getDigitalProductDownload but keys ownership off the
     * storefront shopper (customer id / guest phone) instead of the
     * auth('customer') guard, and resolves the file from the order-line
     * snapshot so it survives the product being deactivated/deleted.
     */
    public function downloadDigitalProduct(?StorefrontScope $scope, int $orderDetailId, ?int $customerId, ?string $guestPhone): array
    {
        $detail = OrderDetail::with('order')->find($orderDetailId);
        if (!$detail || !$detail->order) {
            return ['success' => false, 'error' => translate('order_Not_Found') ?: 'Order not found.'];
        }

        // Reuse the order-ownership predicate (customer id, or guest phone
        // matched against the order's stored delivery contact).
        if (!$this->ownedOrder((int) $detail->order_id, $customerId, $guestPhone)) {
            return ['success' => false, 'error' => translate('order_Not_Found') ?: 'Order not found.'];
        }

        if ($detail->order->payment_status !== 'paid') {
            return ['success' => false, 'error' => translate('Payment_must_be_confirmed_first') ?: 'Payment must be confirmed first.'];
        }

        $snapshot = json_decode($detail->product_details ?? '[]', true) ?: [];
        $type = $snapshot['digital_product_type'] ?? null;

        if ($type === 'ready_product' && !empty($snapshot['digital_file_ready'])) {
            $file = storageLink('product/digital-product', $snapshot['digital_file_ready'], $snapshot['storage_path'] ?? 'public');
            $fileName = $snapshot['digital_file_ready'];
        } else {
            $file = $detail->digital_file_after_sell_full_url;
            $fileName = $detail->digital_file_after_sell;
        }

        $filePath = is_array($file) ? ($file['path'] ?? null) : null;
        $fileExists = is_array($file) && ($file['status'] ?? 0) === 200;

        if (empty($fileName) || !$fileExists) {
            return ['success' => false, 'error' => translate('file_not_found') ?: 'File not found.'];
        }

        return ['success' => true, 'file_path' => $filePath, 'file_name' => $fileName];
    }

    private function ownedOrder(int $orderId, ?int $customerId, ?string $guestPhone): ?Order
    {
        $query = Order::where('id', $orderId);

        if ($customerId) {
            $query->where('customer_id', $customerId)->where('is_guest', 0);
        } elseif ($guestPhone) {
            $phone = trim($guestPhone);
            $query->where('is_guest', 1)->where(function ($inner) use ($phone) {
                $inner->whereJsonContains('shipping_address_data->phone', $phone)
                    ->orWhereJsonContains('shipping_address_data->contact_person_number', $phone);
            });
        } else {
            return null;
        }

        return $query->first();
    }

    private function recordStatusHistory(int $orderId, ?int $customerId, string $status): void
    {
        try {
            OrderStatusHistory::create([
                'order_id'   => $orderId,
                'updated_by' => $customerId,
                'user_type'  => 'customer',
                'status'     => $status,
            ]);
        } catch (\Throwable) {
        }
    }

    private function uploadRefundImages(array $imageFiles): array
    {
        $storage = getWebConfig(name: 'storage_connection_type') ?? 'public';
        $images = [];
        foreach ($imageFiles as $file) {
            if (!$file) {
                continue;
            }
            try {
                $images[] = ['image_name' => ImageManager::upload('refund/', 'webp', $file), 'storage' => $storage];
            } catch (\Throwable $exception) {
                Log::warning('Refund image upload failed', ['error' => $exception->getMessage()]);
            }
        }
        return $images;
    }
}
