<?php

use Filament\Facades\Filament;
use Filament\Actions\Testing\TestAction;
use Livewire\Livewire;
use Vlados\LegalDocuments\Filament\Resources\LegalDocumentResource\Pages\CreateLegalDocument;
use Vlados\LegalDocuments\Filament\Resources\LegalDocumentResource\Pages\EditLegalDocument;
use Vlados\LegalDocuments\Filament\Resources\LegalDocumentResource\Pages\ListLegalDocuments;
use Vlados\LegalDocuments\Filament\Resources\LegalDocumentTypeResource\Pages\EditLegalDocumentType;
use Vlados\LegalDocuments\Models\LegalDocument;
use Vlados\LegalDocuments\Tests\Fixtures\ArrayTranslationDriver;
use Vlados\LegalDocuments\Tests\Fixtures\User;

beforeEach(function () {
    if (! class_exists(\Filament\Panel::class)) {
        $this->markTestSkipped('Filament is installed in the integration environment.');
    }
    $this->app->singleton(ArrayTranslationDriver::class);
    $this->configureTranslations(ArrayTranslationDriver::class);
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    Filament::bootCurrentPanel();
    $this->actingAs(User::create(['name' => 'Admin', 'email' => 'admin@example.test']));
});

it('creates source and additional content through the resource', function () {
    $type = $this->document()->type;
    Livewire::test(CreateLegalDocument::class)->fillForm([
        'title' => 'New source', 'content' => '<p>Source</p>',
        'legal_document_type_id' => $type->id, 'version' => '2.0',
        'content_translations' => ['bg' => ['title' => 'Нов', 'content' => '<p>Нов текст</p>']],
    ])->call('create')->assertHasNoFormErrors();
    $document = LegalDocument::where('version', '2.0')->firstOrFail();
    expect($document->title)->toBe('New source');
    expect($document->localized('bg')->values['title'])->toBe('Нов');
});

it('preserves inactive locales and rich HTML when editing a translation', function () {
    $document = $this->document();
    $document->saveTranslation('bg', ['title' => 'BG', 'content' => '<p><strong>Български</strong></p>']);
    $document->saveTranslation('de', ['title' => 'DE', 'content' => '<p><em>Deutsch</em></p>']);
    Livewire::test(EditLegalDocument::class, ['record' => $document->id])
        ->fillForm(['content_translations.bg.title' => 'Updated'])
        ->call('save')->assertHasNoFormErrors();
    expect($document->localized('bg')->values['title'])->toBe('Updated');
    expect($document->localized('de')->values['content'])->toBe('<p><em>Deutsch</em></p>');
    expect($document->fresh()->title)->toBe('English terms');
});

it('does not overwrite a translation another editor changed after form hydration', function () {
    $document = $this->document();
    $document->saveTranslation('bg', ['title' => 'BG', 'content' => '<p>BG</p>']);
    $document->saveTranslation('de', ['title' => 'DE', 'content' => '<p>DE</p>']);
    $page = Livewire::test(EditLegalDocument::class, ['record' => $document->id]);
    $document->saveTranslation('de', ['title' => 'New German', 'content' => '<p>New German</p>']);
    $page->fillForm(['content_translations.bg.title' => 'New Bulgarian'])->call('save')->assertHasNoFormErrors();
    expect($document->localized('de')->values['title'])->toBe('New German');
});

it('validates partial translations before persisting source changes', function () {
    $document = $this->document();
    Livewire::test(EditLegalDocument::class, ['record' => $document->id])
        ->fillForm(['title' => 'Should not persist', 'content_translations.bg.title' => 'Incomplete'])
        ->call('save')->assertHasFormErrors();
    expect($document->fresh()->title)->toBe('English terms');
});

it('removes only the explicitly selected locale', function () {
    $document = $this->document();
    $document->saveTranslation('bg', ['title' => 'BG', 'content' => 'Body']);
    $document->saveTranslation('de', ['title' => 'DE', 'content' => 'Body']);
    Livewire::test(EditLegalDocument::class, ['record' => $document->id])
        ->fillForm(['removed_content_locales.bg' => true])->call('save')->assertHasNoFormErrors();
    expect($document->localized('bg')->locale)->toBe('en');
    expect($document->localized('de')->locale)->toBe('de');
});

it('edits a type without regenerating its existing slug', function () {
    $type = $this->document()->type;
    $slug = $type->slug;
    Livewire::test(EditLegalDocumentType::class, ['record' => $type->id])
        ->fillForm(['name' => 'Changed source', 'content_translations.bg.name' => 'Тип'])
        ->call('save')->assertHasNoFormErrors();
    expect($type->fresh()->slug)->toBe($slug);
    expect($type->localized('bg')->values['name'])->toBe('Тип');
});

it('saves repeated translations when creating another document', function () {
    $type = $this->document()->type;
    $page = Livewire::test(CreateLegalDocument::class);
    foreach (['2.0', '3.0'] as $version) {
        $page->fillForm([
            'title' => 'Source', 'content' => '<p>Source</p>',
            'legal_document_type_id' => $type->id, 'version' => $version,
            'content_translations' => ['bg' => ['title' => 'Повторен', 'content' => '<p>Текст</p>']],
        ])->call('create', true)->assertHasNoFormErrors();
    }
    $second = LegalDocument::where('version', '3.0')->firstOrFail();
    expect($second->localized('bg')->locale)->toBe('bg');
});

it('does not overwrite a concurrent source edit when saving another language', function () {
    $document = $this->document();
    $document->saveTranslation('bg', ['title' => 'BG', 'content' => '<p>BG</p>']);
    $page = Livewire::test(EditLegalDocument::class, ['record' => $document->id]);
    $document->saveTranslation('en', ['title' => 'Concurrent source', 'content' => '<p>Concurrent source</p>']);
    $page->fillForm(['content_translations.bg.title' => 'New Bulgarian'])->call('save')->assertHasNoFormErrors();
    expect($document->fresh()->title)->toBe('Concurrent source');
    $page->fillForm(['content_translations.bg.title' => 'Second save'])->call('save')->assertHasNoFormErrors();
    expect($document->fresh()->content)->toBe('<p>Concurrent source</p>');
});

it('rejects oversized versions in both duplication forms before creating a draft', function (bool $table) {
    $document = $this->document();
    $page = $table
        ? Livewire::test(ListLegalDocuments::class)
        : Livewire::test(EditLegalDocument::class, ['record' => $document->id]);
    $action = TestAction::make('duplicate');
    if ($table) {
        $action->table($document);
    }
    $page->callAction($action, data: ['new_version' => str_repeat('v', 51)])
        ->assertHasActionErrors(['new_version' => 'max']);
    expect(LegalDocument::count())->toBe(1);
})->with([false, true]);
