<?php

namespace Vlados\LegalDocuments\Filament\Resources\LegalDocumentResource\Pages;

use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Vlados\LegalDocuments\Filament\Resources\LegalDocumentResource;
use Vlados\LegalDocuments\Models\LegalDocument;

class EditLegalDocument extends EditRecord
{
    protected static string $resource = LegalDocumentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // Primary action - Publish (if not current)
            Actions\Action::make('publish')
                ->label(fn () => $this->record->is_current ? 'Публикуван' : 'Публикувай')
                ->icon('heroicon-o-arrow-up-circle')
                ->color(fn () => $this->record->is_current ? 'gray' : 'success')
                ->disabled(fn () => $this->record->is_current)
                ->requiresConfirmation()
                ->modalIcon('heroicon-o-arrow-up-circle')
                ->modalHeading('Публикуване на документа')
                ->modalDescription(fn () => "Това ще направи версия {$this->record->version} текуща. ".($this->record->notify_users ? 'Потребителите ще бъдат уведомени.' : ''))
                ->modalSubmitActionLabel('Публикувай')
                ->action(function () {
                    $this->record->publish();

                    Notification::make()
                        ->title('Документът е публикуван успешно')
                        ->body("Версия {$this->record->version} е текущата версия.")
                        ->success()
                        ->send();

                    $this->refreshFormData(['is_current', 'published_at']);
                }),

            // Duplicate action - Create new version
            Actions\Action::make('duplicate')
                ->label('Нова версия')
                ->icon('heroicon-o-document-duplicate')
                ->color('gray')
                ->form([
                    \Filament\Forms\Components\TextInput::make('new_version')
                        ->label('Нова версия')
                        ->required()
                        ->placeholder('2.0')
                        ->default(fn () => $this->suggestNextVersion()),
                ])
                ->action(function (array $data) {
                    $newDocument = $this->record->replicate();
                    $newDocument->version = $data['new_version'];
                    $newDocument->is_current = false;
                    $newDocument->published_at = null;
                    $newDocument->save();

                    Notification::make()
                        ->title('Създадена е нова версия')
                        ->body("Версия {$data['new_version']} е създадена като чернова.")
                        ->success()
                        ->send();

                    return redirect(LegalDocumentResource::getUrl('edit', ['record' => $newDocument]));
                }),

            Actions\DeleteAction::make()
                ->icon('heroicon-o-trash'),
        ];
    }

    protected function suggestNextVersion(): string
    {
        $currentVersion = $this->record->version;

        // Try to parse semantic version (e.g., 1.0, 2.1)
        if (preg_match('/^(\d+)\.(\d+)$/', $currentVersion, $matches)) {
            $major = (int) $matches[1];
            $minor = (int) $matches[2];

            return $major.'.'.($minor + 1);
        }

        // Try to parse simple version (e.g., 1, 2)
        if (preg_match('/^(\d+)$/', $currentVersion, $matches)) {
            return (string) ((int) $matches[1] + 1);
        }

        // Default: append .1
        return $currentVersion.'.1';
    }

    protected function getSavedNotificationTitle(): ?string
    {
        return 'Документът е запазен';
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('edit', ['record' => $this->record]);
    }
}
