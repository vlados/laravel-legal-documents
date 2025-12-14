<?php

namespace Vlados\LegalDocuments\Filament\Resources\LegalDocumentResource\Pages;

use Filament\Resources\Pages\CreateRecord;
use Vlados\LegalDocuments\Filament\Resources\LegalDocumentResource;

class CreateLegalDocument extends CreateRecord
{
    protected static string $resource = LegalDocumentResource::class;

    protected function getCreatedNotificationTitle(): ?string
    {
        return 'Документът е създаден';
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('edit', ['record' => $this->record]);
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // Auto-set default values if not provided
        $data['is_current'] = false;
        $data['published_at'] = null;

        return $data;
    }
}
