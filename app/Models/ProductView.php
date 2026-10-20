<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Per-customer product view frequency; a low-weight signal for the personalized homepage layer.
 *
 * @property int $id
 * @property int $customer_id
 * @property int $product_id
 * @property int $view_count
 * @property Carbon $viewed_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class ProductView extends Model
{
    protected $fillable = [
        'customer_id',
        'product_id',
        'view_count',
        'viewed_at',
    ];

    protected $casts = [
        'customer_id' => 'integer',
        'product_id' => 'integer',
        'view_count' => 'integer',
        'viewed_at' => 'datetime',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
    }
}
