<?php

namespace Vlados\LegalDocuments\Http\Livewire;

use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Livewire\Component;
use Vlados\LegalDocuments\Translations\ContentTranslator;
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
            ->get();
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
        $translator = app(ContentTranslator::class);
        $localizedDocument = $this->document->localized();
        $versions = $this->versions;
        $otherTypes = LegalDocumentType::query()
            ->whereKeyNot($this->documentType->getKey())
            ->whereHas('currentDocument')->ordered()->get();
        $versionContents = $translator->localizeMany($versions->all());
        $otherTypeContents = $translator->localizeMany($otherTypes->all());

        return view('legal-documents::view-document', [
            'localizedDocument' => $localizedDocument,
            'localizedType' => $this->documentType->localized(),
            'versions' => $versions,
            'versionContents' => array_combine($versions->modelKeys(), $versionContents),
            'otherTypes' => $otherTypes,
            'otherTypeContents' => array_combine($otherTypes->modelKeys(), $otherTypeContents),
        ])
            ->section('title', $localizedDocument->values['title'])
            ->layout(config('legal-documents.frontend.layout', 'layouts.app'));
    }
}
