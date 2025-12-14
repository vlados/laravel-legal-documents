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

    protected static ?string $navigationLabel = 'Правни документи';

    protected static ?string $modelLabel = 'Правен документ';

    protected static ?string $pluralModelLabel = 'Правни документи';

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
        return 'Чернови';
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
                                    ->label('Заглавие')
                                    ->placeholder('Въведете заглавие на документа')
                                    ->required()
                                    ->maxLength(255)
                                    ->autofocus()
                                    ->extraInputAttributes(['class' => 'text-xl font-semibold']),
                            ]),

                        // Content Section
                        Section::make('Съдържание')
                            ->icon('heroicon-o-document-text')
                            ->collapsible()
                            ->schema([
                                Forms\Components\RichEditor::make('content')
                                    ->label('')
                                    ->placeholder('Въведете съдържанието на документа...')
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
                        Section::make('Обобщение на промените')
                            ->icon('heroicon-o-clipboard-document-list')
                            ->description('Опишете накратко какво е променено спрямо предишната версия')
                            ->collapsible()
                            ->collapsed(fn (?LegalDocument $record) => $record === null)
                            ->schema([
                                Forms\Components\Textarea::make('summary_of_changes')
                                    ->label('')
                                    ->placeholder('Напр. "Добавена нова секция за защита на данните", "Актуализирани условия за използване"...')
                                    ->rows(3),
                            ]),
                    ]),

                // Sidebar (Right - 1 column)
                Grid::make(1)
                    ->columnSpan(1)
                    ->schema([
                        // Publish Section
                        Section::make('Публикуване')
                            ->icon('heroicon-o-globe-alt')
                            ->schema([
                                // Status Display
                                Forms\Components\Placeholder::make('status_display')
                                    ->label('Статус')
                                    ->content(function (?LegalDocument $record): string {
                                        if (! $record) {
                                            return '📝 Нов документ';
                                        }
                                        if ($record->is_current && $record->published_at) {
                                            return '✅ Публикуван (текуща версия)';
                                        }
                                        if ($record->published_at) {
                                            return '📄 Публикуван (стара версия)';
                                        }

                                        return '📝 Чернова';
                                    }),

                                Forms\Components\Placeholder::make('published_at_display')
                                    ->label('Публикувано на')
                                    ->visible(fn (?LegalDocument $record) => $record?->published_at !== null)
                                    ->content(fn (?LegalDocument $record) => $record?->published_at?->format('d.m.Y H:i')),

                                Forms\Components\Placeholder::make('acceptances_display')
                                    ->label('Приемания')
                                    ->visible(fn (?LegalDocument $record) => $record?->exists)
                                    ->content(fn (?LegalDocument $record) => $record ? $record->acceptances()->count().' потребители' : '0 потребители'),
                            ]),

                        // Document Type Section
                        Section::make('Тип документ')
                            ->icon('heroicon-o-tag')
                            ->schema([
                                Forms\Components\Select::make('legal_document_type_id')
                                    ->label('')
                                    ->relationship('type', 'name')
                                    ->required()
                                    ->searchable()
                                    ->preload()
                                    ->placeholder('Изберете тип документ')
                                    ->createOptionForm([
                                        Forms\Components\TextInput::make('name')
                                            ->label('Наименование')
                                            ->required(),
                                        Forms\Components\TextInput::make('slug')
                                            ->label('Идентификатор')
                                            ->required(),
                                    ]),

                                Forms\Components\TextInput::make('version')
                                    ->label('Версия')
                                    ->required()
                                    ->maxLength(50)
                                    ->placeholder('1.0')
                                    ->helperText('Напр. 1.0, 2.0, 2.1'),
                            ]),

                        // Settings Section
                        Section::make('Настройки')
                            ->icon('heroicon-o-cog-6-tooth')
                            ->collapsible()
                            ->schema([
                                Forms\Components\Toggle::make('requires_re_acceptance')
                                    ->label('Изисква повторно приемане')
                                    ->helperText('Потребителите ще трябва да приемат отново документа')
                                    ->default(true)
                                    ->inline(false),

                                Forms\Components\Toggle::make('notify_users')
                                    ->label('Уведоми потребителите')
                                    ->helperText('Изпрати имейл известия при публикуване')
                                    ->default(true)
                                    ->inline(false),
                            ]),

                        // Version History Section (only for existing records)
                        Section::make('История на версиите')
                            ->icon('heroicon-o-clock')
                            ->collapsible()
                            ->collapsed()
                            ->visible(fn (?LegalDocument $record) => $record?->exists && $record?->type)
                            ->schema([
                                Forms\Components\Placeholder::make('version_history')
                                    ->label('')
                                    ->content(function (?LegalDocument $record): string {
                                        if (! $record?->type) {
                                            return 'Няма налична история';
                                        }

                                        $versions = $record->type->documents()
                                            ->orderByDesc('published_at')
                                            ->limit(5)
                                            ->get();

                                        if ($versions->isEmpty()) {
                                            return 'Няма други версии';
                                        }

                                        $html = '<div class="space-y-2">';
                                        foreach ($versions as $version) {
                                            $isCurrent = $version->is_current ? ' <span class="text-success-600 dark:text-success-400">(текуща)</span>' : '';
                                            $isThis = $version->id === $record->id ? ' <span class="text-primary-600 dark:text-primary-400">← тази</span>' : '';
                                            $date = $version->published_at?->format('d.m.Y') ?? 'Чернова';
                                            $html .= "<div class=\"text-sm\">v{$version->version} - {$date}{$isCurrent}{$isThis}</div>";
                                        }
                                        $html .= '</div>';

                                        return $html;
                                    })
                                    ->extraAttributes(['class' => 'prose dark:prose-invert']),
                            ]),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('title')
                    ->label('Заглавие')
                    ->searchable()
                    ->sortable()
                    ->weight(FontWeight::SemiBold)
                    ->limit(50),

                Tables\Columns\TextColumn::make('version')
                    ->label('Версия')
                    ->badge()
                    ->color('gray')
                    ->sortable(),

                Tables\Columns\TextColumn::make('status')
                    ->label('Статус')
                    ->badge()
                    ->getStateUsing(function (LegalDocument $record): string {
                        if ($record->is_current && $record->published_at) {
                            return 'Текуща';
                        }
                        if ($record->published_at) {
                            return 'Публикувана';
                        }

                        return 'Чернова';
                    })
                    ->color(fn (string $state): string => match ($state) {
                        'Текуща' => 'success',
                        'Публикувана' => 'info',
                        'Чернова' => 'warning',
                        default => 'gray',
                    })
                    ->icon(fn (string $state): string => match ($state) {
                        'Текуща' => 'heroicon-o-check-circle',
                        'Публикувана' => 'heroicon-o-document-check',
                        'Чернова' => 'heroicon-o-pencil-square',
                        default => 'heroicon-o-document',
                    }),

                Tables\Columns\TextColumn::make('acceptances_count')
                    ->label('Приемания')
                    ->counts('acceptances')
                    ->badge()
                    ->color('gray')
                    ->icon('heroicon-o-users')
                    ->alignCenter(),

                Tables\Columns\TextColumn::make('published_at')
                    ->label('Публикувано')
                    ->dateTime('d.m.Y H:i')
                    ->sortable()
                    ->placeholder('—'),

                Tables\Columns\TextColumn::make('updated_at')
                    ->label('Обновено')
                    ->dateTime('d.m.Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('updated_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('legal_document_type_id')
                    ->label('Тип документ')
                    ->relationship('type', 'name')
                    ->preload()
                    ->searchable(),

                Tables\Filters\TernaryFilter::make('status')
                    ->label('Статус')
                    ->placeholder('Всички')
                    ->trueLabel('Публикувани')
                    ->falseLabel('Чернови')
                    ->queries(
                        true: fn (Builder $query) => $query->whereNotNull('published_at'),
                        false: fn (Builder $query) => $query->whereNull('published_at'),
                    ),

                Tables\Filters\TernaryFilter::make('is_current')
                    ->label('Текуща версия')
                    ->placeholder('Всички')
                    ->trueLabel('Само текущи')
                    ->falseLabel('Само стари'),
            ])
            ->actions([
                Actions\ActionGroup::make([
                    Actions\Action::make('publish')
                        ->label('Публикувай')
                        ->icon('heroicon-o-arrow-up-circle')
                        ->color('success')
                        ->requiresConfirmation()
                        ->modalIcon('heroicon-o-arrow-up-circle')
                        ->modalHeading('Публикуване на документа')
                        ->modalDescription(fn (LegalDocument $record) => "Това ще направи версия {$record->version} текуща. ".($record->notify_users ? 'Потребителите ще бъдат уведомени.' : ''))
                        ->modalSubmitActionLabel('Публикувай')
                        ->visible(fn (LegalDocument $record) => ! $record->is_current)
                        ->action(function (LegalDocument $record) {
                            $record->publish();

                            Notification::make()
                                ->title('Документът е публикуван успешно')
                                ->body("Версия {$record->version} е текущата версия.")
                                ->success()
                                ->send();
                        }),

                    Actions\Action::make('notify')
                        ->label('Изпрати известия')
                        ->icon('heroicon-o-bell-alert')
                        ->color('warning')
                        ->requiresConfirmation()
                        ->modalIcon('heroicon-o-bell-alert')
                        ->modalHeading('Изпращане на известия')
                        ->modalDescription('Ще бъдат изпратени известия на всички потребители, които не са приели този документ.')
                        ->modalSubmitActionLabel('Изпрати')
                        ->visible(fn (LegalDocument $record) => $record->is_current && $record->requires_re_acceptance)
                        ->action(function (LegalDocument $record) {
                            $record->notifyUsers();

                            Notification::make()
                                ->title('Известията са изпратени')
                                ->success()
                                ->send();
                        }),

                    Actions\Action::make('duplicate')
                        ->label('Създай нова версия')
                        ->icon('heroicon-o-document-duplicate')
                        ->color('gray')
                        ->form([
                            Forms\Components\TextInput::make('new_version')
                                ->label('Нова версия')
                                ->required()
                                ->placeholder('2.0'),
                        ])
                        ->action(function (LegalDocument $record, array $data) {
                            $newDocument = $record->replicate();
                            $newDocument->version = $data['new_version'];
                            $newDocument->is_current = false;
                            $newDocument->published_at = null;
                            $newDocument->save();

                            Notification::make()
                                ->title('Създадена е нова версия')
                                ->body("Версия {$data['new_version']} е създадена като чернова.")
                                ->success()
                                ->send();

                            return redirect(static::getUrl('edit', ['record' => $newDocument]));
                        }),

                    Actions\EditAction::make()
                        ->label('Редактирай'),

                    Actions\DeleteAction::make()
                        ->label('Изтрий'),
                ])
                    ->icon('heroicon-m-ellipsis-vertical')
                    ->tooltip('Действия'),
            ])
            ->bulkActions([
                Actions\BulkActionGroup::make([
                    Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->emptyStateHeading('Няма правни документи')
            ->emptyStateDescription('Създайте първия си правен документ, за да започнете.')
            ->emptyStateIcon('heroicon-o-document-text')
            ->emptyStateActions([
                Actions\Action::make('create')
                    ->label('Създай документ')
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
