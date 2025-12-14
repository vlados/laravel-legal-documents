<?php

namespace Vlados\LegalDocuments\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LegalDocumentAcceptance extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'legal_document_id',
        'accepted_at',
        'ip_address',
        'user_agent',
        'created_at',
    ];

    protected $casts = [
        'accepted_at' => 'datetime',
        'created_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $acceptance) {
            $acceptance->created_at = $acceptance->created_at ?? now();
            $acceptance->accepted_at = $acceptance->accepted_at ?? now();
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(config('legal-documents.user_model'));
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(LegalDocument::class, 'legal_document_id');
    }
}
