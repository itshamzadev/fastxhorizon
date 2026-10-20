<?php

namespace Modules\Courier\app\Http\Controllers\Vendor;

use Devrabiul\ToastMagic\Facades\ToastMagic;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controller;
use Modules\Courier\app\Http\Requests\SaveCourierProviderRequest;
use Modules\Courier\app\Services\CourierConfigService;
use Modules\Courier\app\Services\ProviderRegistry;

class CourierConfigController extends Controller
{
    public function __construct(
        private readonly ProviderRegistry $providers,
        private readonly CourierConfigService $config,
    ) {}

    public function index(): View
    {
        return view('courier::vendor.config.index', ['providers' => $this->providers->catalog()]);
    }

    public function update(SaveCourierProviderRequest $request): RedirectResponse
    {
        if (env('APP_MODE') === 'demo') {
            ToastMagic::info(translate('Update option is disable for demo'));
            return redirect()->route('vendor.courier.config.index');
        }

        $this->config->save($request);
        ToastMagic::success(translate('Delivery_partner_settings_updated_successfully'));
        return back();
    }
}
