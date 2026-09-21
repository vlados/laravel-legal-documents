# Content translations

The package works in one language by default. Its existing database columns always contain source-language strings. Installing a translation package alone does not change this behavior.

## Enable the bundled Spatie adapter

Install a v6 release compatible with your application's PHP/Laravel versions:

```bash
composer require spatie/laravel-translatable:^6.0
php artisan vendor:publish --tag=legal-documents-spatie-migrations
php artisan migrate
```

Set this block in your published `config/legal-documents.php`:

```php
// Inside config/legal-documents.php:
'translations' => [
    'driver' => 'spatie',
    'source_locale' => 'en',
    'locales' => ['en', 'bg', 'de'],
    'fallback_locale' => null,
],
```

Set `source_locale` to the language of your existing content, which may be Bulgarian or another language. It must be included in `locales`. Locale identifiers support language/region forms such as `en`, `bg`, `pt-BR`, and `pt_BR`. The source language is fixed; changing it requires migrating the content. Never set it from the current request locale.

Refresh configuration caches and restart long-lived workers through your application's usual deployment procedure. Run migrations on every database connection that stores legal documents before enabling translations there. The adapter reports missing tables rather than hiding storage errors.

The two additive tables are `legal_document_spatie_translations` and `legal_document_type_spatie_translations`. Each stores one companion row per parent, containing JSON maps for additional languages. There is no conversion or backfill of the original scalar columns. The core migration publish tag does not include these optional tables.

Spatie 6.14.1 requires PHP 8.3; single-language core usage retains PHP 8.2 support. Let Composer resolve a compatible version rather than ignoring platform requirements.

## Reading and writing

```php
$document->title;   // Always the source-language string.
$document->content;

$content = $document->localized('bg');
$content->values['title'];
$content->values['content'];
$content->values['summary_of_changes'];
$content->requestedLocale;
$content->locale;       // The actual language returned.
$content->isFallback;

// Without an argument, use Laravel's current application locale.
$content = $document->localized();

// Persist a complete additional-language version. The parent must be saved first.
$document->saveTranslation('bg', [
    'title' => 'Общи условия',
    'content' => '<p>Съдържание на документа.</p>',
    'summary_of_changes' => null,
]);

$document->forgetTranslation('bg');
```

`LegalDocumentType` has the same API with `name` and `description`. `saveTranslation()` saves immediately; it replaces the specified language's full field set, keeping every other language. Omitted optional fields become null. Title and body are required for a document; name is required for a type. Blank rich text is not a complete body. Unknown fields/locales are rejected. Writing the source locale updates the original scalar fields; deleting the source locale is forbidden.

The API belongs to this package. The domain models do not use `HasTranslations`, so native Spatie calls such as `$document->getTranslation('title', 'bg')` are not available. The adapter's companion models use Spatie internally.

Saving source-language content persists only the translated source fields. Unrelated pending changes on the model stay unsaved; call `save()` separately when you intend to persist them.

For lists, avoid one storage query per item:

```php
use Vlados\LegalDocuments\Translations\ContentTranslator;

$contents = app(ContentTranslator::class)->localizeMany($documents->all(), 'bg');
// Results have the same order/count as the supplied records.
```

## Fallback and application locale

With the Spatie driver enabled, the fallback locale is read first from Spatie's global `Translatable::fallback(fallbackLocale: 'bg')` setting. If that value is unset/null, or Spatie is unavailable, the package uses `legal-documents.translations.fallback_locale`. Custom and disabled drivers use only the package setting. The effective fallback must be included in `translations.locales`.

For example, in your application's service provider `boot()` method:

```php
\Spatie\Translatable\Facades\Translatable::fallback(fallbackLocale: 'bg');
```

