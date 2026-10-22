<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PartnerCommissionRule extends Model
{
    protected $fillable = ['program', 'product_id', 'category_id', 'commission_type', 'amount', 'is_active', 'priority'];
    protected $casts = ['amount' => 'decimal:3', 'is_active' => 'boolean'];
}
