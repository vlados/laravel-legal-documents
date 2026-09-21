<?php

use Vlados\LegalDocuments\Tests\Fixtures\User;

it('persists source text and keeps acceptance attached to a version', function () {
    $document = $this->document();
    $document->publish(false);
    $user = User::create(['name' => 'Reader', 'email' => 'reader@example.test']);
    $user->acceptDocument($document);

    expect($document->fresh()->content)->toBe('<p>Original English</p>');
    expect($user->hasAcceptedLatest($document->type->slug))->toBeTrue();
});

it('does not require a translation package', function () {
    $composer = json_decode(file_get_contents(__DIR__.'/../../composer.json'), true);
    expect($composer['require'])->not->toHaveKey('spatie/laravel-translatable');
});

it('falls back safely when the configured Spatie dependency is genuinely absent', function () {
    if (trait_exists(\Spatie\Translatable\HasTranslations::class)) {
        $this->markTestSkipped('Run in the core compatibility environment without Spatie.');
    }
    $this->configureTranslations('spatie');
    $document = $this->document();
    expect($document->localized('bg')->locale)->toBe('en');
    expect(class_exists(\Vlados\LegalDocuments\Translations\Spatie\Models\DocumentTranslations::class, false))->toBeFalse();
    expect(fn () => $document->saveTranslation('bg', ['title' => 'BG', 'content' => 'Body']))
        ->toThrow(\Vlados\LegalDocuments\Exceptions\TranslationDriverUnavailable::class);
    $document->saveTranslation('en', ['title' => 'Updated', 'content' => 'Source']);
    expect($document->fresh()->title)->toBe('Updated');
});

it('keeps role requirements and acceptance independent of viewing language', function () {
    config()->set('legal-documents.roles.enabled', true);
    $document = $this->document();
    $document->type->update(['is_required' => false, 'required_for_roles' => ['seller']]);
    $document->publish(false);
    $user = User::create(['name' => 'Seller', 'email' => 'seller@example.test']);
    expect($user->getPendingDocuments())->toHaveCount(0);
    $user->setAttribute('fixture_roles', ['seller']);
    expect($user->getPendingDocuments())->toHaveCount(1);
    $user->acceptDocument($document);
    app()->setLocale('bg');
    expect($user->needsToAcceptDocuments())->toBeFalse();
});
