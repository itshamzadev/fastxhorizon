<?php

namespace Modules\Courier\app\Services;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Modules\Courier\app\Contracts\CourierShipmentAccessResolver;
use Modules\Courier\app\DataTransferObjects\Requests\OrderData;
use Modules\Courier\app\DataTransferObjects\Requests\QuoteData;
use Modules\Courier\app\DataTransferObjects\Responses\CodInfo;
use Modules\Courier\app\DataTransferObjects\Responses\QuoteResult;
use Modules\Courier\app\DataTransferObjects\Responses\ShipmentResult;
use Modules\Courier\app\DataTransferObjects\Responses\WebhookEvent;
use Modules\Courier\app\Enums\ShipmentStatus;
use Modules\Courier\app\Events\ShipmentDispatched;
use Modules\Courier\app\Events\ShipmentStatusUpdated;
use Modules\Courier\app\Exceptions\CourierException;
use Modules\Courier\app\Exceptions\UnsupportedCourierOperationException;
use Modules\Courier\app\Models\CourierShipment;
use Modules\Courier\app\Models\CourierWebhookLog;
use Modules\Courier\app\ValueObjects\CourierOwner;
use Modules\Courier\CourierProviders\Contracts\CalculatesPrice;
use Modules\Courier\CourierProviders\Contracts\CancelsOrders;
use Modules\Courier\CourierProviders\Contracts\CollectsCod;
use Modules\Courier\CourierProviders\Contracts\CreatesOrders;
use Modules\Courier\CourierProviders\Contracts\EstimatesDeliveryCharge;
use Modules\Courier\CourierProviders\Contracts\HandlesBatchWebhooks;
use Modules\Courier\CourierProviders\Contracts\HandlesWebhooks;
use Modules\Courier\CourierProviders\Contracts\ProvidesOrderInfo;
use Modules\Courier\CourierProviders\Contracts\TracksOrders;
use Modules\Courier\CourierProviders\Contracts\UpdatesOrders;
use Modules\Courier\CourierProviders\CourierProvider;

class CourierService
{
    public function __construct(
        private readonly ProviderRegistry $providers,
        private readonly CourierShipmentAccessResolver $shipmentAccess,
    ) {}

    public function isEnabled(): bool
    {
        return $this->providers->enabled() !== [];
    }

    public function shipmentFor(string $hostOrderReference): ?CourierShipment
    {
        return CourierShipment::query()
            ->forHostReference($hostOrderReference)
            ->active()
            ->latest()
            ->first();
    }

    public function activeShipmentForOwner(string $hostOrderReference, ?CourierOwner $owner = null): ?CourierShipment
    {
        return CourierShipment::query()
            ->forHostReference($hostOrderReference)
            ->forOwner($owner ?? $this->providers->owner())
            ->active()
            ->latest()
            ->first();
    }

    public function ship(OrderData $order, ?string $provider = null, bool $replaceExisting = false): ShipmentResult
    {
        if ($provider === null) {
            $provider = $this->providers->defaultProvider();
        }

        if ($provider === null || !$this->providers->isProviderEnabled($provider)) {
            throw new CourierException('The selected courier provider is not enabled.');
        }

        $driver = $this->providers->driver($provider);

        if (!$driver instanceof CreatesOrders) {
            throw UnsupportedCourierOperationException::for($provider, 'create orders');
        }

        $superseded = $this->shipmentToSupersede($order->hostOrderReference, $provider, $driver->owner(), $replaceExisting);

        $result = $driver->createOrder($order);

        $hostOrderReference = $result->hostOrderReference;

        if ($hostOrderReference === null) {
            $hostOrderReference = $order->hostOrderReference;
        }

        DB::transaction(function () use ($superseded, $driver, $provider, $result, $hostOrderReference, $order): void {
            $this->guardConsignmentIsUnrecorded($driver, $result->consignmentId, $hostOrderReference);

            $superseded?->supersede();

            CourierShipment::create([
                'owner_type'           => $driver->owner()->type,
                'owner_id'             => $driver->owner()->id,
                'provider'             => $provider,
                'consignment_id'       => $result->consignmentId,
                'host_order_reference' => $hostOrderReference,
                'tracking_code'        => $result->trackingCode,
                'status'               => $result->status,
                'delivery_fee'         => $result->deliveryFee,
                'cod_amount'           => $result->codAmount ?? $order->codAmount,
                'payload'              => $result->raw,
                'dispatch_details'     => $this->detailsOf($order),
            ]);
        });

        ShipmentDispatched::dispatch(
            $hostOrderReference,
            $provider,
            $this->providers->labelFor($provider),
            $result->consignmentId,
            $result->trackingCode,
        );

        return $result;
    }

