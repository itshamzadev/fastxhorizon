<?php

namespace Modules\Courier\CourierProviders;

class PathaoNepalProvider extends PathaoProvider
{
    private const HOST_SANDBOX = 'https://courier-api-sandbox.pathao.com.np';
    private const HOST_LIVE = 'https://api-hermes.pathao.com.np';

    private const CURRENCY_NPR = 'NPR';

    public function getName(): string
    {
        return 'pathao_nepal';
    }

    public function supportedCountries(): array
    {
        return ['NP'];
    }

    public function baseUrls(): array
    {
        return [
            self::ENVIRONMENT_SANDBOX => $this->hostOverride('sandbox_base_url') ?? self::HOST_SANDBOX,
            self::ENVIRONMENT_LIVE    => $this->hostOverride('live_base_url') ?? self::HOST_LIVE,
        ];
    }

    public function currency(): string
    {
        return self::CURRENCY_NPR;
    }

    public function deliveryTypes(): array
    {
        return [
            self::DELIVERY_TYPE_NORMAL    => 'Standard Delivery',
            self::DELIVERY_TYPE_ON_DEMAND => 'On Demand Delivery',
        ];
    }

    public function credentialFields(): array
    {
        return [
            ...parent::credentialFields(),
            ['key' => 'sandbox_base_url', 'label' => 'Sandbox Base URL', 'type' => 'text', 'required' => false, 'placeholder' => 'Ex: '.self::HOST_SANDBOX, 'help' => 'Leave blank to use '.self::HOST_SANDBOX.'. Set this if Pathao Nepal issued you a different sandbox host.'],
            ['key' => 'live_base_url', 'label' => 'Live Base URL', 'type' => 'text', 'required' => false, 'placeholder' => 'Ex: '.self::HOST_LIVE, 'help' => 'Leave blank to use '.self::HOST_LIVE.'. Set this if Pathao Nepal issued you a different production host.'],
        ];
    }

    private function hostOverride(string $key): ?string
    {
        $override = trim((string) ($this->credentials[$key] ?? ''));

        return $override !== '' ? rtrim($override, '/') : null;
    }
}
