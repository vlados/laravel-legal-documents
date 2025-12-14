<?php

namespace Vlados\LegalDocuments;

use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;
use Vlados\LegalDocuments\Http\Livewire\AcceptDocuments;
use Vlados\LegalDocuments\Http\Livewire\ViewLegalDocument;

class LegalDocumentsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__.'/../config/legal-documents.php',
            'legal-documents'
        );
    }

    public function boot(): void
    {
        $this->registerPublishables();
        $this->registerViews();
        $this->registerTranslations();
        $this->registerLivewireComponents();
        $this->registerRoutes();
    }

    protected function registerPublishables(): void
    {
        if ($this->app->runningInConsole()) {
            // Config
            $this->publishes([
                __DIR__.'/../config/legal-documents.php' => config_path('legal-documents.php'),
            ], 'legal-documents-config');

            // Migrations
            $this->publishesMigrations([
                __DIR__.'/../database/migrations' => database_path('migrations'),
            ], 'legal-documents-migrations');

            // Views
            $this->publishes([
                __DIR__.'/../resources/views' => resource_path('views/vendor/legal-documents'),
            ], 'legal-documents-views');

            // Translations
            $this->publishes([
                __DIR__.'/../resources/lang' => lang_path('vendor/legal-documents'),
            ], 'legal-documents-lang');

            // All
            $this->publishes([
                __DIR__.'/../config/legal-documents.php' => config_path('legal-documents.php'),
                __DIR__.'/../resources/views' => resource_path('views/vendor/legal-documents'),
                __DIR__.'/../resources/lang' => lang_path('vendor/legal-documents'),
            ], 'legal-documents');
        }
    }

    protected function registerViews(): void
    {
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'legal-documents');
    }

    protected function registerTranslations(): void
    {
        $this->loadTranslationsFrom(__DIR__.'/../resources/lang', 'legal-documents');
    }

    protected function registerLivewireComponents(): void
    {
        if (class_exists(Livewire::class)) {
            Livewire::component('legal-documents::accept-documents', AcceptDocuments::class);
            Livewire::component('legal-documents::view-document', ViewLegalDocument::class);
        }
    }

    protected function registerRoutes(): void
    {
        if (config('legal-documents.frontend.enabled', true)) {
            $this->loadRoutesFrom(__DIR__.'/../routes/web.php');
        }
    }

    /**
     * Check if Filament is installed.
     */
    public static function hasFilament(): bool
    {
        return class_exists(\Filament\Panel::class);
    }
}
