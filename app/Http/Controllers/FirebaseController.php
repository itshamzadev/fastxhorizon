<?php

namespace App\Http\Controllers;

use App\Http\Requests\Request;
use App\Models\Seller;
use App\Utils\Helpers;
use App\Models\BusinessSetting;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Session;

class FirebaseController extends Controller
{
    public function subscribeToTopic(Request $request): JsonResponse
    {
        $request->validate([
            'token' => 'required|string',
            'topic' => 'required|string',
        ]);

        $token = $request->input('token');
        $topic = $request->input('topic');

        $customer = Auth::guard('customer')->user();
        if ($customer) {
            User::where('id', $customer->id)->update(['cm_firebase_token_web' => $token]);
        }

        try {
            app('firebase.messaging')->subscribeToTopic($topic, $token);
            return response()->json(['message' => 'Successfully subscribed to topic'], 200);
        } catch (\Throwable $e) {
            Log::warning('Firebase topic subscription skipped because Firebase is unavailable.', [
                'request_id' => $request->header('X-Request-ID'),
            ]);

            return response()->json([
                'message' => 'Push notifications are not currently configured.',
            ], 503);
        }
    }

    public function saveSellerWebToken(Request $request): JsonResponse
    {
        $request->validate(['token' => 'required|string']);

        $seller = Auth::guard('seller')->user();
        if ($seller) {
            Seller::where('id', $seller->id)->update(['cm_firebase_token_web' => $request->input('token')]);
        }

        return response()->json(['message' => 'Token saved'], 200);
    }
}
