<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OfflinePaymentProof extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'amount_claimed' => 'decimal:4',
        'reviewed_at' => 'datetime',
    ];

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function events()
    {
        return $this->hasMany(OfflinePaymentProofEvent::class, 'proof_id');
    }
}
