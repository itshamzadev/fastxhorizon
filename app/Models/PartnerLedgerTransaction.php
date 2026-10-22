<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PartnerLedgerTransaction extends Model
{
    protected $fillable = ['uuid', 'partner_profile_id', 'commission_id', 'payout_request_id', 'type', 'amount', 'reference', 'note'];
    protected $casts = ['amount' => 'decimal:3'];
    public function profile(): BelongsTo { return $this->belongsTo(PartnerProfile::class, 'partner_profile_id'); }
}
