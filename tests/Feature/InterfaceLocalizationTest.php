<?php

use Vlados\LegalDocuments\Filament\Resources\LegalDocumentResource;
use Vlados\LegalDocuments\Filament\Resources\LegalDocumentTypeResource;

it('localizes admin resource labels without enabling content translations', function () {
    if (! class_exists(\Filament\Panel::class)) {
        $this->markTestSkipped('Filament integration dependency is absent.');
    }
    $this->configureTranslations(null);
    app()->setLocale('en');
    expect(LegalDocumentResource::getNavigationLabel())->toBe('Legal documents');
    expect(LegalDocumentTypeResource::getModelLabel())->toBe('Document type');
    app()->setLocale('bg');
    expect(LegalDocumentResource::getNavigationLabel())->toBe('Правни документи');
    expect(LegalDocumentTypeResource::getModelLabel())->toBe('Тип документ');
});
