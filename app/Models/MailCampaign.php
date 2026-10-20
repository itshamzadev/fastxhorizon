<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MailCampaign extends Model
{
    protected $fillable = ['name', 'type', 'subject', 'preheader', 'html_body', 'plain_text_body', 'cta_text', 'cta_url', 'hero_image', 'status', 'scheduled_at', 'started_at', 'completed_at', 'created_by'];
    protected $casts = ['scheduled_at' => 'datetime', 'started_at' => 'datetime', 'completed_at' => 'datetime'];
    public function recipients() { return $this->hasMany(MailCampaignRecipient::class, 'campaign_id'); }
}
