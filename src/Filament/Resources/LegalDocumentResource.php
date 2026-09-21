<?php

namespace Vlados\LegalDocuments\Filament\Resources;

use Filament\Actions;
use Filament\Forms;
use Filament\Infolists;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Split;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Alignment;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Enums\IconPosition;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Vlados\LegalDocuments\Filament\LegalDocumentsPlugin;
use Vlados\LegalDocuments\Filament\Resources\LegalDocumentResource\Pages;
use Vlados\LegalDocuments\Models\LegalDocument;

class LegalDocumentResource extends Resource
{
    protected static ?string $model = LegalDocument::class;

    protected static ?string $slug = 'legal/documents';

    protected static ?string $recordTitleAttribute = 'title';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-document-text';

    public static function getNavigationLabel(): string
    {
        return __('legal-documents::admin.documents');
    }

    public static function getModelLabel(): string
    {
        return __('legal-documents::admin.document');
    }

    public static function getPluralModelLabel(): string
    {
        return __('legal-documents::admin.documents');
    }

    public static function getNavigationGroup(): ?string
    {
        return filament()->hasPlugin('legal-documents')
            ? LegalDocumentsPlugin::get()->getNavigationGroup()
            : config('legal-documents.filament.navigation_group', 'Settings');
    }

    public static function getNavigationSort(): ?int
    {
        $baseSort = filament()->hasPlugin('legal-documents')
            ? LegalDocumentsPlugin::get()->getNavigationSort()
            : config('legal-documents.filament.navigation_sort', 100);

        return $baseSort + 1;
    }

