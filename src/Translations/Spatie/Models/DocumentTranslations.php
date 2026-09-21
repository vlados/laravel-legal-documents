<?php

namespace Vlados\LegalDocuments\Translations\Spatie\Models;

use Illuminate\Database\Eloquent\Model;
use Spatie\Translatable\HasTranslations;

class DocumentTranslations extends Model
{
    use HasTranslations;

    protected $table = 'legal_document_spatie_translations';

    protected $guarded = [];

    public $translatable = ['title', 'content', 'summary_of_changes'];
}
