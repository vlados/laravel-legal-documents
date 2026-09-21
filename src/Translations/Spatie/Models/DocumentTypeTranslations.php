<?php

namespace Vlados\LegalDocuments\Translations\Spatie\Models;

use Illuminate\Database\Eloquent\Model;
use Spatie\Translatable\HasTranslations;

class DocumentTypeTranslations extends Model
{
    use HasTranslations;

    protected $table = 'legal_document_type_spatie_translations';

    protected $guarded = [];

    public $translatable = ['name', 'description'];
}
