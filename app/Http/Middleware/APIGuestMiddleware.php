<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Models\GuestUser;

class APIGuestMiddleware
{
    /**
     * @param Request $request
     * @param Closure $next
     * @return mixed
     */
    public function handle(Request $request, Closure $next): mixed
    {
        if ($request->header('Authorization') && app('auth')->guard('api')) {
            $request->merge(['user' => auth('api')->user()]);
            return $next($request);
        } elseif ($request->guest_token) {
            $guest = GuestUser::query()
                ->where('guest_token', $request->guest_token)
                ->where(function ($query) {
                    $query->whereNull('expires_at')->orWhere('expires_at', '>', now());
                })->first();
            if (!$guest) {
                return response()->json(['message' => 'Guest cart token is invalid or expired.'], 401);
            }
            $request->merge(['guest_id' => $guest->id]);
            return $next($request);
        } elseif ($request->guest_id) {
            return $next($request);
        }

        // Proper 401 body + status. Previously returned ['Unauthorized', 401] as the
        // BODY with an implicit 200, which clients couldn't reliably detect.
        return response()->json(['message' => 'Unauthorized'], 401);
    }
}
