<?php

namespace Vlados\LegalDocuments;

use Illuminate\Support\ServiceProvider;
use Vlados\LegalDocuments\Contracts\TranslationDriver;
use Vlados\LegalDocuments\Exceptions\InvalidTranslationConfiguration;
use Vlados\LegalDocuments\Translations\ContentTranslator;
use Vlados\LegalDocuments\Translations\SingleLanguageDriver;
use Vlados\LegalDocuments\Translations\Spatie\SpatieTranslationDriver;
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

        $this->app->scoped(TranslationDriver::class, function ($app) {
            $driver = config('legal-documents.translations.driver');
            if ($driver === null || ($driver === 'spatie' && ! trait_exists(\Spatie\Translatable\HasTranslations::class))) {
                return new SingleLanguageDriver;
            }
            if ($driver === 'spatie') {
                return $app->make(SpatieTranslationDriver::class);
            }
            if (! is_string($driver) || ! is_a($driver, TranslationDriver::class, true)) {
                throw new InvalidTranslationConfiguration('translations.driver must be null, spatie, or a class implementing TranslationDriver.');
            }

            return $app->make($driver);
        });
        $this->app->scoped(ContentTranslator::class);
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
            $coreMigrations = [];
            foreach (glob(__DIR__.'/../database/migrations/*.php') as $migration) {
                $coreMigrations[$migration] = database_path('migrations/'.basename($migration));
            }
            $this->publishesMigrations($coreMigrations, 'legal-documents-migrations');

            $translationMigrations = [];
            foreach (glob(__DIR__.'/../database/migrations/spatie/*.php') as $migration) {
                $translationMigrations[$migration] = database_path('migrations/'.basename($migration));
            }
            $this->publishesMigrations($translationMigrations, 'legal-documents-spatie-migrations');

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
        $this->callAfterResolving('livewire', function ($livewire) {
            // Register a resolver for missing components (Livewire 4 compatible)
            $livewire->resolveMissingComponent(function (string $name) {
                return match ($name) {
                    'legal-documents::accept-documents',
                    'vlados.legal-documents.http.livewire.accept-documents' => AcceptDocuments::class,
                    'legal-documents::view-document',
                    'vlados.legal-documents.http.livewire.view-legal-document' => ViewLegalDocument::class,
                    default => null,
                };
            });
        });
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
