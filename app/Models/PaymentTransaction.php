<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PaymentTransaction extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'metadata' => 'array',
        'amount' => 'decimal:4',
        'paid_at' => 'datetime',
        'failed_at' => 'datetime',
    ];
}
