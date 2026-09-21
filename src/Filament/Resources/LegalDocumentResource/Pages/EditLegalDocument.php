<?php

namespace Vlados\LegalDocuments\Filament\Resources\LegalDocumentResource\Pages;

use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Vlados\LegalDocuments\Filament\Resources\LegalDocumentResource;
use Vlados\LegalDocuments\Models\LegalDocument;

class EditLegalDocument extends EditRecord
{
    use \Vlados\LegalDocuments\Filament\Concerns\InteractsWithContentTranslations;
    protected static string $resource = LegalDocumentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // Primary action - Publish (if not current)
            Actions\Action::make('publish')
                ->label(fn () => $this->record->is_current ? __('legal-documents::admin.published_short') : __('legal-documents::admin.publish'))
                ->icon('heroicon-o-arrow-up-circle')
                ->color(fn () => $this->record->is_current ? 'gray' : 'success')
                ->disabled(fn () => $this->record->is_current)
                ->requiresConfirmation()
                ->modalIcon('heroicon-o-arrow-up-circle')
                ->modalHeading(__('legal-documents::admin.publish_document'))
                ->modalDescription(fn () => __('legal-documents::admin.publish_confirmation', ['version' => $this->record->version]).' '.($this->record->notify_users ? __('legal-documents::admin.users_will_be_notified') : ''))
                ->modalSubmitActionLabel(__('legal-documents::admin.publish'))
                ->action(function () {
                    $this->record->publish();

                    Notification::make()
                        ->title(__('legal-documents::admin.published_success'))
                        ->body(__('legal-documents::admin.version_current', ['version' => $this->record->version]))
                        ->success()
                        ->send();

                    $this->refreshFormData(['is_current', 'published_at']);
                }),

            // Duplicate action - Create new version
            Actions\Action::make('duplicate')
                ->label(__('legal-documents::admin.new_version'))
                ->icon('heroicon-o-document-duplicate')
                ->color('gray')
                ->form([
                    \Filament\Forms\Components\TextInput::make('new_version')
                        ->label(__('legal-documents::admin.new_version'))
                        ->required()
                        ->placeholder('2.0')
                        ->default(fn () => $this->suggestNextVersion()),
                ])
                ->action(function (array $data) {
                    $newDocument = $this->record->createNewVersion($data['new_version']);

                    Notification::make()
                        ->title(__('legal-documents::admin.version_created'))
                        ->body(__('legal-documents::admin.version_draft', ['version' => $data['new_version']]))
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
        return __('legal-documents::admin.document_saved');
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('edit', ['record' => $this->record]);
    }
}
