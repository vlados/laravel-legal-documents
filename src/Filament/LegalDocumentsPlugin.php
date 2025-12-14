<?php

namespace Vlados\LegalDocuments\Filament;

use Filament\Contracts\Plugin;
use Filament\Panel;
use Vlados\LegalDocuments\Filament\Resources\LegalDocumentResource;
use Vlados\LegalDocuments\Filament\Resources\LegalDocumentTypeResource;
use Vlados\LegalDocuments\LegalDocumentsServiceProvider;

class LegalDocumentsPlugin implements Plugin
{
    protected string $navigationGroup = 'Settings';

    protected int $navigationSort = 100;

    public static function make(): static
    {
        if (! LegalDocumentsServiceProvider::hasFilament()) {
            throw new \RuntimeException(
                'Filament is not installed. Please install filament/filament to use the LegalDocumentsPlugin.'
            );
        }

        return app(static::class);
    }

    public static function get(): static
    {
        return filament(app(static::class)->getId());
    }

    public function getId(): string
    {
        return 'legal-documents';
    }

    public function navigationGroup(string $group): static
    {
        $this->navigationGroup = $group;

        return $this;
    }

    public function navigationSort(int $sort): static
    {
        $this->navigationSort = $sort;

        return $this;
    }

    public function getNavigationGroup(): string
    {
        return $this->navigationGroup;
    }

    public function getNavigationSort(): int
    {
        return $this->navigationSort;
    }

    public function register(Panel $panel): void
    {
        if (! config('legal-documents.filament.enabled', true)) {
            return;
        }

        $panel->resources([
            LegalDocumentTypeResource::class,
            LegalDocumentResource::class,
        ]);
    }

    public function boot(Panel $panel): void
    {
        //
    }
}