Spatie v6 configures this through its service/facade, not a `translatable.php` config file. It provides no global source language, available-locale list, or driver selector; those remain in this package's config. The source language is never inferred from Spatie's fallback or the current request. Spatie's `fallbackAny` and per-field missing-key callback do not participate in legal-document resolution, which must return one complete language. See [Spatie's fallback documentation](https://spatie.be/docs/laravel-translatable/v6/basic-usage/handling-missing-translations).

The resolver chooses a complete document in this order: requested language, configured fallback language, source language. It never combines a translated title with a body from another language. An absent optional summary stays null in the chosen language. Type names/descriptions resolve together independently of document coverage.

The host application controls Laravel's locale and locale middleware, including Livewire requests. Slugs, routes, version identifiers, and publication state are language-independent. The package adds no locale-prefixed routes. Public views indicate the actual content language when falling back.

Notifications resolve content during delivery using Laravel's active recipient locale. Implement Laravel's `HasLocalePreference` on the user model when users have individual language preferences. New database notification payloads include `content_locale` and `document_type_locale`; existing notification payloads remain unchanged.

## Filament and published views

Filament 4 resources show the original source fields and a separate tab for each additional language. Empty tabs are ignored. To delete a translation, use its explicit removal toggle and save. Editing one language preserves untouched tabs, including their rich text. Changing a type's name no longer regenerates an existing slug.

Lists, sorting, search, and relationship selectors use source-language columns. This release does not implement translated SQL search or sorting. No Lara Zeus translation plugin is required. English/Bulgarian interface labels use Laravel language files independently of the content driver.

Applications with published Blade view overrides should update them to use localized values:

```blade
@php($content = $document->localized())
<article lang="{{ $content->locale }}">
    <h1>{{ $content->values['title'] }}</h1>
    {!! $content->values['content'] !!}
</article>
```

Package views batch related content; do the same in custom lists. Existing `$document->title` consumers remain valid but display source text. Treat rich HTML as trusted administrator-authored content, as before.

## Versions and acceptance

```php
$draft = $document->createNewVersion('2.0');
```

This copies source fields and all stored translations in a transaction, including stored languages no longer enabled for editing. It resets publication/current state and does not copy acceptances. Both Filament duplication actions use this method. Raw Eloquent `replicate()` alone does not copy companion translations.

Acceptance remains per user/document version. Changing the viewing language does not require another acceptance. Published records remain editable under existing package behavior; use a new version for material wording changes. This feature does not add historical wording snapshots or record the language a user accepted.

## Disable or remove Spatie

Set `translations.driver` to null to explicitly disable translation support. You can also remove Spatie: if it was configured but is unavailable, reads and source editing still work. Additional-language writes fail with `TranslationDriverUnavailable`; the admin editor shows a setup message.

Existing translation rows remain intact. Do not roll back/drop their tables merely to disable support. Reinstalling Spatie and restoring the configuration makes those rows available again. Edits made to source text while translations are disabled do not update those stored translations automatically; review them before reenabling.

When translations were never enabled, neither Spatie nor its migrations are needed. Interface localization still uses Laravel's language files.

## Other translation backends

Implement `Vlados\LegalDocuments\Contracts\TranslationDriver` and configure its class name:

```php
'driver' => App\Legal\MyTranslationDriver::class,
```

Laravel resolves the class through its service container, so constructor dependencies and application bindings are supported. The interface is:

```php
use Illuminate\Database\Eloquent\Model;

public function readAll(Model $record): array;
public function readMany(array $records): array;
public function putLocale(Model $record, string $locale, array $values): void;
public function forgetLocale(Model $record, string $locale): void;
```

- `readAll()` returns exact additional translations: `['bg' => ['title' => '...', 'content' => '...', 'summary_of_changes' => null]]`. No fallback and no source-locale shadow records. Inside a transaction, use a current/locking read: version copying locks the source parent first and must include translations committed before that lock, even if an outer transaction already established a snapshot.
- `readMany()` returns one map per input record in the same order; batch reads by parent type/connection.
- `putLocale()` replaces one complete language, preserving all others. Optional fields may be null.
- `forgetLocale()` removes only that additional language.

Support both domain model types, use the parent's database connection, and make writes participate in its transaction. Arrange cascading cleanup when parents are deleted. Use current/locking reads when merging storage maps under concurrent transactions. Do not keep request locale or model data in a global cache.

PostgreSQL's default `READ COMMITTED` isolation reads current rows when locking. Under `REPEATABLE READ`, a concurrent update after the transaction's snapshot can instead raise SQLSTATE `40001`. Let that failure propagate so the entire outer transaction rolls back, then retry the complete transaction; retrying only a nested translation operation cannot refresh the outer snapshot. See [PostgreSQL's transaction isolation documentation](https://www.postgresql.org/docs/current/transaction-iso.html).

The relational fixture at `tests/Fixtures/RelationalTranslationDriver.php` demonstrates separate-row storage and rollback semantics; its intentionally simple reads are test-only. A production adapter for Astrotomic or another package needs that package's models/migrations and a batched `readMany()` implementation. This release bundles only Spatie. Switching drivers does not convert existing translations automatically.

## Verification

```bash
composer install
composer test

composer install --working-dir=compatibility/core
composer install --working-dir=compatibility/spatie
bash compatibility/test.sh core
bash compatibility/test.sh spatie
bash compatibility/test-removal.sh
```

The base environment does not install Spatie. Integration tests are skipped there and run in the isolated Spatie/Filament environment. By default, the removal check creates a temporary SQLite database in one process with Spatie and opens it in a second process without Spatie.

CI covers supported Laravel/Testbench combinations and a dedicated MySQL 8 test for writes inside an existing repeatable-read transaction. The MySQL suite only runs with `LEGAL_MYSQL_TESTS=1` and owns a dedicated `legal_documents_test` database; it drops that database's tables between runs. Never point it at an application database.

CI also runs the full core and Spatie suites plus the dependency-removal check on PostgreSQL 16 and 18. This includes migrations, JSON translations, version/acceptance behavior, custom-driver rollback, Filament editing, and two-connection transaction tests. Additional PostgreSQL checks cover optional-migration rollback/reinstallation and serialization-failure rollback/retry under repeatable-read isolation.

To run against a disposable local PostgreSQL instance with `pdo_pgsql` installed:

```bash
# Create the dedicated legal_documents_pgsql_test database first.
export LEGAL_PGSQL_TESTS=1
export LEGAL_PGSQL_HOST=127.0.0.1
export LEGAL_PGSQL_PORT=5432
export LEGAL_PGSQL_USER=postgres
export LEGAL_PGSQL_PASSWORD=test
bash compatibility/test.sh core
bash compatibility/test.sh spatie
bash compatibility/test-removal.sh
```

This opt-in mode uses only the fixed `legal_documents_pgsql_test` database and drops its tables between tests. The removal check preserves it between its seed and dependency-absent verification processes. Use a disposable instance, run these commands sequentially, and do not enable `LEGAL_MYSQL_TESTS` at the same time.
