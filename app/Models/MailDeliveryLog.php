<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MailDeliveryLog extends Model
{
    protected $fillable = ['message_id', 'template_key', 'campaign_id', 'recipient', 'subject', 'type', 'status', 'attempts', 'failure_reason', 'queued_at', 'sent_at', 'failed_at'];
    protected $casts = ['queued_at' => 'datetime', 'sent_at' => 'datetime', 'failed_at' => 'datetime'];
}
