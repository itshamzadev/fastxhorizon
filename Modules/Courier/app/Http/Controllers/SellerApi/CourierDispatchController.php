<?php

namespace Modules\Courier\app\Http\Controllers\SellerApi;

use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Modules\Courier\app\Exceptions\CourierException;
use Modules\Courier\app\Http\Concerns\PresentsTrackingEvents;
use Modules\Courier\app\Http\Requests\Admin\EstimateCourierChargeRequest;
use Modules\Courier\app\Http\Requests\Admin\ReviseCourierDetailsRequest;
use Modules\Courier\app\Http\Requests\Admin\SendToCourierRequest;
use Modules\Courier\app\Services\CourierService;
use Modules\Courier\app\Services\ProviderRegistry;

class CourierDispatchController extends Controller
{
    use PresentsTrackingEvents;

    public function __construct(
        private readonly CourierService $courier,
        private readonly ProviderRegistry $providers,
    ) {}

    public function store(SendToCourierRequest $request): JsonResponse
    {
        try {
            $shipment = $this->courier->ship(
                $request->toOrderData(),
                $request->selectedProvider(),
                $request->replacesExistingShipment(),
            );
        } catch (CourierException $exception) {
            return response()->json(['ok' => false, 'message' => $exception->getMessage()]);
        }

        return response()->json([
            'ok'               => true,
            'message'          => translate('Sent_to_courier').'. '.translate('Consignment').': '.$shipment->consignmentId,
            'courier_shipment' => $this->courier->shipmentDetailsFor(
                $shipment->hostOrderReference ?? (string) $request->input('host_order_reference'),
            ),
        ]);
    }

    public function revise(ReviseCourierDetailsRequest $request): JsonResponse
    {
        try {
            $shipment = $this->courier->reviseDispatchDetails($request->toOrderData());
        } catch (CourierException $exception) {
            return response()->json(['ok' => false, 'message' => $exception->getMessage()]);
        }

        return response()->json([
            'ok'               => true,
            'message'          => translate('Delivery_information_updated'),
            'courier_shipment' => $this->courier->shipmentDetailsFor($shipment->host_order_reference),
        ]);
    }

    public function estimate(EstimateCourierChargeRequest $request): JsonResponse
    {
        try {
            $quote = $this->courier->estimate($request->toQuoteData(), $request->selectedProvider());
        } catch (CourierException $exception) {
            return response()->json(['ok' => false, 'message' => $exception->getMessage()]);
        }

        if ($quote === null) {
            return response()->json(['ok' => true, 'supported' => false]);
        }

        return response()->json([
            'ok'        => true,
            'supported' => true,
            'total'     => $quote->totalCost,
            'currency'  => $quote->currency,
        ]);
    }

    public function track(string $consignmentId): JsonResponse
    {
        $owner = $this->providers->owner();

        try {
            $status = $this->presentShipmentStatus($this->courier->refreshStatus($consignmentId, $owner));
            $events = $this->courier->track($consignmentId, $owner);
        } catch (CourierException $exception) {
            return response()->json(['ok' => false, 'message' => $exception->getMessage()]);
        }

        return response()->json([
            'ok'     => true,
            'status' => $status,
            'events' => $this->presentTrackingEvents($events),
        ]);
    }
}
