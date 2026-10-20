<?php

namespace App\Builder;

use App\Models\GuestUser;
use App\Models\User;
use Illuminate\Foundation\Auth\User as AuthenticatableUser;
use App\Services\Web\CustomerAuthService;
use App\Traits\FileManagerTrait;
use App\Utils\CartManager;
use App\Utils\CustomerManager;
use App\Utils\Helpers;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Modules\Builder\Contracts\CustomerAuthProvider as CustomerAuthProviderContract;
use Modules\Builder\Services\StorefrontContext;
use Modules\Builder\ValueObjects\StorefrontCustomer;

/**
 * 6Valley host adapter for storefront customer authentication.
 *
 * 6Valley is single-tenant, so the 6amMart tenant/HostScope filtering drops
 * entirely. OTP for phone and email shares the `phone_or_email_verifications`
 * table; password-reset OTP uses `password_resets` (keyed by `identity`).
 * Registration, token send and email/SMS delivery are delegated to the host
 * CustomerAuthService so the storefront stays consistent with the legacy site.
 * Social login is gated behind config('builder.social_login_enabled').
 */
class CustomerAuthProvider implements CustomerAuthProviderContract
{
    use FileManagerTrait;

    private const PENDING_PROFILE_SESSION_KEY = 'pending_profile_user_id';
    private const OTP_TABLE = 'phone_or_email_verifications';

    public function __construct(
        private StorefrontContext $context,
        private CustomerAuthService $authService,
    ) {
    }

    public function current(): ?StorefrontCustomer
    {
        $user = Auth::guard('customer')->user();
        return $user ? $this->toCustomer($user) : null;
    }

    public function loginWithPassword(string $emailOrPhone, string $password, string $fieldType): StorefrontCustomer
    {
        $authenticated = false;
        if ($fieldType === 'email') {
            $authenticated = Auth::guard('customer')->attempt([
                'email' => $emailOrPhone,
                'password' => $password,
            ]);
        } else {
            foreach ($this->phoneIdentityCandidates($emailOrPhone) as $phone) {
                if (Auth::guard('customer')->attempt(['phone' => $phone, 'password' => $password])) {
                    $authenticated = true;
                    break;
                }
            }
        }

        if (!$authenticated) {
            throw ValidationException::withMessages(['email_or_phone' => translate('Credentials_do_not_match') ?: 'Credentials do not match.']);
        }

        $user = Auth::guard('customer')->user();
        $user->login_medium = 'system';
        $user->save();

        $this->afterLogin($user);

        return $this->toCustomer($user);
    }

    public function sendLoginOtp(string $phone): void
    {
        $this->issuePhoneOtp($this->normalizePhone($phone));
    }

    public function loginWithOtp(string $phone, string $otp): StorefrontCustomer
    {
        $phone = $this->normalizePhone($phone);
        $this->assertOtp($phone, $otp);

        $user = User::whereIn('phone', $this->phoneIdentityCandidates($phone))->first();
        if (!$user) {
            $user = new User();
            $user->phone = $phone;
            $user->password = bcrypt($phone);
            $user->is_phone_verified = 1;
            $user->login_medium = 'otp';
            $user->referral_code = Helpers::generate_referer_code();
            $user->save();
        } else {
            $user->is_phone_verified = 1;
            $user->login_medium = 'otp';
            $user->save();
        }

        DB::table(self::OTP_TABLE)->where('phone_or_email', $phone)->delete();

        if (!$this->isProfileComplete($user)) {
            session([self::PENDING_PROFILE_SESSION_KEY => $user->id]);
            return $this->toCustomer($user);
        }

        Auth::guard('customer')->loginUsingId($user->id);
        $this->afterLogin($user);

        return $this->toCustomer($user);
    }

