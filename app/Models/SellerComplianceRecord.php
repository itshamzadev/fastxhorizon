<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SellerComplianceRecord extends Model
{
    protected $fillable = [
        'seller_id', 'customer_id', 'order_id', 'product_id', 'complaint_id', 'points',
        'source', 'reason', 'evidence', 'status', 'admin_note', 'created_by',
        'reversed_at', 'reversed_by', 'reversal_reason',
    ];
    protected $casts = ['points' => 'integer', 'reversed_at' => 'datetime'];

    public function seller()
    {
        return $this->belongsTo(Seller::class, 'seller_id');
    }
}
