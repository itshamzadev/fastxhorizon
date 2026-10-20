<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MailCampaignRecipient extends Model
{
    protected $fillable = ['campaign_id', 'email', 'status', 'delivery_log_id', 'sent_at'];
    protected $casts = ['sent_at' => 'datetime'];
    public function campaign() { return $this->belongsTo(MailCampaign::class, 'campaign_id'); }
}
