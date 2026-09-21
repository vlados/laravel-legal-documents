<?php

use Illuminate\Support\Facades\DB;
use Vlados\LegalDocuments\Exceptions\TranslationDriverUnavailable;
use Vlados\LegalDocuments\Models\LegalDocument;

it('keeps a persisted source document after the translation dependency is removed', function () {
    $phase = getenv('LEGAL_TRANSLATION_LIFECYCLE_PHASE');
    if (! $phase) {
        $this->markTestSkipped('Run the two-process dependency removal check.');
    }
    $this->configureTranslations('spatie');
    if ($phase === 'seed') {
        expect(trait_exists(\Spatie\Translatable\HasTranslations::class))->toBeTrue();
        $this->app->register(\Spatie\Translatable\TranslatableServiceProvider::class);
        foreach (glob(__DIR__.'/../../database/migrations/spatie/*.php') as $path) {
            (require $path)->up();
        }
        $document = $this->document();
        $document->saveTranslation('bg', ['title' => 'Съхранен превод', 'content' => '<p>Съхранен текст</p>']);
        expect($document->localized('bg')->locale)->toBe('bg');
    } else {
        expect(trait_exists(\Spatie\Translatable\HasTranslations::class))->toBeFalse();
        $document = LegalDocument::firstOrFail();
        expect($document->localized('bg')->values['content'])->toBe('<p>Original English</p>');
        expect($document->localized('bg')->locale)->toBe('en');
        $row = DB::table('legal_document_spatie_translations')->first();
        expect(json_decode($row->title, true)['bg'])->toBe('Съхранен превод');
        expect(fn () => $document->saveTranslation('bg', ['title' => 'Overwrite', 'content' => 'Body']))
            ->toThrow(TranslationDriverUnavailable::class);
    }
});
