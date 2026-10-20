<?php

namespace App\Http\Controllers\Admin\ThirdParty;

use App\Http\Controllers\BaseController;
use App\Models\ErpApiToken;
use Devrabiul\ToastMagic\Facades\ToastMagic;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;

class ErpController extends BaseController
{
    public function index(Request|null $request, ?string $type = null): View
    {
        $tokens = ErpApiToken::latest()->get();
        return view('admin-views.third-party.erp.index', compact('tokens'));
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'name' => 'required|string|max:100',
            'webhook_url' => 'required|url|max:500',
        ]);

        if (env('APP_MODE') == 'demo') {
            ToastMagic::error(translate('this_action_is_not_available_in_demo_mode'));
            return back();
        }

        $apiKey = Str::random(48);
        $apiSecret = Str::random(64);

        ErpApiToken::create([
            'name' => $request->name,
            'api_key' => $apiKey,
            'api_secret' => Crypt::encryptString($apiSecret),
            'webhook_url' => $request->webhook_url,
        ]);

        ToastMagic::success(translate('token_generated_successfully'));
        return back()->with('new_credentials', [
            'api_key' => $apiKey,
            'api_secret' => $apiSecret,
        ]);
    }

    public function revoke(Request $request): RedirectResponse
    {
        $token = ErpApiToken::find($request->id);
        if (!$token) {
            ToastMagic::error(translate('token_not_found'));
            return back();
        }

        $token->update(['is_active' => false]);
        ToastMagic::success(translate('token_revoked_successfully'));
        return back();
    }

    public function delete(Request $request): RedirectResponse
    {
        $token = ErpApiToken::find($request->id);
        if (!$token) {
            ToastMagic::error(translate('token_not_found'));
            return back();
        }

        $token->delete();
        ToastMagic::success(translate('token_deleted_successfully'));
        return back();
    }
}
