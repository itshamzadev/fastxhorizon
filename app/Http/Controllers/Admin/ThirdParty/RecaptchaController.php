<?php

namespace App\Http\Controllers\Admin\ThirdParty;

use App\Contracts\Repositories\BusinessSettingRepositoryInterface;
use App\Http\Controllers\BaseController;
use Devrabiul\ToastMagic\Facades\ToastMagic;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class RecaptchaController extends BaseController
{

    public function __construct(
        private readonly BusinessSettingRepositoryInterface $businessSettingRepo,
    )
    {
    }

    /**
     * @param Request|null $request
     * @param string|null $type
     * Captcha configuration remains a compatibility route but is not exposed
     * as an active Fast X Horizon setting.
     */
    public function index(Request|null $request, ?string $type = null): RedirectResponse
    {
        return redirect()->route('admin.dashboard.index');
    }

    public function update(Request $request): RedirectResponse
    {
        $current = getWebConfig(name: 'recaptcha') ?? [];
        $value = json_encode([
            'status' => 0,
            'site_key' => $current['site_key'] ?? '',
            'secret_key' => $current['secret_key'] ?? '',
        ]);
        $this->businessSettingRepo->updateOrInsert(type: 'recaptcha', value: $value);
        ToastMagic::success(translate('Updated_Successfully'));
        return back();
    }
}