    private function shipmentToSupersede(string $hostOrderReference, string $provider, CourierOwner $owner, bool $replaceExisting): ?CourierShipment
    {
        $existing = CourierShipment::query()
            ->forHostReference($hostOrderReference)
            ->active()
            ->latest()
            ->first();

        if (!$replaceExisting) {
            if ($existing !== null) {
                throw new CourierException('This order has already been sent to a courier.');
            }

            return null;
        }

        if ($existing === null) {
            throw new CourierException('This order is not assigned to a courier, so there is nothing to switch.');
        }

        if (!$existing->owner()->equals($owner)) {
            throw new CourierException('This order is assigned to a courier account you do not have access to.');
        }

        if ($existing->provider === $provider) {
            throw new CourierException('This order is already assigned to the selected delivery partner.');
        }

        return $existing;
    }

    private function guardConsignmentIsUnrecorded(CourierProvider $driver, string $consignmentId, ?string $hostOrderReference): void
    {
        // Sandbox endpoints hand out canned consignment ids — the same string comes
        // back for every booking — so the real-world "one id, one parcel" rule can
        // only be enforced against live credentials.
        if ($driver->environment() === CourierProvider::ENVIRONMENT_SANDBOX) {
            return;
        }

        $provider = $driver->getName();

        $recordedElsewhere = CourierShipment::query()
            ->forConsignment($provider, $consignmentId)
            ->where(function ($query) use ($hostOrderReference): void {
                $query->whereNull('host_order_reference')
                    ->orWhere('host_order_reference', '!=', $hostOrderReference);
            })
            ->exists();

        if ($recordedElsewhere) {
            throw new CourierException(sprintf(
                '%s returned consignment %s, which is already recorded against another order. Nothing was changed.',
                $this->providers->labelFor($provider),
                $consignmentId,
            ));
        }
    }

    public function reviseDispatchDetails(OrderData $order, ?CourierOwner $owner = null): CourierShipment
    {
        $shipment = $this->activeShipmentForOwner($order->hostOrderReference, $owner);

        if ($shipment === null) {
            throw new CourierException('This order is not assigned to a courier, so there is nothing to update.');
        }

        $shipment->update(['dispatch_details' => $this->detailsOf($order)]);

        return $shipment;
    }

    private function detailsOf(OrderData $order): array
    {
        $recipient = $order->recipient;

        return [
            'store_id'              => $order->providerStoreId,
            'recipient_name'        => $recipient->name,
            'recipient_phone'       => $recipient->phone,
            'recipient_address'     => $recipient->address,
            'city_id'               => $recipient->cityId,
            'zone_id'               => $recipient->zoneId,
            'area_id'               => $recipient->areaId,
            'country_code'          => $recipient->countryCode,
            'postal_code'           => $recipient->postalCode,
            'city_name'             => $recipient->cityName,
            'state_province'        => $recipient->stateProvince,
            'latitude'              => $recipient->latitude,
            'longitude'             => $recipient->longitude,
            'source_branch'         => $recipient->sourceBranchName,
            'source_branch_id'      => $recipient->sourceBranchId,
            'destination_branch'    => $recipient->destinationBranchName,
            'destination_branch_id' => $recipient->destinationBranchId,
            'weight'                => $order->weight,
            'quantity'              => $order->quantity,
            'cod_amount'            => $order->codAmount,
            'order_value'           => $order->meta['order_value'] ?? null,
            'item_description'      => $order->itemDescription,
            'delivery_type'         => $order->meta['delivery_type'] ?? null,
            'note'                  => $order->note,
        ];
    }

    public function summaryFor(string $hostOrderReference): ?array
    {
        $shipment = $this->shipmentFor($hostOrderReference);

        return $shipment === null ? null : $this->summaryOf($shipment);
    }

