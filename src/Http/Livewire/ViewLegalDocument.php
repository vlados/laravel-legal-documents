<?php

namespace Vlados\LegalDocuments\Http\Livewire;

use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Livewire\Component;
use Vlados\LegalDocuments\Models\LegalDocument;
use Vlados\LegalDocuments\Models\LegalDocumentType;

class ViewLegalDocument extends Component
{
    public LegalDocumentType $documentType;

    public ?LegalDocument $document = null;

    public ?string $selectedVersion = null;

    public function mount(string $slug, ?string $version = null): void
    {
        $this->documentType = LegalDocumentType::where('slug', $slug)->firstOrFail();

        if ($version) {
            $this->selectedVersion = $version;
            $this->document = $this->documentType->documents()
                ->where('version', $version)
                ->whereNotNull('published_at')
                ->firstOrFail();
        } else {
            $this->document = $this->documentType->currentDocument;

            if (! $this->document) {
                abort(404, __('legal-documents::legal-documents.no_published_document'));
            }

            $this->selectedVersion = $this->document->version;
        }
    }

    public function getVersionsProperty(): Collection
    {
        return $this->documentType->documents()
            ->whereNotNull('published_at')
            ->orderByDesc('published_at')
            ->get(['id', 'version', 'published_at', 'is_current']);
    }

    public function selectVersion(string $version): void
    {
        $this->redirect(
            route('legal.show.version', [
                'slug' => $this->documentType->slug,
                'version' => $version,
            ])
        );
    }

    public function render(): View
    {
        return view('legal-documents::view-document')
            ->layout(config('legal-documents.frontend.layout', 'layouts.app'));
    }
}
