<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PartnerCommission extends Model
{
    protected $fillable = ['uuid', 'partner_profile_id', 'order_id', 'program', 'base_amount', 'commission_type', 'rate', 'amount', 'status', 'available_at', 'reversed_at'];
    protected $casts = ['base_amount' => 'decimal:3', 'rate' => 'decimal:3', 'amount' => 'decimal:3', 'available_at' => 'datetime', 'reversed_at' => 'datetime'];
    public function profile(): BelongsTo { return $this->belongsTo(PartnerProfile::class, 'partner_profile_id'); }
    public function order(): BelongsTo { return $this->belongsTo(Order::class); }
}