    public function shipmentDetailsFor(string $hostOrderReference): ?array
    {
        $shipment = $this->shipmentFor($hostOrderReference);

        if ($shipment === null) {
            return null;
        }

        $summary = $this->summaryOf($shipment);

        return [
            'provider'              => $shipment->provider,
            'delivery_partner'      => $summary['provider_label'],
            'consignment_id'        => $shipment->consignment_id,
            'tracking_code'         => $shipment->tracking_code,
            'tracking_number'       => $summary['tracking_number'],
            'shipment_status'       => $summary['status'],
            'shipment_status_label' => $summary['status_label'],
            'status_tone'           => $summary['status_tone'],
            'delivery_fee'          => $shipment->delivery_fee,
            'tracking_url'          => $summary['tracking_url'],
            'dispatched_at'         => optional($shipment->created_at)->format('d M Y, h:i A'),
            'can_track'             => $this->supportsTracking($shipment->provider, $shipment->owner()),
            'dispatch_details'      => $shipment->dispatch_details,
            'can_manage'            => $shipment->owner()->equals($this->providers->owner()),
        ];
    }

    private function summaryOf(CourierShipment $shipment): array
    {
        $status = $this->statusOf($shipment);
        $payload = is_array($shipment->payload) ? $shipment->payload : [];

        return [
            'provider'        => $shipment->provider,
            'provider_label'  => $this->providers->labelFor($shipment->provider),
            'tracking_number' => $shipment->tracking_code ?: $shipment->consignment_id,
            'status'          => $status->value,
            'status_label'    => $status->label(),
            'status_tone'     => $status->tone(),
            'tracking_url'    => $payload['shareLink'] ?? $payload['tracking_url'] ?? null,
        ];
    }

    private function statusOf(CourierShipment $shipment): ShipmentStatus
    {
        $status = $shipment->status;

        if ($status instanceof ShipmentStatus) {
            return $status;
        }

        return ShipmentStatus::tryFrom((string) $status) ?? ShipmentStatus::Unknown;
    }

    public function refreshStatus(string $consignmentId, ?CourierOwner $viewer = null): ?ShipmentStatus
    {
        [$driver, $shipment] = $this->resolveForConsignment($consignmentId, $viewer);

        if ($shipment === null) {
            return null;
        }

        $storedStatus = $this->statusOf($shipment);

        if (!$driver instanceof ProvidesOrderInfo) {
            return $storedStatus;
        }

        $result = rescue(fn (): ShipmentResult => $driver->getOrderStatus($consignmentId), report: false);

        if ($result === null || $result->status === ShipmentStatus::Unknown || $result->status === $storedStatus) {
            return $storedStatus;
        }

        $shipment->update(['status' => $result->status]);

        ShipmentStatusUpdated::dispatch($consignmentId, $result->status, $shipment->host_order_reference);

        return $result->status;
    }

    public function supportsTracking(string $provider, ?CourierOwner $owner = null): bool
    {
        $providers = $this->providers;

        if ($owner !== null) {
            $providers = $this->providers->forOwner($owner);
        }

        return $providers->isProviderEnabled($provider)
            && $providers->driver($provider) instanceof TracksOrders;
    }

    public function estimate(QuoteData $quote, string $provider): ?QuoteResult
    {
        if (!$this->providers->isProviderEnabled($provider)) {
            throw new CourierException('The selected courier provider is not enabled.');
        }

        $driver = $this->providers->driver($provider);

        if ($driver instanceof CalculatesPrice) {
            return $driver->calculatePrice($quote);
        }

        if ($driver instanceof EstimatesDeliveryCharge) {
            return $driver->getDeliveryCharges($quote);
        }

        return null;
    }

    public function handleWebhook(string $provider, Request $request, ?CourierOwner $owner = null): ?WebhookEvent
    {
        if ($owner === null) {
            $owner = CourierOwner::platform();
        }
        $driver = $this->providers->forOwner($owner)->driver($provider);

        if (!$driver instanceof HandlesWebhooks) {
            throw UnsupportedCourierOperationException::for($provider, 'handle webhooks');
        }

        $verified = $driver->verifyWebhook($request);

        CourierWebhookLog::create([
            'provider' => $provider,
            'verified' => $verified,
            'headers'  => $request->headers->all(),
            'payload'  => $request->all(),
        ]);

        if (!$verified) {
            return null;
        }

        if ($driver instanceof HandlesBatchWebhooks) {
            $events = $driver->parseWebhookEvents($request);

            foreach ($events as $event) {
                $this->applyWebhookEvent($provider, $event, $owner);
            }

            return $events[0] ?? null;
        }

        return $this->applyWebhookEvent($provider, $driver->parseWebhook($request), $owner);
    }

