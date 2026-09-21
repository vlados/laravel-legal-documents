<?php

namespace Vlados\LegalDocuments\Filament\Resources\LegalDocumentTypeResource\Pages;

use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Vlados\LegalDocuments\Filament\Resources\LegalDocumentTypeResource;

class EditLegalDocumentType extends EditRecord
{
    use \Vlados\LegalDocuments\Filament\Concerns\InteractsWithContentTranslations;
    protected static string $resource = LegalDocumentTypeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
