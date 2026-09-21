<?php

namespace Vlados\LegalDocuments\Tests\MySql;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Vlados\LegalDocuments\Models\LegalDocument;
use Vlados\LegalDocuments\Tests\TestCase;

class TranslationLockingTest extends TestCase
{
    protected function setUp(): void
    {
        if (getenv('LEGAL_MYSQL_TESTS') !== '1' || ! trait_exists(\Spatie\Translatable\HasTranslations::class)) {
            $this->markTestSkipped('Requires the dedicated MySQL service and Spatie test environment.');
        }
        parent::setUp();
        $this->app->register(\Spatie\Translatable\TranslatableServiceProvider::class);
        $this->configureTranslations('spatie');
    }

    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);
        $connection = [
            'driver' => 'mysql', 'host' => getenv('LEGAL_MYSQL_HOST') ?: '127.0.0.1',
            'port' => getenv('LEGAL_MYSQL_PORT') ?: 3306,
            'database' => 'legal_documents_test',
            'username' => getenv('LEGAL_MYSQL_USER') ?: 'root',
            'password' => getenv('LEGAL_MYSQL_PASSWORD') ?: 'test',
            'charset' => 'utf8mb4', 'collation' => 'utf8mb4_unicode_ci', 'prefix' => '', 'strict' => true,
        ];
        $app['config']->set('database.connections.testing', $connection);
        $app['config']->set('database.connections.concurrent', $connection);
    }

    protected function defineDatabaseMigrations(): void
    {
        // This opt-in suite owns only the dedicated legal_documents_test database.
        Schema::dropAllTables();
        parent::defineDatabaseMigrations();
        foreach (glob(__DIR__.'/../../database/migrations/spatie/*.php') as $path) {
            (require $path)->up();
        }
    }

    public function test_locale_writes_use_current_rows_even_inside_an_existing_snapshot(): void
    {
        $document = $this->document();
        $document->saveTranslation('bg', ['title' => 'Original BG', 'content' => 'Body']);
        $document->saveTranslation('de', ['title' => 'Original DE', 'content' => 'Body']);
        $connection = DB::connection();
        $connection->statement('SET SESSION TRANSACTION ISOLATION LEVEL REPEATABLE READ');
        $connection->beginTransaction();
        try {
            // Establish A's snapshot before B commits another language.
            $connection->table('legal_document_spatie_translations')->get();
            $otherEditor = (new LegalDocument)->setConnection('concurrent')->newQuery()->findOrFail($document->id);
            $otherEditor->saveTranslation('de', ['title' => 'Concurrent DE', 'content' => 'New German']);
            $document->saveTranslation('bg', ['title' => 'Changed BG', 'content' => 'New Bulgarian']);
            $connection->commit();
            $this->assertSame('Concurrent DE', $document->localized('de')->values['title']);
            $this->assertSame('Changed BG', $document->localized('bg')->values['title']);
        } finally {
            while ($connection->transactionLevel() > 0) {
                $connection->rollBack();
            }
            DB::disconnect('concurrent');
        }
    }
}
