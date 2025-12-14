<?php

use Illuminate\Support\Facades\Route;
use Vlados\LegalDocuments\Http\Livewire\AcceptDocuments;
use Vlados\LegalDocuments\Http\Livewire\ViewLegalDocument;

Route::middleware(config('legal-documents.frontend.middleware', ['web']))
    ->prefix(config('legal-documents.frontend.prefix', 'legal'))
    ->group(function () {
        // Accept documents (requires auth)
        Route::get('/accept', AcceptDocuments::class)
            ->middleware('auth')
            ->name('legal.accept');

        // View documents (public)
        Route::get('/{slug}', ViewLegalDocument::class)
            ->name('legal.show');

        Route::get('/{slug}/version/{version}', ViewLegalDocument::class)
            ->name('legal.show.version');
    });
