<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Customer\Auth\RegisterController;
use App\Http\Requests\Web\CustomerRegistrationRequest;
use App\Models\PartnerApplication;
use App\Models\PartnerProfile;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Schema;

class PartnerPortalAuthController extends Controller
{
    public function login(string $program)
    {
        $this->validateProgram($program);
        if (auth('customer')->check()) return redirect(self::destination($program, auth('customer')->user()));
        return view('partner.auth.login', compact('program'));
    }

    public function register(string $program)
    {
        $this->validateProgram($program);
        if (auth('customer')->check()) return redirect()->route($program . '.apply');
        return view('partner.auth.register', compact('program'));
    }

    public function registerSubmit(Request $request, string $program)
    {
        $this->validateProgram($program);
        if (auth('customer')->check()) return redirect()->route($program . '.apply');

        $validator = Validator::make($request->all(), [
            'f_name' => ['required', 'string', 'max:80'], 'l_name' => ['nullable', 'string', 'max:80'],
            'email' => ['required', 'email'], 'phone' => ['required', 'string', 'max:20'],
            'password' => ['required', 'same:con_password', 'min:8'], 'con_password' => ['required'],
        ]);
        if ($validator->fails()) return back()->withErrors($validator)->withInput();

        if (User::where('email', $request->email)->orWhere('phone', $request->phone)->exists()) {
            return back()->withErrors(['email' => 'An account with these details already exists. Sign in to continue your ' . ucfirst($program) . ' application.'])->with('existing_account_portal', route($program . '.login'))->withInput();
        }

        $request->merge(['keep_customer_login_redirect_url' => route($program . '.apply')]);
        $customerRequest = CustomerRegistrationRequest::createFrom($request);
        $customerRequest->setContainer(app())->setRedirector(app('redirect'));
        return app(RegisterController::class)->submitRegisterData($customerRequest);
    }

    public static function destination(string $program, User $user): string
    {
        if (!Schema::hasTable('partner_profiles')) return route($program . '.apply');
        $profile = PartnerProfile::where(['user_id' => $user->id, 'program' => $program])->first();
        if ($profile?->status === 'approved') return route($program . '.dashboard');
        return route($program . '.apply');
    }

    private function validateProgram(string $program): void
    {
        abort_unless(in_array($program, ['reseller', 'affiliate'], true), 404);
    }
}
