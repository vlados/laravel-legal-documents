<?php

namespace Vlados\LegalDocuments\Filament\Resources;

use Filament\Actions;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Enums\FontWeight;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Str;
use Vlados\LegalDocuments\Filament\LegalDocumentsPlugin;
use Vlados\LegalDocuments\Filament\Resources\LegalDocumentTypeResource\Pages;
use Vlados\LegalDocuments\Models\LegalDocumentType;

class LegalDocumentTypeResource extends Resource
{
    protected static ?string $model = LegalDocumentType::class;

    protected static ?string $slug = 'legal/document-types';

    protected static ?string $recordTitleAttribute = 'name';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-tag';

    public static function getNavigationLabel(): string
    {
        return __('legal-documents::admin.types');
    }

    public static function getModelLabel(): string
    {
        return __('legal-documents::admin.type');
    }

    public static function getPluralModelLabel(): string
    {
        return __('legal-documents::admin.types');
    }

    public static function getNavigationGroup(): ?string
    {
        return filament()->hasPlugin('legal-documents')
            ? LegalDocumentsPlugin::get()->getNavigationGroup()
            : config('legal-documents.filament.navigation_group', 'Settings');
    }

    public static function getNavigationSort(): ?int
    {
        return filament()->hasPlugin('legal-documents')
            ? LegalDocumentsPlugin::get()->getNavigationSort()
            : config('legal-documents.filament.navigation_sort', 100);
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
                        Section::make(__('legal-documents::admin.basic_information'))
                            ->icon('heroicon-o-information-circle')
                            ->schema([
                                Forms\Components\TextInput::make('name')
                                    ->label(__('legal-documents::admin.name'))
                                    ->placeholder(__('legal-documents::admin.name_placeholder'))
                                    ->required()
                                    ->maxLength(255)
                                    ->autofocus()
                                    ->live(onBlur: true)
                                    ->afterStateUpdated(function (Set $set, Get $get, ?string $state, string $operation) {
                                        if ($operation !== 'create' || blank($state) || filled($get('slug'))) {
                                            return;
                                        }

                                        $set('slug', Str::slug($state));
                                    }),

                                Forms\Components\TextInput::make('slug')
                                    ->label(__('legal-documents::admin.slug'))
                                    ->placeholder('privacy-policy')
                                    ->required()
                                    ->unique(ignoreRecord: true)
                                    ->maxLength(255)
                                    ->helperText(__('legal-documents::admin.slug_help'))
                                    ->rules(['alpha_dash']),

                                Forms\Components\Textarea::make('description')
                                    ->label(__('legal-documents::admin.description'))
                                    ->placeholder(__('legal-documents::admin.description_placeholder'))
                                    ->rows(3)
                                    ->maxLength(1000)
                                    ->helperText(__('legal-documents::admin.description_help')),
                            ]),
                    ]),

                // Sidebar (Right - 1 column)
                Grid::make(1)
                    ->columnSpan(1)
                    ->schema([
                        // Status Section
                        Section::make(__('legal-documents::admin.status'))
                            ->icon('heroicon-o-signal')
                            ->schema([
                                Forms\Components\Placeholder::make('documents_count_display')
                                    ->label(__('legal-documents::admin.versions'))
                                    ->visible(fn (?LegalDocumentType $record) => $record?->exists)
                                    ->content(function (?LegalDocumentType $record): string {
                                        if (! $record) {
                                            return trans_choice('legal-documents::admin.versions_count', 0, ['count' => 0]);
                                        }
                                        $count = $record->documents()->count();

                                        return trans_choice('legal-documents::admin.versions_count', $count, ['count' => $count]);
                                    }),

                                Forms\Components\Placeholder::make('current_version_display')
                                    ->label(__('legal-documents::admin.current_version'))
                                    ->visible(fn (?LegalDocumentType $record) => $record?->exists && $record?->currentDocument)
                                    ->content(fn (?LegalDocumentType $record) => $record?->currentDocument?->version ?? __('legal-documents::admin.no_published_version')),

                                Forms\Components\Placeholder::make('created_at_display')
                                    ->label(__('legal-documents::admin.created'))
                                    ->visible(fn (?LegalDocumentType $record) => $record?->exists)
                                    ->content(fn (?LegalDocumentType $record) => $record?->created_at?->format('d.m.Y H:i')),
                            ]),

                        // Settings Section
                        Section::make(__('legal-documents::admin.settings'))
                            ->icon('heroicon-o-cog-6-tooth')
                            ->schema([
                                Forms\Components\Toggle::make('is_required')
                                    ->label(__('legal-documents::admin.required_document'))
                                    ->helperText(__('legal-documents::admin.required_help'))
                                    ->default(true)
                                    ->inline(false)
                                    ->live(),

                                Forms\Components\Select::make('required_for_roles')
                                    ->label(__('legal-documents::admin.required_roles'))
                                    ->helperText(__('legal-documents::admin.roles_help'))
                                    ->multiple()
                                    ->options(fn () => LegalDocumentType::getAvailableRoles())
                                    ->visible(fn () => config('legal-documents.roles.enabled', false))
                                    ->disabled(fn (Get $get) => $get('is_required'))
                                    ->placeholder(__('legal-documents::admin.choose_roles')),

                                Forms\Components\TextInput::make('sort_order')
                                    ->label(__('legal-documents::admin.sort_order'))
                                    ->numeric()
                                    ->default(0)
                                    ->minValue(0)
                                    ->helperText(__('legal-documents::admin.sort_help')),
                            ]),
                    ]),
                ...\Vlados\LegalDocuments\Filament\ContentTranslationFields::make(new LegalDocumentType),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->description(__('legal-documents::admin.source_controls'))
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label(__('legal-documents::admin.name'))
                    ->searchable()
                    ->sortable()
                    ->weight(FontWeight::SemiBold),

                Tables\Columns\TextColumn::make('slug')
                    ->label(__('legal-documents::admin.identifier'))
                    ->searchable()
                    ->badge()
                    ->color('gray')
                    ->copyable()
                    ->copyMessage(__('legal-documents::admin.identifier_copied'))
                    ->copyMessageDuration(1500),

                Tables\Columns\IconColumn::make('is_required')
                    ->label(__('legal-documents::admin.required'))
                    ->boolean()
                    ->trueIcon('heroicon-o-check-circle')
                    ->falseIcon('heroicon-o-x-circle')
                    ->trueColor('success')
                    ->falseColor('gray')
                    ->alignCenter(),

                Tables\Columns\TextColumn::make('required_for_roles')
                    ->label(__('legal-documents::admin.for_roles'))
                    ->badge()
                    ->color('warning')
                    ->separator(', ')
                    ->placeholder(__('legal-documents::admin.all'))
                    ->visible(fn () => config('legal-documents.roles.enabled', false))
                    ->toggleable(),

                Tables\Columns\TextColumn::make('currentDocument.version')
                    ->label(__('legal-documents::admin.current_version'))
                    ->badge()
                    ->color(fn (?string $state) => $state ? 'success' : 'gray')
                    ->placeholder(__('legal-documents::admin.none'))
                    ->alignCenter(),

                Tables\Columns\TextColumn::make('documents_count')
                    ->label(__('legal-documents::admin.versions'))
                    ->counts('documents')
                    ->badge()
                    ->color('gray')
                    ->icon('heroicon-o-document-duplicate')
                    ->alignCenter(),

                Tables\Columns\TextColumn::make('sort_order')
                    ->label(__('legal-documents::admin.sort_order'))
                    ->sortable()
                    ->alignCenter()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('updated_at')
                    ->label(__('legal-documents::admin.updated'))
                    ->dateTime('d.m.Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('sort_order')
            ->reorderable('sort_order')
            ->filters([
                Tables\Filters\TernaryFilter::make('is_required')
                    ->label(__('legal-documents::admin.required'))
                    ->placeholder(__('legal-documents::admin.all'))
                    ->trueLabel(__('legal-documents::admin.only_required'))
                    ->falseLabel(__('legal-documents::admin.only_optional')),
            ])
            ->actions([
                Actions\ActionGroup::make([
                    Actions\Action::make('view_documents')
                        ->label(__('legal-documents::admin.view_documents'))
                        ->icon('heroicon-o-document-text')
                        ->color('gray')
                        ->url(fn (LegalDocumentType $record) => LegalDocumentResource::getUrl('index', [
                            'tableFilters[legal_document_type_id][value]' => $record->id,
                        ])),

                    Actions\Action::make('create_document')
                        ->label(__('legal-documents::admin.create_version'))
                        ->icon('heroicon-o-plus-circle')
                        ->color('success')
                        ->url(fn (LegalDocumentType $record) => LegalDocumentResource::getUrl('create', [
                            'legal_document_type_id' => $record->id,
                        ])),

                    Actions\EditAction::make()
                        ->label(__('legal-documents::admin.edit')),

                    Actions\DeleteAction::make()
                        ->label(__('legal-documents::admin.delete'))
                        ->before(function (LegalDocumentType $record, Actions\DeleteAction $action) {
                            if ($record->documents()->exists()) {
                                $action->cancel();
                                $action->failureNotificationTitle(__('legal-documents::admin.cannot_delete'));
                                $action->failureNotification()?->body(__('legal-documents::admin.has_documents'));
                            }
                        }),
                ])
                    ->icon('heroicon-m-ellipsis-vertical')
                    ->tooltip(__('legal-documents::admin.actions')),
            ])
            ->bulkActions([
                Actions\BulkActionGroup::make([
                    Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->emptyStateHeading(__('legal-documents::admin.no_types'))
            ->emptyStateDescription(__('legal-documents::admin.no_types_help'))
            ->emptyStateIcon('heroicon-o-tag')
            ->emptyStateActions([
                Actions\Action::make('create')
                    ->label(__('legal-documents::admin.create_type'))
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
            'index' => Pages\ListLegalDocumentTypes::route('/'),
            'create' => Pages\CreateLegalDocumentType::route('/create'),
            'edit' => Pages\EditLegalDocumentType::route('/{record}/edit'),
        ];
    }
}