    private function applyWebhookEvent(string $provider, WebhookEvent $event, CourierOwner $owner): WebhookEvent
    {
        $shipment = $this->latestShipmentForConsignment(
            CourierShipment::query()->forOwner($owner)->forConsignment($provider, $event->consignmentId),
        );

        if ($shipment !== null) {
            $shipment->update(['status' => $event->status]);
            $shipment->trackingEvents()->create([
                'status'      => $event->status,
                'occurred_at' => $event->occurredAt,
                'payload'     => $event->raw,
            ]);
        }

        ShipmentStatusUpdated::dispatch(
            $event->consignmentId,
            $event->status,
            $event->hostOrderReference ?? $shipment?->host_order_reference,
        );

        return $event;
    }

    public function track(string $consignmentId, ?CourierOwner $viewer = null): array
    {
        [$driver, $shipment] = $this->resolveForConsignment($consignmentId, $viewer);

        if ($shipment === null) {
            throw new CourierException('No shipment was found for this consignment.');
        }

        if (!$driver instanceof TracksOrders) {
            throw UnsupportedCourierOperationException::for($driver->getName(), 'track orders');
        }

        return $driver->trackOrder($consignmentId);
    }

    public function cancel(string $consignmentId): ShipmentResult
    {
        [$driver, $shipment] = $this->resolveForConsignment($consignmentId);

        if (!$driver instanceof CancelsOrders) {
            throw UnsupportedCourierOperationException::for($driver->getName(), 'cancel orders');
        }

        $result = $driver->cancelOrder($consignmentId);
        $shipment?->update(['status' => $result->status]);

        return $result;
    }

    public function updateShipment(string $consignmentId, OrderData $data): ShipmentResult
    {
        [$driver, $shipment] = $this->resolveForConsignment($consignmentId);

        if (!$driver instanceof UpdatesOrders) {
            throw UnsupportedCourierOperationException::for($driver->getName(), 'update orders');
        }

        $result = $driver->updateOrder($consignmentId, $data);
        $shipment?->update(['status' => $result->status, 'payload' => $result->raw]);

        return $result;
    }

    public function codCollection(string $consignmentId): CodInfo
    {
        [$driver] = $this->resolveForConsignment($consignmentId);

        if (!$driver instanceof CollectsCod) {
            throw UnsupportedCourierOperationException::for($driver->getName(), 'COD collection');
        }

        return $driver->getCodCollection($consignmentId);
    }

    private function latestShipmentForConsignment($query): ?CourierShipment
    {
        return (clone $query)->active()->latest()->first() ?? $query->latest()->first();
    }

    private function resolveForConsignment(string $consignmentId, ?CourierOwner $viewer = null): array
    {
        $shipment = $this->readableShipment($consignmentId, $viewer);

        $providers = $this->providers;

        if ($shipment !== null) {
            $providers = $this->providers->forOwner($shipment->owner());
        }

        return [$providers->driver($shipment?->provider), $shipment];
    }

    private function readableShipment(string $consignmentId, ?CourierOwner $viewer): ?CourierShipment
    {
        $query = CourierShipment::query()->where('consignment_id', $consignmentId);

        if ($viewer === null) {
            return $this->latestShipmentForConsignment($query);
        }

        $ownShipment = $this->latestShipmentForConsignment((clone $query)->forOwner($viewer));

        if ($ownShipment !== null) {
            return $ownShipment;
        }

        return $this->latestReadableShipment((clone $query)->active(), $viewer)
            ?? $this->latestReadableShipment($query, $viewer);
    }

    private function latestReadableShipment(Builder $query, CourierOwner $viewer): ?CourierShipment
    {
        foreach ($query->latest()->get() as $shipment) {
            $isReadable = $this->shipmentAccess->allowsRead(
                $viewer,
                $shipment->owner(),
                $shipment->host_order_reference,
            );

            if ($isReadable) {
                return $shipment;
            }
        }

        return null;
    }
}
