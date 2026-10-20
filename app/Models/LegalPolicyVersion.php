<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LegalPolicyVersion extends Model
{
    protected $fillable = [
        'business_page_id', 'version', 'title', 'hero_title', 'hero_subtitle',
        'at_a_glance', 'description', 'seo_title', 'seo_description',
        'policy_status', 'published_at', 'published_by',
    ];

    protected $casts = ['published_at' => 'datetime', 'version' => 'integer', 'published_by' => 'integer'];

    public function page()
    {
        return $this->belongsTo(BusinessPage::class, 'business_page_id');
    }
}
