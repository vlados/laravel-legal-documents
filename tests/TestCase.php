<?php

namespace Vlados\LegalDocuments\Tests;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Orchestra\Testbench\TestCase as Orchestra;
use Vlados\LegalDocuments\LegalDocumentsServiceProvider;
use Vlados\LegalDocuments\Models\LegalDocument;
use Vlados\LegalDocuments\Models\LegalDocumentType;
use Vlados\LegalDocuments\Tests\Fixtures\User;

class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        $providers = [LegalDocumentsServiceProvider::class];
        if (class_exists(\Livewire\LivewireServiceProvider::class)) {
            $providers[] = \Livewire\LivewireServiceProvider::class;
        }
        if (class_exists(\Filament\FilamentServiceProvider::class)) {
            foreach ([\BladeUI\Icons\BladeIconsServiceProvider::class, \BladeUI\Heroicons\BladeHeroiconsServiceProvider::class,
                \Filament\Support\SupportServiceProvider::class, \Filament\Actions\ActionsServiceProvider::class,
                \Filament\Schemas\SchemasServiceProvider::class, \Filament\Forms\FormsServiceProvider::class,
                \Filament\Infolists\InfolistsServiceProvider::class, \Filament\Notifications\NotificationsServiceProvider::class,
                \Filament\Tables\TablesServiceProvider::class, \Filament\Widgets\WidgetsServiceProvider::class,
                \Filament\FilamentServiceProvider::class, \Vlados\LegalDocuments\Tests\Fixtures\PanelProvider::class] as $provider) {
                $providers[] = $provider;
            }
        }

        return $providers;
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite', 'database' => getenv('LEGAL_TRANSLATION_LIFECYCLE_DATABASE') ?: ':memory:', 'prefix' => '', 'foreign_key_constraints' => true,
        ]);
        if (getenv('LEGAL_PGSQL_TESTS') === '1') {
            if (getenv('LEGAL_MYSQL_TESTS') === '1') {
                throw new \RuntimeException('Run PostgreSQL and MySQL suites separately.');
            }
            $connection = [
                'driver' => 'pgsql', 'host' => getenv('LEGAL_PGSQL_HOST') ?: '127.0.0.1',
                'port' => getenv('LEGAL_PGSQL_PORT') ?: 5432,
                'database' => 'legal_documents_pgsql_test',
                'username' => getenv('LEGAL_PGSQL_USER') ?: 'postgres',
                'password' => getenv('LEGAL_PGSQL_PASSWORD') ?: 'test',
                'charset' => 'utf8', 'prefix' => '', 'search_path' => 'public', 'sslmode' => 'prefer',
            ];
            $app['config']->set('database.connections.testing', $connection);
            $app['config']->set('database.connections.concurrent', $connection);
        }
        $app['config']->set('legal-documents.frontend.enabled', false);
        $app['config']->set('legal-documents.notifications.queue', false);
        $app['config']->set('legal-documents.user_model', User::class);
        $app['config']->set('app.fallback_locale', 'en');
        $app['config']->set('app.key', 'base64:'.base64_encode(str_repeat('a', 32)));
        $app['config']->set('session.driver', 'array');
        $app['config']->set('legal-documents.frontend.layout', 'legal-test::layout');
    }

    protected function defineRoutes($router): void
    {
        $router->get('/', fn () => 'Home')->name('home');
        if (class_exists(\Livewire\Livewire::class)) {
            require __DIR__.'/../routes/web.php';
        }
    }

    protected function setUp(): void
    {
        parent::setUp();
        view()->addNamespace('legal-test', __DIR__.'/Fixtures/views');
    }

    protected function defineDatabaseMigrations(): void
    {
        if (getenv('LEGAL_TRANSLATION_LIFECYCLE_PHASE') === 'verify') {
            return;
        }
        if (getenv('LEGAL_PGSQL_TESTS') === '1') {
            // This opt-in suite owns only the dedicated legal_documents_pgsql_test database.
            Schema::dropAllTables();
        }
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email');
            $table->string('locale')->default('en');
            $table->timestamps();
        });
        foreach (glob(__DIR__.'/../database/migrations/*.php') as $path) {
            (require $path)->up();
        }
    }

    public function document(array $attributes = []): LegalDocument
    {
        $type = LegalDocumentType::create(['slug' => 'terms-'.LegalDocumentType::count(), 'name' => 'Terms']);

        return $type->documents()->create(array_merge([
            'version' => '1.0', 'title' => 'English terms', 'content' => '<p>Original English</p>',
            'summary_of_changes' => 'English changes', 'notify_users' => false,
        ], $attributes));
    }

    public function configureTranslations(?string $driver): void
    {
        config()->set('legal-documents.translations', [
            'driver' => $driver, 'source_locale' => 'en', 'locales' => ['en', 'bg', 'de'], 'fallback_locale' => null,
        ]);
    }
}
