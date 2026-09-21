<?php

namespace Vlados\LegalDocuments\Http\Livewire;

use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Vlados\LegalDocuments\Translations\ContentTranslator;
use Vlados\LegalDocuments\Models\LegalDocument;

class AcceptDocuments extends Component
{
    public array $acceptedIds = [];

    public ?int $viewingDocumentId = null;

    public function mount(): void
    {
        // Redirect if no documents to accept
        if (! Auth::user()?->needsToAcceptDocuments()) {
            $this->redirectToIntendedUrl();
        }
    }

    public function getPendingDocumentsProperty(): Collection
    {
        return Auth::user()?->getPendingDocuments() ?? collect();
    }

    public function getViewingDocumentProperty(): ?LegalDocument
    {
        if (! $this->viewingDocumentId) {
            return null;
        }

        return $this->pendingDocuments->firstWhere('id', $this->viewingDocumentId);
    }

    public function viewDocument(int $documentId): void
    {
        $this->viewingDocumentId = $documentId;
    }

    public function closeDocument(): void
    {
        $this->viewingDocumentId = null;
    }

    public function toggleAcceptance(int $documentId): void
    {
        if (in_array($documentId, $this->acceptedIds)) {
            $this->acceptedIds = array_values(array_diff($this->acceptedIds, [$documentId]));
        } else {
            $this->acceptedIds[] = $documentId;
        }
    }

    public function acceptAll(): void
    {
        $this->acceptedIds = $this->pendingDocuments->pluck('id')->toArray();
    }

    public function submit(): void
    {
        $user = Auth::user();

        if (! $user) {
            return;
        }

        // Validate all required documents are accepted
        $pendingIds = $this->pendingDocuments->pluck('id')->toArray();
        $missingIds = array_diff($pendingIds, $this->acceptedIds);

        if (! empty($missingIds)) {
            $this->addError('acceptance', __('legal-documents::legal-documents.accept_all_required'));

            return;
        }

        // Accept all documents
        $user->acceptDocuments(
            $this->acceptedIds,
            request()->ip(),
            request()->userAgent()
        );

        $this->redirectToIntendedUrl();
    }

    protected function redirectToIntendedUrl(): void
    {
        $this->redirect(
            session()->pull('url.intended', route('home'))
        );
    }

    public function render(): View
    {
        $documents = $this->pendingDocuments;
        $translator = app(ContentTranslator::class);
        $contents = $translator->localizeMany($documents->all());
        $types = $translator->localizeMany($documents->map(fn ($document) => $document->type)->all());

        return view('legal-documents::accept-documents', [
            'pendingDocuments' => $documents,
            'documentContents' => array_combine($documents->modelKeys(), $contents),
            'typeContents' => array_combine($documents->modelKeys(), $types),
            'viewingDocument' => $this->viewingDocument,
        ]);
    }
}
