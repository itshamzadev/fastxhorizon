<?php

namespace App\Http\Middleware;

use Closure;
use Devrabiul\ToastMagic\Facades\ToastMagic;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class DeliveryPartnerServiceMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($this->isAvailable()) {
            return $next($request);
        }

        // The seller app and the panels' AJAX calls read the gate from the
        // status code, so only a browser navigation is bounced with a toast.
        if ($request->expectsJson()) {
            abort(404);
        }

        ToastMagic::error($this->refusalMessage());

        return redirect()->to($this->previousUrl($request));
    }

    protected function isAvailable(): bool
    {
        return deliveryPartnerServiceAvailable();
    }

    protected function refusalMessage(): string
    {
        return translate('the_delivery_partner_service_is_currently_turned_off');
    }

    private function previousUrl(Request $request): string
    {
        $previous = url()->previous();

        if ($previous !== $request->fullUrl()) {
            return $previous;
        }
        return $request->routeIs('vendor.*')
            ? route('vendor.dashboard.index')
            : route('admin.dashboard.index');
    }
}
