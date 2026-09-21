<?php

use Livewire\Livewire;
use Vlados\LegalDocuments\Http\Livewire\AcceptDocuments;
use Vlados\LegalDocuments\Tests\Fixtures\ArrayTranslationDriver;
use Vlados\LegalDocuments\Tests\Fixtures\User;

beforeEach(function () {
    if (! class_exists(Livewire::class)) {
        $this->markTestSkipped('Livewire is installed in the frontend integration environment.');
    }
    $this->app->singleton(ArrayTranslationDriver::class);
    $this->configureTranslations(ArrayTranslationDriver::class);
    app()->setLocale('bg');
});

it('renders localized page title body revisions and other document links', function () {
    $document = $this->document();
    $document->publish(false);
    $document->saveTranslation('bg', ['title' => 'Българско заглавие', 'content' => '<p>Българско съдържание</p>', 'summary_of_changes' => 'Нова редакция']);
    $document->type->saveTranslation('bg', ['name' => 'Български тип']);
    $other = $this->document();
    $other->publish(false);
    $other->type->saveTranslation('bg', ['name' => 'Друг документ', 'description' => 'Описание']);
    $this->get(route('legal.show', $document->type->slug))->assertOk()
        ->assertSee('Българско заглавие')->assertSee('Българско съдържание')
        ->assertSee('Нова редакция')->assertSee('Друг документ')->assertSee('Описание')
        ->assertSee('lang="bg"', false)->assertDontSee('Original English');
});

it('renders a complete source fallback with a language indication', function () {
    $document = $this->document();
    $document->publish(false);
    $this->get(route('legal.show', $document->type->slug))->assertOk()
        ->assertSee('English terms')->assertSee('Original English')
        ->assertSee('lang="en"', false);
});

it('shows translated acceptance content and accepts the same document identity', function () {
    $document = $this->document();
    $document->publish(false);
    $document->saveTranslation('bg', ['title' => 'Условия', 'content' => '<p>За приемане</p>', 'summary_of_changes' => 'Промени']);
    $user = User::create(['name' => 'Reader', 'email' => 'reader@example.test']);
    Livewire::actingAs($user)->test(AcceptDocuments::class)
        ->assertSee('Промени')->call('viewDocument', $document->id)
        ->assertSee('За приемане')->assertSee('Условия')
        ->call('acceptAll')->call('submit')->assertHasNoErrors();
    expect($user->hasAcceptedDocument($document))->toBeTrue();
});
