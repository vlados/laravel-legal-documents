<?php

namespace Vlados\LegalDocuments\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class LegalDocumentType extends Model
{
    protected $fillable = [
        'slug',
        'name',
        'description',
        'is_required',
        'sort_order',
    ];

    protected $casts = [
        'is_required' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function documents(): HasMany
    {
        return $this->hasMany(LegalDocument::class);
    }

    public function currentDocument(): HasOne
    {
        return $this->hasOne(LegalDocument::class)
            ->where('is_current', true)
            ->whereNotNull('published_at');
    }

    public function latestDocument(): HasOne
    {
        return $this->hasOne(LegalDocument::class)
            ->latest('published_at');
    }

    public function scopeRequired(Builder $query): Builder
    {
        return $query->where('is_required', true);
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderBy('name');
    }

    public function getCurrentVersion(): ?LegalDocument
    {
        return $this->currentDocument;
    }
}
