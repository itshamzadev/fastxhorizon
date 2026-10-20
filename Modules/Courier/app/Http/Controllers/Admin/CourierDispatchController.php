<?php

namespace Modules\Courier\app\Http\Controllers\Admin;

use Devrabiul\ToastMagic\Facades\ToastMagic;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controller;
use Modules\Courier\app\Exceptions\CourierException;
use Modules\Courier\app\Http\Concerns\PresentsTrackingEvents;
use Modules\Courier\app\Http\Requests\Admin\EstimateCourierChargeRequest;
use Modules\Courier\app\Http\Requests\Admin\ReviseCourierDetailsRequest;
use Modules\Courier\app\Http\Requests\Admin\SendToCourierRequest;
use Modules\Courier\app\Services\CourierService;

class CourierDispatchController extends Controller
{
    use PresentsTrackingEvents;

    public function __construct(private readonly CourierService $courier) {}

    public function store(SendToCourierRequest $request): RedirectResponse
    {
        try {
            $shipment = $this->courier->ship(
                $request->toOrderData(),
                $request->selectedProvider(),
                $request->replacesExistingShipment(),
            );
        } catch (CourierException $exception) {
            ToastMagic::error($exception->getMessage());

            return back();
        }

        ToastMagic::success(translate('Sent_to_courier').'. '.translate('Consignment').': '.$shipment->consignmentId);

        return back();
    }

    public function revise(ReviseCourierDetailsRequest $request): RedirectResponse
    {
        try {
            $this->courier->reviseDispatchDetails($request->toOrderData());
        } catch (CourierException $exception) {
            ToastMagic::error($exception->getMessage());

            return back();
        }

        ToastMagic::success(translate('Delivery_information_updated'));

        return back();
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
        try {
            $status = $this->presentShipmentStatus($this->courier->refreshStatus($consignmentId));
            $events = $this->courier->track($consignmentId);
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
