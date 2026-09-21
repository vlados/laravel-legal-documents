<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Schema;
use Vlados\LegalDocuments\Notifications\LegalDocumentUpdated;
use Vlados\LegalDocuments\Tests\Fixtures\ArrayTranslationDriver;
use Vlados\LegalDocuments\Tests\Fixtures\User;

it('localizes serialized notification delivery per recipient without leaking locale', function () {
    $this->app->singleton(ArrayTranslationDriver::class);
    $this->configureTranslations(ArrayTranslationDriver::class);
    Schema::create('notifications', function (Blueprint $table) {
        $table->uuid('id')->primary();
        $table->string('type');
        $table->morphs('notifiable');
        $table->text('data');
        $table->timestamp('read_at')->nullable();
        $table->timestamps();
    });
    config()->set('mail.default', 'array');
    config()->set('mail.mailers.array', ['transport' => 'array']);
    config()->set('mail.from', ['address' => 'noreply@example.test', 'name' => 'Legal']);
    $document = $this->document();
    $document->saveTranslation('bg', ['title' => 'Условия', 'content' => 'Текст', 'summary_of_changes' => 'Промени']);
    $document->type->saveTranslation('bg', ['name' => 'Общи условия']);
    $english = User::create(['name' => 'English', 'email' => 'en@example.test', 'locale' => 'en']);
    $bulgarian = User::create(['name' => 'Bulgarian', 'email' => 'bg@example.test', 'locale' => 'bg']);
    app()->setLocale('de');
    $notification = unserialize(serialize(new LegalDocumentUpdated($document)));
    Notification::sendNow([$english, $bulgarian], $notification, ['database', 'mail']);
    expect($english->notifications()->first()->data['content_locale'])->toBe('en');
    expect($bulgarian->notifications()->first()->data['document_title'])->toBe('Условия');
    expect($bulgarian->notifications()->first()->data['summary_of_changes'])->toBe('Промени');
    expect($bulgarian->notifications()->first()->data['content_locale'])->toBe('bg');
    expect(app()->getLocale())->toBe('de');
    $messages = app('mailer')->getSymfonyTransport()->messages();
    expect($messages)->toHaveCount(2);
    expect($messages[1]->getOriginalMessage()->getSubject())->toContain('Общи условия');
});