    public function register(array $data): array
    {
        $name = trim((string) ($data['name'] ?? ''));
        $email = $data['email'] ?? null;
        $phone = $this->normalizePhone($data['phone'] ?? null);
        $password = $data['password'] ?? null;
        $refCode = config('builder.wallet_features_enabled', true) ? ($data['ref_code'] ?? null) : null;

        [$firstName, $lastName] = $this->splitName($name);

        $errors = [];
        if ($name === '') {
            $errors['name'] = [translate('The_name_field_is_required') ?: 'The name field is required.'];
        }
        if (!$email) {
            $errors['email'] = [translate('The_email_field_is_required') ?: 'The email field is required.'];
        } elseif (User::where('email', $email)->exists()) {
            $errors['email'] = [translate('The_email_has_already_been_taken') ?: 'The email has already been taken.'];
        }
        if (!$phone) {
            $errors['phone'] = [translate('The_phone_field_is_required') ?: 'The phone field is required.'];
        } elseif (User::whereIn('phone', $this->phoneIdentityCandidates($phone))->exists()) {
            $errors['phone'] = [translate('The_phone_has_already_been_taken') ?: 'The phone has already been taken.'];
        }
        if (!$password || strlen($password) < 8) {
            $errors['password'] = [translate('The_password_must_be_at_least_8_characters') ?: 'The password must be at least 8 characters.'];
        }
        if ($errors) {
            throw ValidationException::withMessages($errors);
        }

        $referUser = $refCode ? User::where('referral_code', $refCode)->first() : null;

        $user = User::create($this->authService->getCustomerRegisterData([
            'f_name' => $firstName,
            'l_name' => $lastName,
            'email' => $email,
            'phone' => $phone,
            'password' => $password,
        ], $referUser));

        $needsPhone = (bool) getLoginConfig(key: 'phone_verification') && !$user->is_phone_verified;
        $needsEmail = !$needsPhone && (bool) getLoginConfig(key: 'email_verification') && !$user->is_email_verified;

        if ($needsPhone) {
            $this->issuePhoneOtp($phone);
        } elseif ($needsEmail) {
            $this->issueEmailOtp($user);
        } else {
            Auth::guard('customer')->loginUsingId($user->id);
            $this->afterLogin($user);
        }

        return [
            'customer' => $this->toCustomer($user),
            'needsPhoneVerification' => $needsPhone,
            'needsEmailVerification' => $needsEmail,
        ];
    }

    public function verifyContact(string $type, string $value, string $otp): StorefrontCustomer
    {
        $value = $type === 'phone' ? $this->normalizePhone($value) : $value;

        $user = $type === 'phone'
            ? User::whereIn('phone', $this->phoneIdentityCandidates($value))->first()
            : User::where($type, $value)->first();
        if (!$user) {
            throw ValidationException::withMessages(['_form' => translate('User_not_found') ?: 'User not found.']);
        }

        $this->assertOtp($value, $otp);
        DB::table(self::OTP_TABLE)->where('phone_or_email', $value)->delete();

        $user->{$type === 'phone' ? 'is_phone_verified' : 'is_email_verified'} = 1;
        $user->save();

        Auth::guard('customer')->loginUsingId($user->id);
        $this->afterLogin($user);

        return $this->toCustomer($user);
    }

    public function resendVerificationOtp(string $type, string $value): void
    {
        if ($type === 'phone') {
            $this->issuePhoneOtp($this->normalizePhone($value));
        } else {
            $user = User::where('email', $value)->first();
            if ($user) {
                $this->issueEmailOtp($user);
            }
        }
    }

    public function sendPasswordResetOtp(string $type, string $value): void
    {
        $value = $type === 'phone' ? $this->normalizePhone($value) : $value;

        $user = $type === 'phone'
            ? User::whereIn('phone', $this->phoneIdentityCandidates($value))->first()
            : User::where($type, $value)->first();
        if (!$user) {
            throw ValidationException::withMessages([$type => translate('User_not_found') ?: 'User not found.']);
        }

        $token = $this->generateToken();
        DB::table('password_resets')->updateOrInsert(
            ['identity' => $value, 'user_type' => 'customer'],
            ['token' => $token, 'created_at' => now()],
        );

        if ($type === 'phone') {
            $this->authService->sendCustomerPhoneVerificationToken($value, $token);
        } else {
            $this->authService->sendCustomerEmailVerificationToken($user, $token);
        }
    }

    public function verifyPasswordResetOtp(string $type, string $value, string $otp): bool
    {
        $value = $type === 'phone' ? $this->normalizePhone($value) : $value;

        if ($this->isDemoOtp($otp)) {
            return true;
        }

        return DB::table('password_resets')
            ->where(['identity' => $value, 'token' => $otp, 'user_type' => 'customer'])
            ->exists();
    }

    public function resetPassword(string $type, string $value, string $otp, string $newPassword): void
    {
        $value = $type === 'phone' ? $this->normalizePhone($value) : $value;

        if (!$this->verifyPasswordResetOtp($type, $value, $otp)) {
            throw ValidationException::withMessages(['otp' => translate('OTP_does_not_match') ?: 'OTP does not match.']);
        }
        if (strlen($newPassword) < 8) {
            throw ValidationException::withMessages(['password' => translate('The_password_must_be_at_least_8_characters') ?: 'The password must be at least 8 characters.']);
        }

        $user = User::where($type, $value)->first();
        if (!$user) {
            throw ValidationException::withMessages(['_form' => translate('User_not_found') ?: 'User not found.']);
        }

        $user->password = bcrypt($newPassword);
        $user->save();

        DB::table('password_resets')->where(['identity' => $value, 'user_type' => 'customer'])->delete();
    }

