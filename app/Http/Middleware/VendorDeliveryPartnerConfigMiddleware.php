<?php

namespace App\Http\Middleware;

class VendorDeliveryPartnerConfigMiddleware extends DeliveryPartnerConfigMiddleware
{
    protected function isAvailable(): bool
    {
        return vendorDeliveryPartnerConfigAvailable();
    }

    protected function refusalMessage(): string
    {
        return translate('delivery_partner_setup_is_managed_by_the_admin_for_your_store');
    }
}
