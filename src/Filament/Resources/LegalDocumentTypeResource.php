<?php

namespace Vlados\LegalDocuments\Filament\Resources;

use Filament\Actions;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
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

    protected static ?string $navigationLabel = 'Типове документи';

    protected static ?string $modelLabel = 'Тип документ';

    protected static ?string $pluralModelLabel = 'Типове документи';

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
                        Section::make('Основна информация')
                            ->icon('heroicon-o-information-circle')
                            ->schema([
                                Forms\Components\TextInput::make('name')
                                    ->label('Наименование')
                                    ->placeholder('Напр. Политика за поверителност')
                                    ->required()
                                    ->maxLength(255)
                                    ->autofocus()
                                    ->live(onBlur: true)
                                    ->afterStateUpdated(function (Set $set, ?string $state, ?string $old) {
                                        if (blank($state)) {
                                            return;
                                        }

                                        $set('slug', Str::slug($state));
                                    }),

                                Forms\Components\TextInput::make('slug')
                                    ->label('Идентификатор (slug)')
                                    ->placeholder('privacy-policy')
                                    ->required()
                                    ->unique(ignoreRecord: true)
                                    ->maxLength(255)
                                    ->helperText('Уникален идентификатор за системата. Автоматично се генерира от наименованието.')
                                    ->rules(['alpha_dash']),

                                Forms\Components\Textarea::make('description')
                                    ->label('Описание')
                                    ->placeholder('Кратко описание на този тип документ...')
                                    ->rows(3)
                                    ->maxLength(1000)
                                    ->helperText('Това описание помага на администраторите да разберат предназначението на документа.'),
                            ]),
                    ]),

                // Sidebar (Right - 1 column)
                Grid::make(1)
                    ->columnSpan(1)
                    ->schema([
                        // Status Section
                        Section::make('Статус')
                            ->icon('heroicon-o-signal')
                            ->schema([
                                Forms\Components\Placeholder::make('documents_count_display')
                                    ->label('Версии')
                                    ->visible(fn (?LegalDocumentType $record) => $record?->exists)
                                    ->content(function (?LegalDocumentType $record): string {
                                        if (! $record) {
                                            return '0 версии';
                                        }
                                        $count = $record->documents()->count();

                                        return $count.' '.trans_choice('версия|версии|версии', $count);
                                    }),

                                Forms\Components\Placeholder::make('current_version_display')
                                    ->label('Текуща версия')
                                    ->visible(fn (?LegalDocumentType $record) => $record?->exists && $record?->currentDocument)
                                    ->content(fn (?LegalDocumentType $record) => $record?->currentDocument?->version ?? 'Няма публикувана версия'),

                                Forms\Components\Placeholder::make('created_at_display')
                                    ->label('Създаден')
                                    ->visible(fn (?LegalDocumentType $record) => $record?->exists)
                                    ->content(fn (?LegalDocumentType $record) => $record?->created_at?->format('d.m.Y H:i')),
                            ]),

                        // Settings Section
                        Section::make('Настройки')
                            ->icon('heroicon-o-cog-6-tooth')
                            ->schema([
                                Forms\Components\Toggle::make('is_required')
                                    ->label('Задължителен документ')
                                    ->helperText('Потребителите трябва да приемат този документ при регистрация')
                                    ->default(true)
                                    ->inline(false),

                                Forms\Components\TextInput::make('sort_order')
                                    ->label('Подредба')
                                    ->numeric()
                                    ->default(0)
                                    ->minValue(0)
                                    ->helperText('По-малките числа се показват първи'),
                            ]),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Наименование')
                    ->searchable()
                    ->sortable()
                    ->weight(FontWeight::SemiBold),

                Tables\Columns\TextColumn::make('slug')
                    ->label('Идентификатор')
                    ->searchable()
                    ->badge()
                    ->color('gray')
                    ->copyable()
                    ->copyMessage('Идентификаторът е копиран')
                    ->copyMessageDuration(1500),

                Tables\Columns\IconColumn::make('is_required')
                    ->label('Задължителен')
                    ->boolean()
                    ->trueIcon('heroicon-o-check-circle')
                    ->falseIcon('heroicon-o-x-circle')
                    ->trueColor('success')
                    ->falseColor('gray')
                    ->alignCenter(),

                Tables\Columns\TextColumn::make('currentDocument.version')
                    ->label('Текуща версия')
                    ->badge()
                    ->color(fn (?string $state) => $state ? 'success' : 'gray')
                    ->placeholder('Няма')
                    ->alignCenter(),

                Tables\Columns\TextColumn::make('documents_count')
                    ->label('Версии')
                    ->counts('documents')
                    ->badge()
                    ->color('gray')
                    ->icon('heroicon-o-document-duplicate')
                    ->alignCenter(),

                Tables\Columns\TextColumn::make('sort_order')
                    ->label('Подредба')
                    ->sortable()
                    ->alignCenter()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('updated_at')
                    ->label('Обновено')
                    ->dateTime('d.m.Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('sort_order')
            ->reorderable('sort_order')
            ->filters([
                Tables\Filters\TernaryFilter::make('is_required')
                    ->label('Задължителен')
                    ->placeholder('Всички')
                    ->trueLabel('Само задължителни')
                    ->falseLabel('Само незадължителни'),
            ])
            ->actions([
                Actions\ActionGroup::make([
                    Actions\Action::make('view_documents')
                        ->label('Виж документите')
                        ->icon('heroicon-o-document-text')
                        ->color('gray')
                        ->url(fn (LegalDocumentType $record) => LegalDocumentResource::getUrl('index', [
                            'tableFilters[legal_document_type_id][value]' => $record->id,
                        ])),

                    Actions\Action::make('create_document')
                        ->label('Създай нова версия')
                        ->icon('heroicon-o-plus-circle')
                        ->color('success')
                        ->url(fn (LegalDocumentType $record) => LegalDocumentResource::getUrl('create', [
                            'legal_document_type_id' => $record->id,
                        ])),

                    Actions\EditAction::make()
                        ->label('Редактирай'),

                    Actions\DeleteAction::make()
                        ->label('Изтрий')
                        ->before(function (LegalDocumentType $record, Actions\DeleteAction $action) {
                            if ($record->documents()->exists()) {
                                $action->cancel();
                                $action->failureNotificationTitle('Не може да се изтрие');
                                $action->failureNotification()?->body('Този тип има свързани документи. Първо изтрийте документите.');
                            }
                        }),
                ])
                    ->icon('heroicon-m-ellipsis-vertical')
                    ->tooltip('Действия'),
            ])
            ->bulkActions([
                Actions\BulkActionGroup::make([
                    Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->emptyStateHeading('Няма типове документи')
            ->emptyStateDescription('Създайте първия тип документ (напр. Политика за поверителност, Общи условия).')
            ->emptyStateIcon('heroicon-o-tag')
            ->emptyStateActions([
                Actions\Action::make('create')
                    ->label('Създай тип документ')
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
