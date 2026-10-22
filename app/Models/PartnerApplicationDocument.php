<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PartnerApplicationDocument extends Model
{
    protected $fillable = ['uuid', 'partner_application_id', 'document_type', 'disk', 'path', 'original_name', 'mime_type', 'size'];

    public function application(): BelongsTo
    {
        return $this->belongsTo(PartnerApplication::class, 'partner_application_id');
    }
}
