<?php

namespace Vlados\LegalDocuments\Tests\PostgreSql;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PDOException;
use Vlados\LegalDocuments\Models\LegalDocument;
use Vlados\LegalDocuments\Tests\TestCase;

class TranslationTransactionsTest extends TestCase
{
    protected function setUp(): void
    {
        if (getenv('LEGAL_PGSQL_TESTS') !== '1' || ! trait_exists(\Spatie\Translatable\HasTranslations::class)) {
            $this->markTestSkipped('Requires the dedicated PostgreSQL service and Spatie test environment.');
        }
        parent::setUp();
        $this->app->register(\Spatie\Translatable\TranslatableServiceProvider::class);
        $this->configureTranslations('spatie');
        foreach (glob(__DIR__.'/../../database/migrations/spatie/*.php') as $path) {
            (require $path)->up();
        }
    }

    public function test_optional_migrations_can_be_rolled_back_and_reinstalled(): void
    {
        $paths = glob(__DIR__.'/../../database/migrations/spatie/*.php');
        foreach (array_reverse($paths) as $path) {
            (require $path)->down();
        }
        $this->assertFalse(Schema::hasTable('legal_document_spatie_translations'));
        $this->assertFalse(Schema::hasTable('legal_document_type_spatie_translations'));
        foreach ($paths as $path) {
            (require $path)->up();
        }
        $document = $this->document();
        $document->saveTranslation('bg', ['title' => 'Условия', 'content' => 'Текст']);
        $this->assertSame('Условия', $document->localized('bg')->values['title']);
    }

    public function test_read_committed_preserves_another_editors_locale(): void
    {
        $document = $this->document();
        $document->saveTranslation('bg', ['title' => 'Original BG', 'content' => 'Body']);
        $document->saveTranslation('de', ['title' => 'Original DE', 'content' => 'Body']);
        try {
            DB::transaction(function () use ($document) {
                DB::statement('SET TRANSACTION ISOLATION LEVEL READ COMMITTED');
                DB::table('legal_document_spatie_translations')->get();
                $this->otherEditor($document)->saveTranslation('de', ['title' => 'Current DE', 'content' => 'New German']);
                $document->saveTranslation('bg', ['title' => 'Current BG', 'content' => 'New Bulgarian']);
            });
            $this->assertSame('Current DE', $document->localized('de')->values['title']);
            $this->assertSame('Current BG', $document->localized('bg')->values['title']);
        } finally {
            DB::disconnect('concurrent');
        }
    }

    public function test_read_committed_copies_current_source_and_translations(): void
    {
        $document = $this->document();
        $document->saveTranslation('bg', ['title' => 'Original BG', 'content' => 'Body']);
        try {
            $copy = DB::transaction(function () use ($document) {
                DB::statement('SET TRANSACTION ISOLATION LEVEL READ COMMITTED');
                DB::table('legal_document_spatie_translations')->get();
                $other = $this->otherEditor($document);
                $other->getConnection()->transaction(function () use ($other) {
                    $other->saveTranslation('en', ['title' => 'Current source', 'content' => 'Source body']);
                    $other->saveTranslation('bg', ['title' => 'Current BG', 'content' => 'Current body']);
                });

                return $document->createNewVersion('2.0');
            });
            $this->assertSame('Current source', $copy->title);
            $this->assertSame('Current BG', $copy->localized('bg')->values['title']);
        } finally {
            DB::disconnect('concurrent');
        }
    }

    public function test_repeatable_read_aborts_a_stale_locale_write_without_losing_committed_content(): void
    {
        $this->assertStaleSnapshotAborts(copy: false);
    }

    public function test_repeatable_read_aborts_a_stale_version_copy_without_leaving_a_draft(): void
    {
        $this->assertStaleSnapshotAborts(copy: true);
    }

    private function assertStaleSnapshotAborts(bool $copy): void
    {
        $document = $this->document();
        $document->saveTranslation('bg', ['title' => 'Original BG', 'content' => 'Body']);
        $document->saveTranslation('de', ['title' => 'Original DE', 'content' => 'Body']);
        try {
            try {
                DB::transaction(function () use ($document, $copy) {
                    DB::statement('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');
                    DB::table('legal_document_spatie_translations')->get();
                    $this->otherEditor($document)->saveTranslation('de', ['title' => 'Current DE', 'content' => 'New German']);
                    if ($copy) {
                        $document->createNewVersion('2.0');
                    } else {
                        $document->saveTranslation('bg', ['title' => 'Stale write', 'content' => 'Must roll back']);
                    }
                });
                $this->fail('A stale locking read must fail with a serialization error.');
            } catch (PDOException $exception) {
                // Nested Laravel transactions wrap serialization failures in DeadlockException.
                while ($exception->getPrevious()) {
                    $exception = $exception->getPrevious();
                }
                $this->assertSame('40001', (string) $exception->getCode());
            }
            $this->assertSame(0, DB::connection()->transactionLevel());
            $this->assertSame(1, LegalDocument::count());
            $this->assertSame(1, DB::table('legal_document_spatie_translations')->count());
            $this->assertSame('Original BG', $document->localized('bg')->values['title']);
            $this->assertSame('Current DE', $document->localized('de')->values['title']);

            // Retrying the complete operation starts a fresh snapshot.
            if ($copy) {
                $draft = $document->createNewVersion('2.0');
                $this->assertSame('Current DE', $draft->localized('de')->values['title']);
            } else {
                $document->saveTranslation('bg', ['title' => 'Retried BG', 'content' => 'Body']);
                $this->assertSame('Retried BG', $document->localized('bg')->values['title']);
                $this->assertSame('Current DE', $document->localized('de')->values['title']);
            }
        } finally {
            DB::disconnect('concurrent');
        }
    }

    private function otherEditor(LegalDocument $document): LegalDocument
    {
        return (new LegalDocument)->setConnection('concurrent')->newQuery()->findOrFail($document->id);
    }
}
