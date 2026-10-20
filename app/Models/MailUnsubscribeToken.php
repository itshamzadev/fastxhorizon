<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MailUnsubscribeToken extends Model
{
    protected $fillable = ['email', 'token', 'unsubscribed_at'];
    protected $casts = ['unsubscribed_at' => 'datetime'];
}
