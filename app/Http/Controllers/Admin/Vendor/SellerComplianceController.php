<?php

namespace App\Http\Controllers\Admin\Vendor;

use App\Http\Controllers\BaseController;
use App\Models\Seller;
use App\Models\SellerComplianceRecord;
use Devrabiul\ToastMagic\Facades\ToastMagic;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SellerComplianceController extends BaseController
{
    public function index(?Request $request = null, ?string $type = null): View
    {
        $request ??= request();
        $records = SellerComplianceRecord::with('seller')->latest()->paginate(25)->withQueryString();
        $sellers = Seller::query()->approved()->orderBy('f_name')->orderBy('l_name')->get(['id','f_name','l_name','email']);
        return view('admin-views.vendor.compliance', compact('records', 'sellers'));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'seller_id' => 'required|integer|exists:sellers,id',
            'points' => 'required|integer|min:1|max:7',
            'reason' => 'required|string|max:500',
            'evidence' => 'nullable|string|max:4000',
            'source' => 'required|string|in:manual,refund,cancellation,privacy,prohibited_listing,customer_complaint',
            'admin_note' => 'nullable|string|max:2000',
        ]);
        DB::transaction(function () use ($data): void {
            $record = SellerComplianceRecord::create($data + ['status' => 'verified', 'created_by' => auth('admin')->id()]);
            $total = SellerComplianceRecord::where('seller_id', $record->seller_id)->where('status', 'verified')->whereNull('reversed_at')->sum('points');
            $threshold = (int) (getWebConfig('seller_black_point_suspension_threshold') ?: 7);
            if ($total >= $threshold) Seller::whereKey($record->seller_id)->update(['status' => 'suspended']);
        });
        ToastMagic::success('Seller compliance record saved.');
        return back();
    }

    public function reverse(Request $request, int $id): RedirectResponse
    {
        $data = $request->validate(['reversal_reason' => 'required|string|max:1000']);
        $record = SellerComplianceRecord::findOrFail($id);
        if (!$record->reversed_at) {
            $record->update(['reversed_at' => now(), 'reversed_by' => auth('admin')->id(), 'reversal_reason' => $data['reversal_reason'], 'status' => 'reversed']);
        }
        ToastMagic::success('Compliance record reversed.');
        return back();
    }
}
