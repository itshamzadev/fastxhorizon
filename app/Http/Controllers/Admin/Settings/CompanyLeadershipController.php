<?php

namespace App\Http\Controllers\Admin\Settings;

use App\Http\Controllers\BaseController;
use App\Models\CompanyLeadershipProfile;
use App\Utils\ImageManager;
use Devrabiul\ToastMagic\Facades\ToastMagic;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class CompanyLeadershipController extends BaseController
{
    public function index(?Request $request = null, ?string $type = null): View
    {
        $profiles = CompanyLeadershipProfile::query()->orderBy('display_order')->orderBy('id')->get();
        return view('admin-views.pages-and-media.leadership', compact('profiles'));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'role' => 'required|string|max:255',
            'bio' => 'nullable|string|max:2000',
            'portfolio_url' => 'nullable|url|max:500',
            'display_order' => 'nullable|integer|min:0|max:9999',
            'photo' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048',
        ]);
        $data['is_active'] = $request->boolean('is_active');
        if ($request->hasFile('photo')) {
            $data['photo'] = ImageManager::upload('company/leadership/', 'webp', $request->file('photo'));
        }
        CompanyLeadershipProfile::create($data);
        ToastMagic::success('Leadership profile saved. It is inactive until explicitly enabled.');
        return back();
    }

    public function update(Request $request, int $id): RedirectResponse
    {
        $profile = CompanyLeadershipProfile::findOrFail($id);
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'role' => 'required|string|max:255',
            'bio' => 'nullable|string|max:2000',
            'portfolio_url' => 'nullable|url|max:500',
            'display_order' => 'nullable|integer|min:0|max:9999',
            'photo' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048',
        ]);
        $data['is_active'] = $request->boolean('is_active');
        if ($request->hasFile('photo')) {
            if ($profile->photo) ImageManager::delete('company/leadership/' . $profile->photo);
            $data['photo'] = ImageManager::upload('company/leadership/', 'webp', $request->file('photo'));
        }
        $profile->update($data);
        ToastMagic::success('Leadership profile updated.');
        return back();
    }

    public function destroy(int $id): RedirectResponse
    {
        $profile = CompanyLeadershipProfile::findOrFail($id);
        if ($profile->photo) ImageManager::delete('company/leadership/' . $profile->photo);
        $profile->delete();
        ToastMagic::success('Leadership profile removed.');
        return back();
    }
}
