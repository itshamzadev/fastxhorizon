<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CustomerWalletWithdrawalRequest extends Model
{
    protected $fillable = [
        'customer_id', 'amount', 'held_amount', 'withdrawable_amount', 'currency_code', 'method',
        'payment_details', 'reference', 'idempotency_key', 'status', 'customer_note', 'admin_note',
        'reviewed_by', 'reviewed_at', 'payment_reference', 'paid_at', 'released_at',
    ];
    protected $casts = ['amount' => 'decimal:2', 'held_amount' => 'decimal:2', 'withdrawable_amount' => 'decimal:2', 'reviewed_at' => 'datetime', 'paid_at' => 'datetime', 'released_at' => 'datetime'];
    public function customer() { return $this->belongsTo(User::class, 'customer_id'); }
}
