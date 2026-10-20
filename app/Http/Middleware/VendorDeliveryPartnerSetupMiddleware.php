<?php

namespace App\Http\Middleware;

class VendorDeliveryPartnerSetupMiddleware extends DeliveryPartnerServiceMiddleware
{
    protected function isAvailable(): bool
    {
        return vendorDeliveryPartnerSetupAvailable();
    }

    protected function refusalMessage(): string
    {
        return translate('delivery_partner_setup_is_managed_by_the_admin_for_your_store');
    }
}
