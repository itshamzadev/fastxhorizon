<?php

namespace App\Http\Controllers\Web;

use App\Contracts\Repositories\CurrencyRepositoryInterface;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Services\FastXHorizonCurrencyService;

class CurrencyController extends Controller
{
    public function __construct(
        private readonly CurrencyRepositoryInterface $currencyRepo,
        private readonly FastXHorizonCurrencyService $currencyService,
    )
    {
    }

    public function changeCurrency(Request $request): JsonResponse
    {
        $code = strtoupper((string) $request->input('currency_code'));
        if (!$this->currencyService->setManual($code)) {
            return response()->json(['message' => 'Selected currency is not available.'], 422);
        }
        $currency = $this->currencyRepo->getFirstWhere(params: ['code' => $code]);
        $message = translate('currency_changed_to') . ' ' . $currency['name'];
        return response()->json(['message' => $message]);
    }
}
