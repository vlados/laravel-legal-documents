# Upgrading from v1 to v2

Version 2 adds optional content translations while preserving existing scalar source-language fields and document acceptance records. Existing installations stay in single-language mode until a translation driver is explicitly enabled.

## Dependencies

```bash
composer require vlados/laravel-legal-documents:^2.0 --with-all-dependencies
```

Core requirements remain PHP 8.2+ and Laravel 11, 12, or 13, subject to each Laravel version's PHP requirements. The optional admin resources require Filament 4. Public Livewire components are tested with Livewire 3.

`spatie/laravel-translatable` is no longer installed automatically. If your application uses Spatie directly or wants this package's bundled adapter, declare it explicitly:

```bash
composer require spatie/laravel-translatable:^6.0
```

Composer must resolve a release compatible with your PHP/Laravel versions; Spatie 6.14.1 requires PHP 8.3. The Lara Zeus translation plugin is not needed for this package's editor. Keep any dependency that other parts of your application still use. Custom `TranslationDriver` implementations use their own dependencies and storage.

## Single-language installations

Leave `legal-documents.translations.driver` as `null`. No translation migrations or data conversion are needed. Existing scalar attributes, slugs, document version IDs, and acceptance records remain valid.

## Enable the bundled Spatie adapter

After installing Spatie, publish and run the additional migrations:

```bash
php artisan vendor:publish --tag=legal-documents-spatie-migrations
php artisan migrate
```

Merge this configuration into your existing `config/legal-documents.php`, using the actual language of your stored content:

```php
'translations' => [
    'driver' => 'spatie',
    'source_locale' => 'en',
    'locales' => ['en', 'bg'],
    'fallback_locale' => null,
],
```

The source language must be fixed and included in `locales`. Spatie's explicitly configured global fallback takes precedence over this package's fallback; the effective fallback must also be included in `locales`. The adapter stores additional languages in separate tables and never converts your original columns to JSON. Run its migrations on every connection that stores legal documents.

Installing Spatie alone does not enable translations. Removing it later leaves source content usable and additional translation rows intact.

## Published files and application code

- Merge the new configuration keys into your published config while retaining application-specific values. Refresh cached configuration and restart long-lived workers using your deployment procedure.
- Compare published Blade overrides with the updated package views. `$document->title` still returns source text; use `$document->localized()` for translated content. The result exposes `values`, the actual `locale`, and `isFallback`. Batch list reads through `ContentTranslator::localizeMany()`.
- Compare published language files with the new English/Bulgarian keys. Admin labels now follow Laravel's interface locale.
- Use `saveTranslation()` and `forgetTranslation()` for content edits. Domain models do not expose Spatie's native `getTranslation()` API; the optional companion models use Spatie internally.
- Use `createNewVersion()` when duplicating a document with translations. Raw Eloquent `replicate()` does not copy companion data.

Acceptance remains per document version, not per viewing language. Public routes, source-language search/sorting, and published-content editing semantics remain unchanged. New notification payloads include content-language metadata; old notification payloads are not rewritten.

The [translation guide](translations.md) covers the APIs, complete-document fallback, custom adapters, disabling translations, and transaction retry behavior.
