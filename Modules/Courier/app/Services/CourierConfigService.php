<?php

namespace Modules\Courier\app\Services;

use Modules\Courier\app\Http\Requests\SaveCourierProviderRequest;
use Modules\Courier\app\Models\CourierProviderSetting;

class CourierConfigService
{
    public function __construct(private readonly ProviderRegistry $providers) {}

    public function save(SaveCourierProviderRequest $request): void
    {
        $owner = $this->providers->owner();

        $setting = CourierProviderSetting::firstOrNew([
            'owner_type' => $owner->type,
            'owner_id'   => $owner->id,
            'provider'   => $request->input('provider'),
        ]);

        $setting->is_active = $request->boolean('is_enabled');

        if ($request->has('credentials')) {
            $setting->credentials = (array) $request->input('credentials', []);
        }

        $settings = (array) $setting->settings;

        if ($request->input('environment') !== null) {
            $settings['environment'] = $request->input('environment');
        }

        if ($request->input('country') !== null) {
            $settings['country'] = $request->input('country');
        }

        $setting->settings = $settings;
        $setting->save();
    }
}
