<?php

namespace Vlados\LegalDocuments\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Route;
use Vlados\LegalDocuments\Models\LegalDocument;

class LegalDocumentUpdated extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public LegalDocument $document
    ) {}

    public function via(object $notifiable): array
    {
        return config('legal-documents.notifications.channels', ['mail', 'database']);
    }

    public function toMail(object $notifiable): MailMessage
    {
        $documentTitle = $this->document->title;
        $typeName = $this->document->type->name;
        $summaryOfChanges = $this->document->summary_of_changes;
        $acceptanceRoute = config('legal-documents.acceptance_route', 'legal.accept');

        $message = (new MailMessage)
            ->subject(__('legal-documents::legal-documents.document_updated_subject', ['title' => $typeName]))
            ->greeting(__('legal-documents::legal-documents.document_updated_greeting'))
            ->line(__('legal-documents::legal-documents.document_updated_intro', ['title' => $typeName]))
            ->line(__('legal-documents::legal-documents.version', ['version' => $this->document->version]));

        if ($summaryOfChanges) {
            $message->line(__('legal-documents::legal-documents.document_updated_changes'))
                ->line($summaryOfChanges);
        }

        if ($this->document->requires_re_acceptance && Route::has($acceptanceRoute)) {
            $message->line(__('legal-documents::legal-documents.document_updated_action'))
                ->action(__('legal-documents::legal-documents.document_updated_button'), route($acceptanceRoute));
        }

        return $message->line(__('legal-documents::legal-documents.document_updated_thanks'));
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'legal_document_updated',
            'document_id' => $this->document->id,
            'document_type_id' => $this->document->legal_document_type_id,
            'document_type_slug' => $this->document->type->slug,
            'document_type_name' => $this->document->type->name,
            'document_title' => $this->document->title,
            'version' => $this->document->version,
            'requires_re_acceptance' => $this->document->requires_re_acceptance,
            'summary_of_changes' => $this->document->summary_of_changes,
        ];
    }

    public function toDatabase(object $notifiable): array
    {
        return $this->toArray($notifiable);
    }
}
