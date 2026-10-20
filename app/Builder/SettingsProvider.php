<?php

namespace App\Builder;

use Modules\Builder\Contracts\SettingsProvider as SettingsProviderContract;

class SettingsProvider implements SettingsProviderContract
{
    public function brandName(): ?string
    {
        return getWebConfig(name: 'company_name');
    }

    public function mapApiKey(): ?string
    {
        return getWebConfig(name: 'map_api_key');
    }

    public function referralEarningRate(): float
    {
        return (float) (getWebConfig(name: 'ref_earning_exchange_rate') ?? 0);
    }

    public function firebaseConfig(): ?array
    {
        // Same `fcm_credentials` row the host's service-worker generator and
        // push dispatch pipeline use, so token issuance and delivery point at
        // one Firebase project. 6Valley stores no web-push VAPID key, so the
        // essentials gate on apiKey + projectId; vapidKey passes through when
        // an operator adds it later.
        $config = getWebConfig(name: 'fcm_credentials');
        if (!\is_array($config) || empty($config['apiKey']) || empty($config['projectId'])) {
            return null;
        }

        return [
            'apiKey'            => $config['apiKey'] ?? '',
            'authDomain'        => $config['authDomain'] ?? '',
            'projectId'         => $config['projectId'] ?? '',
            'storageBucket'     => $config['storageBucket'] ?? '',
            'messagingSenderId' => $config['messagingSenderId'] ?? '',
            'appId'             => $config['appId'] ?? '',
            'measurementId'     => $config['measurementId'] ?? '',
            'vapidKey'          => $config['vapidKey'] ?? '',
        ];
    }
}
