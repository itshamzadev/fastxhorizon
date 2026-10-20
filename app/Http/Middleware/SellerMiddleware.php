<?php

namespace App\Http\Middleware;

use Closure;
use App\Utils\Helpers;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class SellerMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param Request $request
     * @param Closure $next
     * @return mixed
     */
    public function handle(Request $request, Closure $next): mixed
    {
        if (auth('seller')->check() && auth('seller')->user()->status == 'approved') {
            return $next($request);
        }
        auth()->guard('seller')->logout();

        // The vendor-panel builder is an Inertia (React) app. Its XHR navigations
        // can't follow a 302 to the plain-HTML login page — Inertia would render
        // that HTML inside its "unexpected response" modal. Return a 409 with
        // X-Inertia-Location so the client performs a full-page redirect to the
        // login instead (flash a notice so the login page can explain why).
        if ($request->header('X-Inertia')) {
            // The login page surfaces the validation error bag (toastMagic over
            // $errors), so flash the notice there rather than session('error').
            $request->session()->flash(
                'errors',
                (new \Illuminate\Support\ViewErrorBag)->put(
                    'default',
                    new \Illuminate\Support\MessageBag([
                        'auth' => translate('You have been logged out. Please log in again.'),
                    ])
                )
            );
            return Inertia::location(route('vendor.auth.login'));
        }

        return redirect()->route('vendor.auth.login');
    }
}
