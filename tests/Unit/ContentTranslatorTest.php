<?php

use Vlados\LegalDocuments\Contracts\TranslationDriver;
use Vlados\LegalDocuments\Exceptions\InvalidTranslation;
use Vlados\LegalDocuments\Exceptions\InvalidTranslationConfiguration;
use Vlados\LegalDocuments\Exceptions\TranslationDriverUnavailable;
use Vlados\LegalDocuments\Models\LegalDocument;
use Vlados\LegalDocuments\Translations\ContentTranslator;
use Vlados\LegalDocuments\Tests\Fixtures\ArrayTranslationDriver;

beforeEach(function () {
    $this->app->singleton(ArrayTranslationDriver::class);
    $this->configureTranslations(ArrayTranslationDriver::class);
});

it('preserves scalar attributes and resolves an explicit or current locale', function () {
    $document = $this->document();
    $document->saveTranslation('bg', ['title' => 'Условия', 'content' => '<p>Български</p>']);
    expect($document->title)->toBe('English terms');
    expect($document->localized('bg')->values['title'])->toBe('Условия');
    app()->setLocale('bg');
    expect($document->localized()->locale)->toBe('bg');
    app()->setLocale('en');
    expect($document->localized()->values['title'])->toBe('English terms');
});

it('falls back as a complete document and never mixes optional summaries', function () {
    $document = $this->document();
    app(ArrayTranslationDriver::class)->seed($document, ['bg' => ['title' => 'Условия', 'content' => null]]);
    expect($document->localized('bg')->locale)->toBe('en');
    $document->saveTranslation('bg', ['title' => 'Условия', 'content' => '<p>Български</p>']);
    expect($document->localized('bg')->values['summary_of_changes'])->toBeNull();
    config()->set('legal-documents.translations.fallback_locale', 'bg');
    expect($document->localized('de')->locale)->toBe('bg');
    expect($document->localized('fr')->isFallback)->toBeTrue();
});

it('does not consult a backend for the source locale', function () {
    $document = $this->document();
    $document->localized('en');
    expect(app(ArrayTranslationDriver::class)->reads)->toBe(0);
});

it('edits and removes one additional locale without touching another', function () {
    $document = $this->document();
    $document->saveTranslation('bg', ['title' => 'BG', 'content' => 'body bg']);
    $document->saveTranslation('de', ['title' => 'DE', 'content' => 'body de']);
    $document->saveTranslation('bg', ['title' => 'Updated', 'content' => 'new body']);
    expect($document->localized('de')->values['title'])->toBe('DE');
    $document->forgetTranslation('bg');
    expect($document->localized('bg')->locale)->toBe('en');
});

it('updates the source without making an additional translation', function () {
    $document = $this->document();
    $document->saveTranslation('en', ['title' => 'Revised', 'content' => '<p>Revised</p>']);
    expect($document->fresh()->title)->toBe('Revised');
    expect(app(ArrayTranslationDriver::class)->readAll($document))->toBe([]);
});

it('saves source text without persisting or discarding unrelated pending changes', function () {
    $document = $this->document();
    $document->notify_users = true;
    $document->version = 'pending';
    $document->saveTranslation('en', ['title' => 'Revised', 'content' => '<p>Revised</p>']);

    expect($document->fresh()->notify_users)->toBeFalse();
    expect($document->fresh()->version)->toBe('1.0');
    expect($document->fresh()->title)->toBe('Revised');
    expect($document->title)->toBe('Revised');
    expect($document->isDirty('title'))->toBeFalse();
    expect($document->getDirty())->toBe(['version' => 'pending', 'notify_users' => true]);
});

it('validates fields and locales', function (string $locale, array $values) {
    $this->document()->saveTranslation($locale, $values);
})->with([
    ['bg', ['title' => 'Only title']],
    ['bg', ['title' => ' ', 'content' => 'Body']],
    ['bg', ['title' => 'Title', 'content' => '<p><br></p>']],
    ['bg', ['title' => 'Title', 'content' => 'Body', 'slug' => 'changed']],
    ['fr', ['title' => 'Title', 'content' => 'Body']],
    ['bg', ['title' => ['wrong'], 'content' => 'Body']],
])->throws(InvalidTranslation::class);

it('rejects source deletion', fn () => $this->document()->forgetTranslation('en'))
    ->throws(InvalidTranslation::class);

it('rejects unsaved models', fn () => (new LegalDocument)->localized('bg'))
    ->throws(InvalidTranslation::class);

it('requires valid fixed source configuration for an enabled driver', function () {
    config()->set('legal-documents.translations.source_locale', null);
    $this->document()->localized('bg');
})->throws(InvalidTranslationConfiguration::class);

it('rejects a custom class that does not implement the contract', function () {
    config()->set('legal-documents.translations.driver', stdClass::class);
    $this->document()->localized('bg');
})->throws(InvalidTranslationConfiguration::class);

it('works in single language with zero backend reads', function () {
    $this->configureTranslations(null);
    $document = $this->document();
    expect($document->localized('bg')->values['title'])->toBe('English terms');
    $document->saveTranslation('en', ['title' => 'New', 'content' => 'Body']);
    $document->saveTranslation('bg', ['title' => 'BG', 'content' => 'Body']);
})->throws(TranslationDriverUnavailable::class);

it('batches translations in order for documents and types', function () {
    $first = $this->document();
    $second = $this->document();
    $first->saveTranslation('bg', ['title' => 'One', 'content' => 'Body']);
    $second->type->saveTranslation('bg', ['name' => 'Type two']);
    $driver = app(ArrayTranslationDriver::class);
    $driver->reads = 0;
    $results = app(ContentTranslator::class)->localizeMany([$first, $second->type], 'bg');
    expect(array_map(fn ($value) => $value->locale, $results))->toBe(['bg', 'bg']);
    expect($results[1]->values['description'])->toBeNull();
    expect($driver->reads)->toBe(1);
    expect(app(ContentTranslator::class)->localizeMany([], 'bg'))->toBe([]);
});

it('reports malformed disabled-driver configuration as a configuration error', function () {
    config()->set('legal-documents.translations', [
        'driver' => null, 'source_locale' => 'en', 'locales' => 'en,bg', 'fallback_locale' => 'en',
    ]);
    $this->document()->localized('bg');
})->throws(InvalidTranslationConfiguration::class);
