<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Per-customer search keyword history; each keyword resolves (literal + AI-bridged) to tag ids
 * so the personalization layer can score matching products.
 *
 * @property int $id
 * @property int $customer_id
 * @property string $keyword
 * @property array|null $tag_ids
 * @property int $search_count
 * @property Carbon $searched_at
 */
class CustomerSearch extends Model
{
    protected $fillable = [
        'customer_id',
        'keyword',
        'tag_ids',
        'search_count',
        'searched_at',
    ];

    protected $casts = [
        'customer_id' => 'integer',
        'tag_ids' => 'array',
        'search_count' => 'integer',
        'searched_at' => 'datetime',
    ];
}
