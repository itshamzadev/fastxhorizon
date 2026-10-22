<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PartnerPayoutRequest extends Model
{
    protected $fillable = ['uuid', 'partner_profile_id', 'program', 'amount', 'payout_method', 'account_details', 'status', 'reference', 'admin_note', 'reviewed_by', 'reviewed_at'];
    protected $casts = ['amount' => 'decimal:3', 'reviewed_at' => 'datetime', 'account_details' => 'encrypted'];
    protected $hidden = ['account_details'];
    public function profile(): BelongsTo { return $this->belongsTo(PartnerProfile::class, 'partner_profile_id'); }
    public function reviewer(): BelongsTo { return $this->belongsTo(Admin::class, 'reviewed_by'); }
}
