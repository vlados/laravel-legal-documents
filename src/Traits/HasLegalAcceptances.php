<?php

namespace Vlados\LegalDocuments\Traits;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Request;
use Vlados\LegalDocuments\Models\LegalDocument;
use Vlados\LegalDocuments\Models\LegalDocumentAcceptance;
use Vlados\LegalDocuments\Models\LegalDocumentType;

trait HasLegalAcceptances
{
    public function legalAcceptances(): HasMany
    {
        return $this->hasMany(LegalDocumentAcceptance::class);
    }

    public function hasAcceptedDocument(LegalDocument $document): bool
    {
        return $this->legalAcceptances()
            ->where('legal_document_id', $document->id)
            ->exists();
    }

    public function getDocumentAcceptance(LegalDocument $document): ?LegalDocumentAcceptance
    {
        return $this->legalAcceptances()
            ->where('legal_document_id', $document->id)
            ->first();
    }

    public function hasAcceptedLatest(string $typeSlug): bool
    {
        $type = LegalDocumentType::where('slug', $typeSlug)->first();

        if (! $type || ! $type->currentDocument) {
            return true; // No document to accept
        }

        return $this->hasAcceptedDocument($type->currentDocument);
    }

    /**
     * Get pending documents for this user based on their roles.
     * Falls back to is_required only if roles are disabled.
     */
    public function getPendingDocuments(): Collection
    {
        $acceptedDocumentIds = $this->legalAcceptances()
            ->pluck('legal_document_id')
            ->toArray();

        return LegalDocument::query()
            ->current()
            ->published()
            ->requiresAcceptance()
            ->whereHas('type', fn ($q) => $q->requiredForUser($this))
            ->whereNotIn('id', $acceptedDocumentIds)
            ->with('type')
            ->get();
    }

    /**
     * Get pending documents for specific roles (for preview/onboarding).
     * Useful when user hasn't been assigned roles yet.
     */
    public function getPendingDocumentsForRoles(array $roleNames): Collection
    {
        $acceptedDocumentIds = $this->legalAcceptances()
            ->pluck('legal_document_id')
            ->toArray();

        return LegalDocument::query()
            ->current()
            ->published()
            ->requiresAcceptance()
            ->whereHas('type', fn ($q) => $q->requiredForRolesPreview($roleNames))
            ->whereNotIn('id', $acceptedDocumentIds)
            ->with('type')
            ->get();
    }

    /**
     * Get all required documents for specific roles (including already accepted).
     * Useful for onboarding to show all documents regardless of acceptance status.
     */
    public function getRequiredDocumentsForRoles(array $roleNames): Collection
    {
        return LegalDocument::query()
            ->current()
            ->published()
            ->whereHas('type', fn ($q) => $q->requiredForRolesPreview($roleNames))
            ->with('type')
            ->get();
    }

    public function needsToAcceptDocuments(): bool
    {
        return $this->getPendingDocuments()->isNotEmpty();
    }

    /**
     * Check if user needs to accept documents for specific roles.
     */
    public function needsToAcceptDocumentsForRoles(array $roleNames): bool
    {
        return $this->getPendingDocumentsForRoles($roleNames)->isNotEmpty();
    }

    public function acceptDocument(
        LegalDocument $document,
        ?string $ipAddress = null,
        ?string $userAgent = null
    ): LegalDocumentAcceptance {
        $trackIp = config('legal-documents.audit.track_ip', true);
        $trackUserAgent = config('legal-documents.audit.track_user_agent', true);

        return $this->legalAcceptances()->updateOrCreate(
            ['legal_document_id' => $document->id],
            [
                'accepted_at' => now(),
                'ip_address' => $trackIp ? ($ipAddress ?? Request::ip()) : null,
                'user_agent' => $trackUserAgent ? ($userAgent ?? Request::userAgent()) : null,
            ]
        );
    }

    public function acceptDocuments(
        array $documentIds,
        ?string $ipAddress = null,
        ?string $userAgent = null
    ): void {
        $documents = LegalDocument::whereIn('id', $documentIds)->get();

        foreach ($documents as $document) {
            $this->acceptDocument($document, $ipAddress, $userAgent);
        }
    }

    public function getAcceptanceHistory(): Collection
    {
        return $this->legalAcceptances()
            ->with(['document.type'])
            ->orderByDesc('accepted_at')
            ->get();
    }
}
