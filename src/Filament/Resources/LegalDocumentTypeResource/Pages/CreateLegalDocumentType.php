<?php

namespace Vlados\LegalDocuments\Filament\Resources\LegalDocumentTypeResource\Pages;

use Filament\Resources\Pages\CreateRecord;
use Vlados\LegalDocuments\Filament\Resources\LegalDocumentTypeResource;

class CreateLegalDocumentType extends CreateRecord
{
    use \Vlados\LegalDocuments\Filament\Concerns\InteractsWithContentTranslations;
    protected static string $resource = LegalDocumentTypeResource::class;
}
