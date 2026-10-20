<?php

namespace Modules\Courier\app\Http\Requests\Admin;

use Closure;
use Modules\Courier\app\Services\CourierService;

class ReviseCourierDetailsRequest extends SendToCourierRequest
{
    public function rules(): array
    {
        return ['provider' => ['required', 'string', $this->providerMustHoldTheShipment()]] + parent::rules();
    }

    private function providerMustHoldTheShipment(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail): void {
            $shipment = app(CourierService::class)->activeShipmentForOwner((string) $this->input('host_order_reference'));

            if ($shipment === null) {
                $fail(translate('this_order_is_not_assigned_to_a_delivery_partner'));

                return;
            }

            if ($shipment->provider !== (string) $value) {
                $fail(translate('this_order_is_assigned_to_a_different_delivery_partner'));
            }
        };
    }
}
