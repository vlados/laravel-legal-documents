<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\ServiceProvider;
use Vlados\LegalDocuments\Contracts\TranslationDriver;
use Vlados\LegalDocuments\Exceptions\InvalidTranslationConfiguration;
use Vlados\LegalDocuments\LegalDocumentsServiceProvider;
use Vlados\LegalDocuments\Translations\ContentTranslator;

beforeEach(function () {
    if (! trait_exists(\Spatie\Translatable\HasTranslations::class)) {
        $this->markTestSkipped('Spatie integration dependency is absent.');
    }
    $this->app->register(\Spatie\Translatable\TranslatableServiceProvider::class);
    $this->configureTranslations('spatie');
    foreach (glob(__DIR__.'/../../database/migrations/spatie/*.php') as $path) {
        (require $path)->up();
    }
});

it('round trips translations without changing source bytes or other languages', function () {
    $document = $this->document();
    $source = $document->fresh()->getAttributes();
    $document->saveTranslation('bg', ['title' => 'Условия', 'content' => '<p>Български &amp; текст</p>', 'summary_of_changes' => 'Промени']);
    $document->saveTranslation('de', ['title' => 'Bedingungen', 'content' => '<p>Deutsch</p>']);
    $document->saveTranslation('bg', ['title' => 'Нови условия', 'content' => '<p>Нов текст</p>']);
    $document = $document->fresh();
    expect($document->getAttributes())->toBe($source);
    expect($document->localized('de')->values['content'])->toBe('<p>Deutsch</p>');
    expect($document->localized('bg')->values['title'])->toBe('Нови условия');
    expect($document->localized('bg')->values['summary_of_changes'])->toBeNull();
    $document->forgetTranslation('bg');
    expect($document->localized('bg')->locale)->toBe('en');
});

it('translates type fields and cascades companion deletion', function () {
    $type = $this->document()->type;
    $type->saveTranslation('bg', ['name' => 'Условия', 'description' => 'Описание']);
    expect($type->fresh()->localized('bg')->values['description'])->toBe('Описание');
    $type->saveTranslation('bg', ['name' => 'Условия']);
    expect($type->fresh()->localized('bg')->values['description'])->toBeNull();
    $type->delete();
    expect(DB::table('legal_document_type_spatie_translations')->count())->toBe(0);
});

it('prefers the Spatie fallback locale while ignoring per-field callbacks and arbitrary fallback', function () {
    $document = $this->document();
    $document->saveTranslation('bg', ['title' => 'BG', 'content' => 'Body']);
    config()->set('legal-documents.translations.fallback_locale', 'en');
    \Spatie\Translatable\Facades\Translatable::fallback(fallbackLocale: 'bg', fallbackAny: true, missingKeyCallback: fn () => 'Unexpected');
    expect($document->localized('de')->values)->toBe(['title' => 'BG', 'content' => 'Body', 'summary_of_changes' => null]);
    expect($document->localized('de')->locale)->toBe('bg');
    $document->forgetTranslation('bg');
    $document->saveTranslation('de', ['title' => 'DE', 'content' => 'Deutsch']);
    expect($document->localized('fr')->values['title'])->toBe('English terms');
});

it('uses the package fallback when the Spatie fallback is unset or null', function (bool $explicitNull) {
    if ($explicitNull) {
        \Spatie\Translatable\Facades\Translatable::fallback(fallbackLocale: null);
    }
    config()->set('legal-documents.translations.fallback_locale', 'bg');
    $document = $this->document();
    $document->saveTranslation('bg', ['title' => 'BG', 'content' => 'Body']);
    expect($document->localized('de')->locale)->toBe('bg');
})->with([false, true]);

it('reads updated Spatie fallback settings without changing the source locale', function () {
    $document = $this->document();
    $document->saveTranslation('bg', ['title' => 'BG', 'content' => 'Body']);
    expect($document->localized('de')->locale)->toBe('en');
    \Spatie\Translatable\Facades\Translatable::fallback(fallbackLocale: 'bg');
    app()->setLocale('de');
    expect($document->localized()->locale)->toBe('bg');
    expect(app(ContentTranslator::class)->sourceLocale())->toBe('en');
});

it('does not inherit Spatie settings for a custom or disabled driver', function (?string $driver) {
    \Spatie\Translatable\Facades\Translatable::fallback(fallbackLocale: 'fr');
    $this->configureTranslations($driver);
    config()->set('legal-documents.translations.fallback_locale', 'bg');
    $this->app->singleton(\Vlados\LegalDocuments\Tests\Fixtures\ArrayTranslationDriver::class);
    $document = $this->document();
    if ($driver !== null) {
        $document->saveTranslation('bg', ['title' => 'BG', 'content' => 'Body']);
    }
    expect($document->localized('de')->locale)->toBe($driver === null ? 'en' : 'bg');
})->with([null, \Vlados\LegalDocuments\Tests\Fixtures\ArrayTranslationDriver::class]);

it('requires the inherited fallback to be an enabled locale', function () {
    \Spatie\Translatable\Facades\Translatable::fallback(fallbackLocale: 'fr');
    $this->document()->localized('de');
})->throws(InvalidTranslationConfiguration::class, 'fallback_locale');

it('batches 20 rows into a fixed number of translation queries', function () {
    $records = [];
    for ($i = 0; $i < 20; $i++) {
        $record = $this->document();
        $record->saveTranslation('bg', ['title' => 'Title '.$i, 'content' => 'Body']);
        $records[] = $record;
    }
    DB::enableQueryLog();
    DB::flushQueryLog();
    $results = app(ContentTranslator::class)->localizeMany($records, 'bg');
    expect(count(DB::getQueryLog()))->toBeLessThanOrEqual(2);
    expect($results[19]->values['title'])->toBe('Title 19');
});

it('disables additional content without deleting translations', function () {
    $document = $this->document();
    $document->saveTranslation('bg', ['title' => 'BG', 'content' => 'Body']);
    config()->set('legal-documents.translations.driver', null);
    $this->app->forgetScopedInstances();
    expect($document->localized('bg')->locale)->toBe('en');
    expect(DB::table('legal_document_spatie_translations')->count())->toBe(1);
});

it('publishes optional migrations separately from core migrations', function () {
    $core = ServiceProvider::pathsToPublish(LegalDocumentsServiceProvider::class, 'legal-documents-migrations');
    $optional = ServiceProvider::pathsToPublish(LegalDocumentsServiceProvider::class, 'legal-documents-spatie-migrations');
    expect(count($core))->toBe(4);
    expect(count($optional))->toBe(2);
    foreach (array_keys($core) as $path) {
        expect($path)->not->toContain('/spatie/');
    }
});

it('reports missing translation migrations instead of hiding the failure', function () {
    \Illuminate\Support\Facades\Schema::drop('legal_document_spatie_translations');
    $this->document()->localized('bg');
})->throws(InvalidTranslationConfiguration::class, 'legal-documents-spatie-migrations');

it('copies Spatie translations and preserves unique version constraints', function () {
    $document = $this->document();
    $document->saveTranslation('bg', ['title' => 'BG', 'content' => 'Body']);
    $copy = $document->createNewVersion('2.0');
    expect($copy->localized('bg')->values['title'])->toBe('BG');
    expect(fn () => $document->createNewVersion('2.0'))->toThrow(\Illuminate\Database\QueryException::class);
    expect(DB::table('legal_document_spatie_translations')->count())->toBe(2);
});
