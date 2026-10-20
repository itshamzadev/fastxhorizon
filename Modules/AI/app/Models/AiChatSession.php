<?php

namespace Modules\AI\app\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int         $id
 * @property int|null    $customer_id
 * @property string|null $guest_id
 * @property string|null $title
 * @property string      $locale
 * @property array|null  $context
 * @property Carbon|null $last_activity_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class AiChatSession extends Model
{
    use HasFactory;

    protected $table = 'ai_chat_sessions';

    protected $fillable = [
        'customer_id',
        'guest_id',
        'title',
        'locale',
        'context',
        'last_activity_at',
    ];

    protected $casts = [
        'context'          => 'array',
        'last_activity_at' => 'datetime',
    ];

    public function messages(): HasMany
    {
        return $this->hasMany(AiChatMessage::class, 'session_id')->orderBy('created_at');
    }

    public function scopeForCustomer($query, int $customerId)
    {
        return $query->where('customer_id', $customerId);
    }

    public function scopeForGuest($query, string $guestId)
    {
        return $query->where('guest_id', $guestId);
    }
}
