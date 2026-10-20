<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Class WalletTransaction
 *
 * @property int $id
 * @property int|null $user_id
 * @property string $transaction_id
 * @property string|null $reference
 * @property string|null $transaction_type
 * @property float $credit
 * @property float $debit
 * @property float $admin_bonus
 * @property float $balance
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @package App\Models
 */
class WalletTransaction extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'transaction_id',
        'reference',
        'transaction_type',
        'credit',
        'debit',
        'admin_bonus',
        'balance',
        'balance_before',
        'balance_after',
        'payment_method',
        'currency_code',
        'source',
        'status',
        'idempotency_key',
        'related_order_id',
        'related_refund_id',
        'created_by',
        'description',
        'created_at',
        'updated_at',
    ];

    protected $casts = [
        'user_id' => 'integer',
        'credit' => 'float',
        'debit' => 'float',
        'admin_bonus' => 'float',
        'balance' => 'float',
        'balance_before' => 'float',
        'balance_after' => 'float',
        'related_order_id' => 'integer',
        'related_refund_id' => 'integer',
        'created_by' => 'integer',
        'reference' => 'string',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
