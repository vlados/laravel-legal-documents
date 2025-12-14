<?php

namespace Vlados\LegalDocuments\Filament\Resources\LegalDocumentTypeResource\Pages;

use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Vlados\LegalDocuments\Filament\Resources\LegalDocumentTypeResource;

class ListLegalDocumentTypes extends ListRecords
{
    protected static string $resource = LegalDocumentTypeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