    public function loginWithSocial(string $provider, array $payload): array
    {
        // Storefront social login is gated: providers key on registered
        // origins / redirect URIs which don't work across arbitrary vendor
        // domains without a central broker.
        if (!config('builder.social_login_enabled', false)) {
            throw ValidationException::withMessages(['_form' => translate('Social_login_is_currently_unavailable') ?: 'Social login is currently unavailable.']);
        }

        throw ValidationException::withMessages(['_form' => translate('Social_login_is_currently_unavailable') ?: 'Social login is currently unavailable.']);
    }

    public function logout(): void
    {
        Auth::guard('customer')->logout();
        session()->forget(self::PENDING_PROFILE_SESSION_KEY);
    }

    public function pendingProfileUser(): ?StorefrontCustomer
    {
        $id = session(self::PENDING_PROFILE_SESSION_KEY);
        if (!$id) {
            return null;
        }

        $user = User::find($id);
        if (!$user) {
            session()->forget(self::PENDING_PROFILE_SESSION_KEY);
            return null;
        }

        return $this->toCustomer($user);
    }

    public function completeProfile(array $data): StorefrontCustomer
    {
        $id = session(self::PENDING_PROFILE_SESSION_KEY);
        $user = $id ? User::find($id) : null;
        if (!$user) {
            throw ValidationException::withMessages(['_form' => translate('No_pending_profile') ?: 'No pending profile.']);
        }

        if (!empty($data['name'])) {
            [$firstName, $lastName] = $this->splitName((string) $data['name']);
            $user->f_name = $firstName;
            $user->l_name = $lastName;
        }
        if (!empty($data['phone'])) {
            $user->phone = $this->normalizePhone($data['phone']);
        }
        if (!empty($data['email'])) {
            $user->email = $data['email'];
        }
        if (!empty($data['ref_code']) && !$user->referred_by && config('builder.wallet_features_enabled', true)) {
            $referUser = User::where('referral_code', $data['ref_code'])->first();
            $user->referred_by = $referUser?->id;
        }
        $user->save();

        Auth::guard('customer')->loginUsingId($user->id);
        $this->afterLogin($user);
        session()->forget(self::PENDING_PROFILE_SESSION_KEY);

        return $this->toCustomer($user);
    }

    public function updateProfile(array $data, ?\Illuminate\Http\UploadedFile $image = null): StorefrontCustomer
    {
        $user = Auth::guard('customer')->user();
        if (!$user) {
            throw ValidationException::withMessages(['_form' => translate('Sign_in_required') ?: 'Sign in required.']);
        }

        if (array_key_exists('f_name', $data) && $data['f_name'] !== null) {
            $user->f_name = $data['f_name'];
        }
        // Last name is optional and submitted alongside the first name. An empty
        // value (ConvertEmptyStringsToNull turns "" into null) means the customer
        // cleared it — persist that instead of keeping the old surname, which
        // otherwise stayed appended to the updated first name (e.g. renaming
        // "Need Change Name" to "NeedChange" left "NeedChange Change Name").
        if (array_key_exists('l_name', $data)) {
            $user->l_name = $data['l_name'] ?? '';
        }

        // Email is the account identity (login + notifications) and is NOT
        // updatable once set — the edit form shows it read-only. Enforce it
        // server-side too so a tampered request can't change it. Phone-only
        // signups with no email yet may set one here (one-time).
        if (empty($user->email) && !empty($data['email'])) {
            $user->email = $data['email'];
        }
        if (!empty($data['phone'])) {
            $user->phone = $this->normalizePhone($data['phone']);
        }
        if (!empty($data['password'])) {
            $user->password = bcrypt($data['password']);
        }
        if ($image) {
            $user->image = $this->upload(dir: 'profile/', format: 'webp', image: $image);
        }
        $user->save();

        return $this->toCustomer($user);
    }

    public function ensureGuestId(): ?int
    {
        if (Auth::guard('customer')->check()) {
            return null;
        }

        $guestId = session('guest_id');
        if ($guestId && GuestUser::where('id', $guestId)->exists()) {
            return (int) $guestId;
        }

        $guest = GuestUser::create(['ip_address' => request()->ip()]);
        session(['guest_id' => $guest->id]);

        return (int) $guest->id;
    }

    public function loginMethods(): array
    {
        $firebase = getWebConfig(name: 'firebase_otp_verification');

        return [
            'manual' => true,
            'otp' => (bool) ($firebase['status'] ?? 0),
        ];
    }

    public function socialConfig(): array
    {
        $enabled = (bool) config('builder.social_login_enabled', false);

        return [
            'google'   => ['enabled' => $enabled, 'clientId' => null],
            'facebook' => ['enabled' => $enabled, 'appId' => null],
            'apple'    => ['enabled' => $enabled, 'clientId' => null, 'redirectUri' => null],
        ];
    }

