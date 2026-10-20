<?php

namespace App\Http\Middleware;

class DeliveryPartnerConfigMiddleware extends DeliveryPartnerServiceMiddleware
{
    protected function isAvailable(): bool
    {
        return deliveryPartnerConfigAvailable();
    }

    protected function refusalMessage(): string
    {
        return translate('the_delivery_partner_addon_is_not_active');
    }
}
