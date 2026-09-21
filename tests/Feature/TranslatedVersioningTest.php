<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Vlados\LegalDocuments\Models\LegalDocument;
use Vlados\LegalDocuments\Tests\Fixtures\RelationalTranslationDriver;
use Vlados\LegalDocuments\Tests\Fixtures\User;

beforeEach(function () {
    $this->app->singleton(RelationalTranslationDriver::class);
    $this->configureTranslations(RelationalTranslationDriver::class);
    Schema::create('fixture_translations', function (Blueprint $table) {
        $table->id();
        $table->string('parent_type');
        $table->unsignedBigInteger('parent_id');
        $table->string('locale');
        foreach (['title', 'content', 'summary_of_changes', 'name', 'description'] as $field) {
            $table->text($field)->nullable();
        }
        $table->unique(['parent_type', 'parent_id', 'locale']);
    });
});

it('copies every translation into a draft without copying acceptances', function () {
    $document = $this->document();
    $document->publish(false);
    $user = User::create(['name' => 'Reader', 'email' => 'reader@example.test']);
    $user->acceptDocument($document);
    $document->saveTranslation('bg', ['title' => 'BG', 'content' => '<p>Български</p>']);
    $document->saveTranslation('de', ['title' => 'DE', 'content' => '<p>Deutsch</p>']);
    $document->load('acceptances', 'type');
    $copy = $document->createNewVersion('2.0');
    expect($copy->title)->toBe($document->title);
    expect($copy->localized('bg')->values['content'])->toBe('<p>Български</p>');
    expect($copy->localized('de')->values['content'])->toBe('<p>Deutsch</p>');
    expect($copy->published_at)->toBeNull();
    expect($copy->is_current)->toBeFalse();
    expect($copy->acceptances()->count())->toBe(0);
    app()->setLocale('bg');
    expect($user->hasAcceptedDocument($document))->toBeTrue();
    expect($user->needsToAcceptDocuments())->toBeFalse();
    $copy->publish(false);
    expect($user->needsToAcceptDocuments())->toBeTrue();
});

it('rolls back the draft and copied rows after a backend failure', function () {
    $document = $this->document();
    $document->saveTranslation('bg', ['title' => 'BG', 'content' => 'Body']);
    $document->saveTranslation('de', ['title' => 'DE', 'content' => 'Body']);
    app(RelationalTranslationDriver::class)->failCopy = true;
    expect(fn () => $document->createNewVersion('2.0'))->toThrow(RuntimeException::class, 'Translation copy failed');
    expect(LegalDocument::count())->toBe(1);
    expect(DB::table('fixture_translations')->count())->toBe(2);
});

it('copies disabled stored locales without deleting them', function () {
    $document = $this->document();
    $document->saveTranslation('de', ['title' => 'DE', 'content' => 'Body']);
    config()->set('legal-documents.translations.locales', ['en', 'bg']);
    $copy = $document->createNewVersion('2.0');
    expect(app(RelationalTranslationDriver::class)->readAll($copy)['de']['title'])->toBe('DE');
});

it('keeps type translation fields independent in a relational backend', function () {
    $type = $this->document()->type;
    $type->saveTranslation('bg', ['name' => 'Тип', 'description' => 'Описание']);
    expect($type->localized('bg')->values)->toBe(['name' => 'Тип', 'description' => 'Описание']);
    $type->forgetTranslation('bg');
    expect($type->localized('bg')->locale)->toBe('en');
});