    /* ─── helpers ─────────────────────────────────────────── */

    // Receives the `customer` guard user (App\User) on login and query-loaded
    // App\Models\User elsewhere — typed to the shared base to accept both.
    private function afterLogin(AuthenticatableUser $user): void
    {
        try {
            CustomerManager::updateCustomerSessionData(userId: $user->id);
            CartManager::cartListSessionToDatabase();
        } catch (\Throwable) {
        }
    }

    private function issuePhoneOtp(string $phone): void
    {
        $token = $this->generateToken();
        $this->storeOtp($phone, $token);
        $this->authService->sendCustomerPhoneVerificationToken($phone, $token);
    }

    private function issueEmailOtp(User $user): void
    {
        $token = $this->generateToken();
        $this->storeOtp((string) $user->email, $token);
        $this->authService->sendCustomerEmailVerificationToken($user, $token);
    }

    private function storeOtp(string $identity, string $token): void
    {
        DB::table(self::OTP_TABLE)->updateOrInsert(
            ['phone_or_email' => $identity],
            ['token' => $token, 'otp_hit_count' => 0, 'created_at' => now(), 'updated_at' => now()],
        );
    }

    private function assertOtp(string $identity, string $otp): void
    {
        if ($this->isDemoOtp($otp)) {
            return;
        }

        $matched = DB::table(self::OTP_TABLE)
            ->where('phone_or_email', $identity)
            ->where('token', $otp)
            ->exists();

        if (!$matched) {
            throw ValidationException::withMessages(['otp' => translate('OTP_does_not_match') ?: 'OTP does not match.']);
        }
    }

    private function generateToken(): string
    {
        return app()->environment(['local', 'testing']) ? '123456' : (string) rand(100000, 999999);
    }

    private function isDemoOtp(string $otp): bool
    {
        return app()->environment(['local', 'testing']) && $otp === '123456';
    }

    private function isProfileComplete(User $user): bool
    {
        return (string) ($user->f_name ?? '') !== ''
            && (string) ($user->phone ?? '') !== ''
            && (string) ($user->email ?? '') !== '';
    }

    private function normalizePhone(?string $phone): string
    {
        $phone = trim((string) $phone);
        if ($phone === '') {
            return '';
        }

        $clean = preg_replace('/[^0-9+]/', '', $phone) ?: '';
        $digits = preg_replace('/[^0-9]/', '', $clean) ?: '';
        if (str_starts_with($digits, '00')) {
            $digits = substr($digits, 2);
        }

        if (preg_match('/^0?3\d{9}$/', $digits)) {
            return '+92' . ltrim($digits, '0');
        }
        if (str_starts_with($digits, '92') && strlen($digits) >= 12) {
            return '+' . $digits;
        }

        return $digits !== '' ? '+' . $digits : $phone;
    }

    private function phoneIdentityCandidates(?string $phone): array
    {
        $phone = trim((string) $phone);
        if ($phone === '') {
            return [''];
        }

        $candidates = [$phone, $this->normalizePhone($phone)];
        $clean = preg_replace('/[^0-9+]/', '', $phone) ?: '';
        if ($clean !== '') {
            $candidates[] = $clean;
        }

        return array_values(array_unique(array_filter($candidates)));
    }

    private function splitName(string $name): array
    {
        $parts = preg_split('/\s+/', trim($name)) ?: [];
        $firstName = array_shift($parts) ?? '';
        $lastName = implode(' ', $parts);
        return [$firstName, $lastName];
    }

    // Accepts the shared Eloquent auth base: the `customer` guard resolves to
    // App\User while this provider's own queries use App\Models\User — both map
    // to the `users` table and expose the same attributes read below.
    private function toCustomer(AuthenticatableUser $user): StorefrontCustomer
    {
        $imageUrl = !empty($user->image)
            ? getStorageImages(path: $user->image_full_url ?? null, type: 'backend-profile')
            : null;

        return new StorefrontCustomer(
            id: (int) $user->id,
            firstName: $user->f_name,
            lastName: $user->l_name,
            email: $user->email,
            phone: $user->phone,
            imageUrl: $imageUrl,
            isPhoneVerified: (bool) $user->is_phone_verified,
            isEmailVerified: (bool) $user->is_email_verified,
            // wallet_balance is stored in the default currency — convert for
            // display (multi-currency); loyaltyPoint is a point count, not money.
            walletBalance: (float) webCurrencyConverterOnlyDigit(amount: (float) ($user->wallet_balance ?? 0)),
            loyaltyPoint: (float) ($user->loyalty_point ?? 0),
            refCode: $user->referral_code,
            createdAt: optional($user->created_at)->toIso8601String(),
        );
    }
}
