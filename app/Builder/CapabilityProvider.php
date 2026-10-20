<?php

namespace App\Builder;

use Modules\Builder\Contracts\CapabilityProvider as CapabilityProviderContract;
use Modules\Builder\ValueObjects\HostCapabilities;
use Modules\Builder\ValueObjects\StorefrontScope;

/**
 * 6Valley capability manifest adapter.
 *
 * Passes through the host-owned `config/builder.php` capabilities block and
 * forces the wallet-family flags to mirror the legacy `wallet_features_enabled`
 * master switch so the storefront can't drift from the operator's setting.
 * Additional data-driven flags get merged UNDER the config in later phases —
 * `array_replace_recursive($derived, $config)` — so an explicit config value
 * always wins.
 */
class CapabilityProvider implements CapabilityProviderContract
{
    public function capabilities(?StorefrontScope $scope): HostCapabilities
    {
        $config = (array) config('builder.capabilities', []);

        $walletOn = (bool) config('builder.wallet_features_enabled', true);
        $config['features']['wallet'] = $walletOn;
        $config['payment']['wallet']  = $walletOn;
        $config['payment']['partial'] = $walletOn;

        return HostCapabilities::fromArray($config);
    }
}
