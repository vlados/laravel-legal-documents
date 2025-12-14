<?php

namespace Vlados\LegalDocuments\Filament\Resources\LegalDocumentResource\Pages;

use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;
use Vlados\LegalDocuments\Filament\Resources\LegalDocumentResource;
use Vlados\LegalDocuments\Models\LegalDocument;

class ListLegalDocuments extends ListRecords
{
    protected static string $resource = LegalDocumentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->label('Нов документ')
                ->icon('heroicon-o-plus'),
        ];
    }

    public function getTabs(): array
    {
        return [
            'all' => Tab::make('Всички')
                ->badge(LegalDocument::count())
                ->badgeColor('gray'),

            'current' => Tab::make('Текущи')
                ->badge(LegalDocument::where('is_current', true)->count())
                ->badgeColor('success')
                ->icon('heroicon-o-check-circle')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('is_current', true)),

            'drafts' => Tab::make('Чернови')
                ->badge(LegalDocument::whereNull('published_at')->count())
                ->badgeColor('warning')
                ->icon('heroicon-o-pencil-square')
                ->modifyQueryUsing(fn (Builder $query) => $query->whereNull('published_at')),

            'archived' => Tab::make('Архивирани')
                ->badge(LegalDocument::whereNotNull('published_at')->where('is_current', false)->count())
                ->badgeColor('gray')
                ->icon('heroicon-o-archive-box')
                ->modifyQueryUsing(fn (Builder $query) => $query->whereNotNull('published_at')->where('is_current', false)),
        ];
    }
}
