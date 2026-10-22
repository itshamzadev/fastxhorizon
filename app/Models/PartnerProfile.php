<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PartnerProfile extends Model
{
    protected $fillable = ['uuid', 'user_id', 'program', 'code', 'display_name', 'phone', 'city', 'province', 'address', 'status', 'approved_at', 'suspended_at'];
    protected $casts = ['approved_at' => 'datetime', 'suspended_at' => 'datetime'];

    public function user(): BelongsTo { return $this->belongsTo(User::class); }
    public function applications(): HasMany { return $this->hasMany(PartnerApplication::class); }
    public function commissions(): HasMany { return $this->hasMany(PartnerCommission::class); }
    public function attributions(): HasMany { return $this->hasMany(PartnerAttribution::class); }
    public function payouts(): HasMany { return $this->hasMany(PartnerPayoutRequest::class); }
    public function ledger(): HasMany { return $this->hasMany(PartnerLedgerTransaction::class); }
}