    public static function getNavigationBadge(): ?string
    {
        return static::getModel()::whereNull('published_at')->count() ?: null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        return __('legal-documents::admin.drafts');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->columns(3)
            ->components([
                // Main Content Area (Left - 2 columns)
                Grid::make(1)
                    ->columnSpan(2)
                    ->schema([
                        // Title Section
                        Section::make()
                            ->schema([
                                Forms\Components\TextInput::make('title')
                                    ->label(__('legal-documents::admin.title'))
                                    ->placeholder(__('legal-documents::admin.title_placeholder'))
                                    ->required()
                                    ->maxLength(255)
                                    ->autofocus()
                                    ->extraInputAttributes(['class' => 'text-xl font-semibold']),
                            ]),

                        // Content Section
                        Section::make(__('legal-documents::admin.content'))
                            ->icon('heroicon-o-document-text')
                            ->collapsible()
                            ->schema([
                                Forms\Components\RichEditor::make('content')
                                    ->label(__('legal-documents::admin.content'))
                                    ->placeholder(__('legal-documents::admin.content_placeholder'))
                                    ->required()
                                    ->toolbarButtons([
                                        'blockquote',
                                        'bold',
                                        'bulletList',
                                        'h2',
                                        'h3',
                                        'italic',
                                        'link',
                                        'orderedList',
                                        'redo',
                                        'strike',
                                        'underline',
                                        'undo',
                                    ])
                                    ->columnSpanFull(),
                            ]),

                        // Summary of Changes Section
                        Section::make(__('legal-documents::admin.summary'))
                            ->icon('heroicon-o-clipboard-document-list')
                            ->description(__('legal-documents::admin.summary_help'))
                            ->collapsible()
                            ->collapsed(fn (?LegalDocument $record) => $record === null)
                            ->schema([
                                Forms\Components\Textarea::make('summary_of_changes')
                                    ->label(__('legal-documents::admin.summary'))
                                    ->placeholder(__('legal-documents::admin.summary_placeholder'))
                                    ->rows(3),
                            ]),
                    ]),

                // Sidebar (Right - 1 column)
                Grid::make(1)
                    ->columnSpan(1)
                    ->schema([
                        // Publish Section
                        Section::make(__('legal-documents::admin.publishing'))
                            ->icon('heroicon-o-globe-alt')
                            ->schema([
                                // Status Display
                                Forms\Components\Placeholder::make('status_display')
                                    ->label(__('legal-documents::admin.status'))
                                    ->content(function (?LegalDocument $record): string {
                                        if (! $record) {
                                            return __('legal-documents::admin.new_status');
                                        }
                                        if ($record->is_current && $record->published_at) {
                                            return __('legal-documents::admin.current_status');
                                        }
                                        if ($record->published_at) {
                                            return __('legal-documents::admin.old_status');
                                        }

                                        return __('legal-documents::admin.draft_status');
                                    }),

                                Forms\Components\Placeholder::make('published_at_display')
                                    ->label(__('legal-documents::admin.published_at'))
                                    ->visible(fn (?LegalDocument $record) => $record?->published_at !== null)
                                    ->content(fn (?LegalDocument $record) => $record?->published_at?->format('d.m.Y H:i')),

                                Forms\Components\Placeholder::make('acceptances_display')
                                    ->label(__('legal-documents::admin.acceptances'))
                                    ->visible(fn (?LegalDocument $record) => $record?->exists)
                                    ->content(fn (?LegalDocument $record) => trans_choice('legal-documents::admin.users_count', $record?->acceptances()->count() ?? 0, ['count' => $record?->acceptances()->count() ?? 0])),
                            ]),

                        // Document Type Section
                        Section::make(__('legal-documents::admin.type'))
                            ->icon('heroicon-o-tag')
                            ->schema([
                                Forms\Components\Select::make('legal_document_type_id')
                                    ->label(__('legal-documents::admin.type'))
                                    ->relationship('type', 'name')
                                    ->required()
                                    ->searchable()
                                    ->preload()
                                    ->placeholder(__('legal-documents::admin.choose_type'))
                                    ->createOptionForm([
                                        Forms\Components\TextInput::make('name')
                                            ->label(__('legal-documents::admin.name'))
                                            ->required(),
                                        Forms\Components\TextInput::make('slug')
                                            ->label(__('legal-documents::admin.identifier'))
                                            ->required(),
                                    ]),

                                Forms\Components\TextInput::make('version')
                                    ->label(__('legal-documents::admin.version'))
                                    ->required()
                                    ->maxLength(50)
                                    ->placeholder('1.0')
                                    ->helperText(__('legal-documents::admin.version_help')),
                            ]),

                        // Settings Section
                        Section::make(__('legal-documents::admin.settings'))
                            ->icon('heroicon-o-cog-6-tooth')
                            ->collapsible()
                            ->schema([
                                Forms\Components\Toggle::make('requires_re_acceptance')
                                    ->label(__('legal-documents::admin.reacceptance'))
                                    ->helperText(__('legal-documents::admin.reacceptance_help'))
                                    ->default(true)
                                    ->inline(false),

                                Forms\Components\Toggle::make('notify_users')
                                    ->label(__('legal-documents::admin.notify_users'))
                                    ->helperText(__('legal-documents::admin.notify_help'))
                                    ->default(true)
                                    ->inline(false),
                            ]),

                        // Version History Section (only for existing records)
                        Section::make(__('legal-documents::admin.version_history'))
                            ->icon('heroicon-o-clock')
                            ->collapsible()
                            ->collapsed()
                            ->visible(fn (?LegalDocument $record) => $record?->exists && $record?->type)
                            ->schema([
                                Forms\Components\Placeholder::make('version_history')
                                    ->label('')
                                    ->content(function (?LegalDocument $record): string {
                                        if (! $record?->type) {
                                            return __('legal-documents::admin.no_history');
                                        }

                                        $versions = $record->type->documents()
                                            ->orderByDesc('published_at')
                                            ->limit(5)
                                            ->get();

                                        if ($versions->isEmpty()) {
                                            return __('legal-documents::admin.no_other_versions');
                                        }

                                        $html = '<div class="space-y-2">';
                                        foreach ($versions as $version) {
                                            $isCurrent = $version->is_current ? ' <span class="text-success-600 dark:text-success-400">'.e(__('legal-documents::admin.current_marker')).'</span>' : '';
                                            $isThis = $version->id === $record->id ? ' <span class="text-primary-600 dark:text-primary-400">'.e(__('legal-documents::admin.viewing_marker')).'</span>' : '';
                                            $date = $version->published_at?->format('d.m.Y') ?? __('legal-documents::admin.draft');
                                            $html .= "<div class=\"text-sm\">v{$version->version} - {$date}{$isCurrent}{$isThis}</div>";
                                        }
                                        $html .= '</div>';

                                        return $html;
                                    })
                                    ->extraAttributes(['class' => 'prose dark:prose-invert']),
                            ]),
                    ]),
                ...\Vlados\LegalDocuments\Filament\ContentTranslationFields::make(new LegalDocument),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->description(__('legal-documents::admin.source_controls'))
            ->columns([
                Tables\Columns\TextColumn::make('title')
                    ->label(__('legal-documents::admin.title'))
                    ->searchable()
                    ->sortable()
                    ->weight(FontWeight::SemiBold)
                    ->limit(50),

                Tables\Columns\TextColumn::make('version')
                    ->label(__('legal-documents::admin.version'))
                    ->badge()
                    ->color('gray')
                    ->sortable(),

                Tables\Columns\TextColumn::make('status')
                    ->label(__('legal-documents::admin.status'))
                    ->badge()
                    ->getStateUsing(function (LegalDocument $record): string {
                        if ($record->is_current && $record->published_at) {
                            return __('legal-documents::admin.current');
                        }
                        if ($record->published_at) {
                            return __('legal-documents::admin.published');
                        }

                        return __('legal-documents::admin.draft');
                    })
                    ->color(fn (string $state): string => match ($state) {
                        __('legal-documents::admin.current') => 'success',
                        __('legal-documents::admin.published') => 'info',
                        __('legal-documents::admin.draft') => 'warning',
                        default => 'gray',
                    })
                    ->icon(fn (string $state): string => match ($state) {
                        __('legal-documents::admin.current') => 'heroicon-o-check-circle',
                        __('legal-documents::admin.published') => 'heroicon-o-document-check',
                        __('legal-documents::admin.draft') => 'heroicon-o-pencil-square',
                        default => 'heroicon-o-document',
                    }),

                Tables\Columns\TextColumn::make('acceptances_count')
                    ->label(__('legal-documents::admin.acceptances'))
                    ->counts('acceptances')
                    ->badge()
                    ->color('gray')
                    ->icon('heroicon-o-users')
                    ->alignCenter(),

                Tables\Columns\TextColumn::make('published_at')
                    ->label(__('legal-documents::admin.published_on'))
                    ->dateTime('d.m.Y H:i')
                    ->sortable()
                    ->placeholder('—'),

                Tables\Columns\TextColumn::make('updated_at')
                    ->label(__('legal-documents::admin.updated'))
                    ->dateTime('d.m.Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('updated_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('legal_document_type_id')
                    ->label(__('legal-documents::admin.type'))
                    ->relationship('type', 'name')
                    ->preload()
                    ->searchable(),

                Tables\Filters\TernaryFilter::make('status')
                    ->label(__('legal-documents::admin.status'))
                    ->placeholder(__('legal-documents::admin.all'))
                    ->trueLabel(__('legal-documents::admin.published_plural'))
                    ->falseLabel(__('legal-documents::admin.drafts'))
                    ->queries(
                        true: fn (Builder $query) => $query->whereNotNull('published_at'),
                        false: fn (Builder $query) => $query->whereNull('published_at'),
                    ),

                Tables\Filters\TernaryFilter::make('is_current')
                    ->label(__('legal-documents::admin.current_version'))
                    ->placeholder(__('legal-documents::admin.all'))
                    ->trueLabel(__('legal-documents::admin.only_current'))
                    ->falseLabel(__('legal-documents::admin.only_old')),
            ])
            ->actions([
                Actions\ActionGroup::make([
                    Actions\Action::make('publish')
                        ->label(__('legal-documents::admin.publish'))
                        ->icon('heroicon-o-arrow-up-circle')
                        ->color('success')
                        ->requiresConfirmation()
                        ->modalIcon('heroicon-o-arrow-up-circle')
                        ->modalHeading(__('legal-documents::admin.publish_document'))
                        ->modalDescription(fn (LegalDocument $record) => __('legal-documents::admin.publish_confirmation', ['version' => $record->version]).' '.($record->notify_users ? __('legal-documents::admin.users_will_be_notified') : ''))
                        ->modalSubmitActionLabel(__('legal-documents::admin.publish'))
                        ->visible(fn (LegalDocument $record) => ! $record->is_current)
                        ->action(function (LegalDocument $record) {
                            $record->publish();

                            Notification::make()
                                ->title(__('legal-documents::admin.published_success'))
                                ->body(__('legal-documents::admin.version_current', ['version' => $record->version]))
                                ->success()
                                ->send();
                        }),

                    Actions\Action::make('notify')
                        ->label(__('legal-documents::admin.send_notifications'))
                        ->icon('heroicon-o-bell-alert')
                        ->color('warning')
                        ->requiresConfirmation()
                        ->modalIcon('heroicon-o-bell-alert')
                        ->modalHeading(__('legal-documents::admin.sending_notifications'))
                        ->modalDescription(__('legal-documents::admin.notification_confirmation'))
                        ->modalSubmitActionLabel(__('legal-documents::admin.send'))
                        ->visible(fn (LegalDocument $record) => $record->is_current && $record->requires_re_acceptance)
                        ->action(function (LegalDocument $record) {
                            $record->notifyUsers();

                            Notification::make()
                                ->title(__('legal-documents::admin.notifications_sent'))
                                ->success()
                                ->send();
                        }),

                    Actions\Action::make('duplicate')
                        ->label(__('legal-documents::admin.create_version'))
                        ->icon('heroicon-o-document-duplicate')
                        ->color('gray')
                        ->form([
                            Forms\Components\TextInput::make('new_version')
                                ->label(__('legal-documents::admin.new_version'))
                                ->required()
                                ->placeholder('2.0'),
                        ])
                        ->action(function (LegalDocument $record, array $data) {
                            $newDocument = $record->createNewVersion($data['new_version']);

                            Notification::make()
                                ->title(__('legal-documents::admin.version_created'))
                                ->body(__('legal-documents::admin.version_draft', ['version' => $data['new_version']]))
                                ->success()
                                ->send();

                            return redirect(static::getUrl('edit', ['record' => $newDocument]));
                        }),

                    Actions\EditAction::make()
                        ->label(__('legal-documents::admin.edit')),

                    Actions\DeleteAction::make()
                        ->label(__('legal-documents::admin.delete')),
                ])
                    ->icon('heroicon-m-ellipsis-vertical')
                    ->tooltip(__('legal-documents::admin.actions')),
            ])
            ->bulkActions([
                Actions\BulkActionGroup::make([
                    Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->emptyStateHeading(__('legal-documents::admin.no_documents'))
            ->emptyStateDescription(__('legal-documents::admin.no_documents_help'))
            ->emptyStateIcon('heroicon-o-document-text')
            ->emptyStateActions([
                Actions\Action::make('create')
                    ->label(__('legal-documents::admin.create_document'))
                    ->url(static::getUrl('create'))
                    ->icon('heroicon-o-plus')
                    ->button(),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListLegalDocuments::route('/'),
            'create' => Pages\CreateLegalDocument::route('/create'),
            'edit' => Pages\EditLegalDocument::route('/{record}/edit'),
        ];
    }
}
