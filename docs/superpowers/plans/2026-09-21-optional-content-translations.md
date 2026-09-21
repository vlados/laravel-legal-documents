# Optional Content Translations Implementation Plan

**Status:** Implementation delivered on `feature/optional-content-translations`; local suites and browser checks pass. The full CI matrix and MySQL runtime check remain pending. See the [implementation record](2026-09-21-optional-content-translations-results.md). The detailed checklists below preserve the original plan; the record tracks actual delivery and deviations.

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Provide single-language operation without a translation dependency, bundled optional Spatie support, and a storage contract for alternative translation backends.

**Architecture:** Preserve scalar source fields on the existing domain models. A core content service resolves complete translations through a storage contract; the built-in Spatie adapter uses companion models and opt-in tables. Public rendering, notifications, and Filament editing use that service.

**Tech Stack:** PHP, Laravel/Eloquent, optional Spatie Laravel Translatable v6, Livewire, optional Filament, Orchestra Testbench/Pest.

**Spec:** [Optional content translations design](../specs/2026-09-21-optional-content-translations-design.md).

## Global constraints

- Originally planning-only; the subsequent request to continue authorized implementation.
- Preserve the existing uncommitted Laravel 13 change in `composer.json`.
- Keep the core PHP constraint `^8.2` and existing Illuminate constraints `^11.0|^12.0|^13.0`; test only PHP/Laravel combinations allowed by their actual dependencies.
- Default translation driver is `null`; dependency installation alone never changes content behavior.
- Source locale is fixed configuration matching existing scalar content; enabling translations requires it explicitly.
- Translate document `title`, `content`, `summary_of_changes` and type `name`, `description`; keep slug, version, publication, roles, and acceptance identity language-independent.
- Use optional companion storage; do not convert existing scalar columns to JSON or rewrite published core migrations.
- Every supported adapter must use the parent's database connection for atomic persistence and duplication.
- Preserve English and Bulgarian interface language support; UI localization works without Spatie.
- The original planning request did not authorize commits or pushes; the subsequent request to commit and create a pull request authorizes publication of the implementation.

## Review focus

1. Spatie removed after translations exist: source reads work and additional translation writes fail explicitly (Tasks 1, 3, 7).
2. Missing or partial requested translation: title/body/summary do not silently mix languages (Tasks 2, 4).
3. Two administrators edit different locales: neither save discards the other locale or existing HTML (Tasks 3, 5).
4. A new version is created or copy fails midway: all translations copy atomically, and acceptances remain attached to the old ID (Task 6).
5. A long-lived worker sends to users with different locales: content language does not leak across notifications (Tasks 2, 4, 7).

## File responsibilities

| File/group | Responsibility |
| --- | --- |
| `src/Contracts/TranslationDriver.php` | Exact storage read/write contract |
| `src/Translations/ContentTranslator.php` | Validation, source writes, fallback, batching, copy orchestration |
| `src/Translations/LocalizedContent.php` | Requested locale, actual locale, values, fallback flag |
| `src/Translations/SingleLanguageDriver.php` | Empty additional translations; rejected non-source writes |
| `src/Traits/HasLocalizedContent.php` | Small domain-model API delegating to content service |
| `src/Translations/Spatie/SpatieTranslationDriver.php` | Spatie persistence and batched reads |
| `src/Translations/Spatie/Models/{DocumentTranslations,DocumentTypeTranslations}.php` | Optional models containing Spatie's trait |
| `src/Exceptions/{TranslationDriverUnavailable,InvalidTranslation,InvalidTranslationConfiguration}.php` | Distinct dependency, content, and configuration errors |
| `database/migrations/spatie/` | Separately published, additive storage migrations |
| `src/Filament/Concerns/InteractsWithContentTranslations.php` | Shared form state validation/persistence for create/edit pages |
| Existing domain models, resources, Livewire, notifications, views | Consumers of the shared API |
| `tests/` and `.github/workflows/tests.yml` | Baseline, backend contract, integration, and dependency-absence verification |

## Task 1: Establish the baseline and optional dependency boundary

**Files:** Modify `composer.json`, `config/legal-documents.php`, `src/LegalDocumentsServiceProvider.php`; create `tests/TestCase.php`, `tests/Pest.php`, `phpunit.xml`, `tests/Feature/SingleLanguageTest.php`.

**Interfaces:** Existing model/public APIs remain unchanged. Produce a working `composer test` command and a test application with users, legal migrations, a layout, and named `home` route.

- [ ] Write the baseline test before changing dependency declarations:

```php
it('persists source text and keeps acceptance attached to a version', function () {
    $type = LegalDocumentType::create(['slug' => 'terms', 'name' => 'Terms']);
    $document = $type->documents()->create([
        'version' => '1.0', 'title' => 'Terms', 'content' => '<p>Original</p>',
        'notify_users' => false,
    ]);
    $document->publish(false);
    $user = User::create(['name' => 'Reader', 'email' => 'reader@example.test']);
    $user->acceptDocument($document);

    expect($document->fresh()->content)->toBe('<p>Original</p>');
    expect($user->hasAcceptedLatest('terms'))->toBeTrue();
});
```

Import the package model classes at the test file top. Create `tests/Fixtures/User.php` extending Laravel's authenticatable user model, with `HasLegalAcceptances`, a fillable name/email, and a test users migration. Set notification queue off, use SQLite in memory, and load the existing core migrations explicitly in Testbench. Core tests disable frontend routes when Livewire is absent; frontend jobs install Livewire and enable them.

- [ ] Add `"test": "vendor/bin/pest"` under Composer scripts. Run `composer test -- --filter=SingleLanguage` to establish the baseline.
- [ ] Move Spatie from `require` to `suggest`, add `illuminate/database` with the existing Illuminate version constraint, and remove the claim that the Lara Zeus plugin is required. Keep Spatie out of the base `require-dev` so a real absence test is possible.
- [ ] Add the spec's `translations` config block. Keep optional class loading out of unconditional provider boot paths. Add a dependency-absence assertion:

```php
expect(trait_exists(\Spatie\Translatable\HasTranslations::class))->toBeFalse();
expect(LegalDocument::query()->count())->toBe(0);
```

- [ ] Run `composer validate --strict` and the baseline suite in an isolated dependency installation without Spatie. This is a real package-absence check, not a mocked detection result.

**Deliverable:** The current feature set remains usable without Spatie. Suggested commit: `refactor: make content translation dependencies optional`.

## Task 2: Add the storage contract and consistent content API

**Files:** Create the contract, content service/DTO, null driver, model trait, and exceptions listed above; modify both domain models and the provider; create `tests/Unit/ContentTranslatorTest.php`, `tests/Fixtures/ArrayTranslationDriver.php`.

**Interfaces:** Implement the exact `TranslationDriver` signatures in the spec. Produce these public methods:

```php
// ContentTranslator
public function localize(Model $record, ?string $locale = null): LocalizedContent;
/** @param list<Model> $records @return list<LocalizedContent> */
public function localizeMany(array $records, ?string $locale = null): array;
public function saveLocale(Model $record, string $locale, array $values): void;
public function forgetLocale(Model $record, string $locale): void;
public function copyTranslations(Model $source, Model $target): void;

// HasLocalizedContent delegates to those methods.
public function localized(?string $locale = null): LocalizedContent;
public function saveTranslation(string $locale, array $values): void;
public function forgetTranslation(string $locale): void;
```

`LocalizedContent` is a readonly DTO with public `string $requestedLocale`, `string $locale`, `array $values`, and `bool $isFallback`. No query or persistence behavior belongs in the DTO.

- [ ] Write tests for explicit locale, current application locale, configured fallback, complete source fallback, nullable optional fields, unknown fields/locales, unsupported model types, unsaved records, and source deletion. Pin whole-document fallback:

```php
config()->set('legal-documents.translations', [
    'driver' => ArrayTranslationDriver::class,
    'source_locale' => 'en', 'locales' => ['en', 'bg'], 'fallback_locale' => null,
]);
$driver = app(ArrayTranslationDriver::class);
$driver->seed($document, ['bg' => ['title' => 'Условия', 'content' => null]]);
$resolved = $document->localized('bg');
expect($resolved->locale)->toBe('en');
expect($resolved->values['title'])->toBe($document->title);
expect($resolved->values['content'])->toBe($document->content);
expect($resolved->isFallback)->toBeTrue();
```

The array fixture implements all four contract methods and exposes `seed(Model $record, array $translations): void` only for malformed/incomplete stored-data tests. Register its instance in the container before using it; do not create separate fixture stores on each resolution.

- [ ] Resolve driver config through the container. Use scoped bindings for the service/driver; never retain current locale in either. When Spatie is configured but missing, resolve the null driver and expose translation editing as unavailable; bad custom classes throw `InvalidTranslationConfiguration`.
- [ ] Implement field allowlists and normalization. Source saves use scalar model fields; additional writes replace one complete locale via the adapter. Do not permit source rows in the additional translation map. Source writes and adapter writes occur in the parent's connection transaction.
- [ ] Implement ordered fallback exactly as the spec defines. `localizeMany()` calls `readMany()` once and returns aligned DTOs, including an empty-array fast path. Read the request locale at call time, not service construction.
- [ ] Run `composer test -- --filter=ContentTranslator`. Add a test that resolves English then Bulgarian through the same service instance and verifies both results.

**Deliverable:** The core works with a fake backend and has no dependency on Spatie. Suggested commit: `feat: add a translation backend contract and content resolver`.

## Task 3: Ship the optional Spatie adapter and additive migrations

**Files:** Create the three Spatie classes from the file map; create `database/migrations/spatie/2026_09_21_000001_create_legal_document_spatie_translations_table.php` and `2026_09_21_000002_create_legal_document_type_spatie_translations_table.php`; update provider publishing; create `tests/Integration/SpatieTranslationDriverTest.php`.

**Interfaces:** Consume `TranslationDriver`; return only exact additional translations with no fallback. Adapter models use explicit table names and the parent's database connection.

- [ ] Write a round-trip integration test that creates a scalar source document, saves Bulgarian and German, edits Bulgarian, refreshes, and verifies German and source HTML are unchanged. Add type name/description and nullable summary coverage.
- [ ] Create companion migrations following this shape, with the type equivalent for `name`/`description`:

```php
Schema::create('legal_document_spatie_translations', function (Blueprint $table) {
    $table->id();
    $table->foreignId('legal_document_id')->unique()
        ->constrained('legal_documents')->cascadeOnDelete();
    $table->json('title')->nullable();
    $table->json('content')->nullable();
    $table->json('summary_of_changes')->nullable();
    $table->timestamps();
});
```

- [ ] Define the document companion model with `use HasTranslations`, explicit `$table`, fillable parent/content fields, and `public $translatable = ['title', 'content', 'summary_of_changes'];`. Define the analogous type model. These are ordinary Eloquent models, not subclasses of the domain models.
- [ ] Transpose Spatie's attribute-indexed `getTranslations()` maps into the contract's locale-indexed maps. For writes, lock the parent row and freshly load the companion record inside the transaction; call `setTranslation()` for every supplied field, then save. For null optional values, remove that locale's optional field translation. Preserve all other locales and invalidate no global caches because none exist.
- [ ] Implement batched reads by grouping records by model kind and database connection, then fetching each group's companion records with `whereIn`. Reconstruct the output in the original input order; include empty maps for missing rows.
- [ ] Publish the adapter files only under `legal-documents-spatie-migrations`. Restrict the existing core migration publisher to its four original files so the new nested directory is not accidentally included. Do not load adapter migrations automatically.
- [ ] Add tests for delete cascades, double migration publication, parent connection selection, missing adapter tables, and separate-connection concurrent writes to different locales on MySQL. Assert source values are byte-for-byte unchanged by migration and enable/disable cycles.
- [ ] In an isolated integration checkout install a compatible v6 release, run the adapter suite, then run the separate absence job against a persisted database fixture with populated translation tables. Core reads must work without loading either companion model.

**Deliverable:** Built-in Spatie support with no data conversion requirement. Suggested commit: `feat: add optional Spatie translation storage`.

## Task 4: Localize rendering and notifications

**Files:** Modify `src/Http/Livewire/ViewLegalDocument.php`, `src/Http/Livewire/AcceptDocuments.php`, both files in `resources/views/`, `src/Notifications/LegalDocumentUpdated.php`, both language files; create `tests/Feature/LocalizedRenderingTest.php` and `tests/Feature/LocalizedNotificationsTest.php`.

**Interfaces:** Consume `localized()`/`localizeMany()` DTOs. Produce locale-consistent public/acceptance content and notification payloads with `content_locale`.

- [ ] Add public-page and acceptance-modal tests with different English/Bulgarian titles and body markers. Include revision summaries, other-document navigation, partial locale fallback, HTML `lang`, and layout page title.
- [ ] Resolve content once per displayed record and pass DTOs into Blade. Replace direct translated-field access, including section title and revision history. Move view-level queries into component methods and batch related types/history rather than resolving within each Blade loop.

```blade
<article lang="{{ $localizedDocument->locale }}">
    <h1>{{ $localizedDocument->values['title'] }}</h1>
    {!! $localizedDocument->values['content'] !!}
</article>
```

Preserve the package's existing trusted-admin HTML behavior; this task does not change content sanitization policy.

- [ ] Ensure Livewire requests retain the host application's locale middleware behavior. Do not introduce a locale input that can change slugs or acceptance identity. Published view overrides need a documented upgrade example.
- [ ] Resolve notification data at channel evaluation time and add actual content locale to database payloads:

```php
$content = $this->document->localized(app()->getLocale());
$title = $content->values['title'];
$summary = $content->values['summary_of_changes'];
// Include these resolved values plus $content->locale in the database payload.
```

- [ ] Send notifications to English and Bulgarian fixtures implementing `HasLocalePreference` in the same worker process. Assert translated strings/body summaries and restored application locale. Exercise serialization/deserialization and delivery, not only `Notification::fake()`.
- [ ] Run `composer test -- --filter='LocalizedRendering|LocalizedNotifications'` with translations enabled, disabled, and the fake adapter. Verify query counts for 1 versus 20 navigation/history items stay bounded by model/connection groups.

**Deliverable:** All package reading surfaces use the contract consistently. Suggested commit: `feat: render legal content in the requested language`.

## Task 5: Add backend-independent editing and localize Filament labels

**Files:** Modify both Filament resources and all create/edit pages, `src/Filament/LegalDocumentsPlugin.php`, and English/Bulgarian language files; create `src/Filament/Concerns/InteractsWithContentTranslations.php`, `tests/Feature/FilamentTranslationsTest.php`, `tests/Feature/InterfaceLocalizationTest.php`.

**Interfaces:** Consume `ContentTranslator::saveLocale()` and `forgetLocale()`. Translation form state is `content_translations[locale][field]`, separate from original scalar fields; explicit deletion state is `removed_content_locales`.

- [ ] Write create/edit tests with distinct source and translated HTML. Verify switching tabs, editing one locale, validation errors, and an explicit removal leave all other locales intact. Assert unknown locale/field submissions are rejected server-side even if UI controls are hidden.
- [ ] Add additional-locale tabs using ordinary fields, hydrated from exact stored maps without fallback. Keep source controls bound to the existing scalar attributes. Avoid Spatie-specific resource/page traits.

```php
// Field state paths inside the additional-locale tab.
TextInput::make("content_translations.{$locale}.title");
RichEditor::make("content_translations.{$locale}.content");
Textarea::make("content_translations.{$locale}.summary_of_changes");
```

Use the same source editor's HTML format for all tabs. Validate locale identifiers before constructing paths; use safe encoded form keys with an explicit mapping if configured locale identifiers contain dots.

- [ ] Implement `handleRecordCreation(array $data): Model` and `handleRecordUpdate(Model $record, array $data): Model` in shared page behavior. Extract translation/removal state before mass assignment, validate it, save the parent and translated locales in the same connection transaction. On create, save the parent before adapter writes; on edit, lock the parent before read/merge. Roll back source and translations together on any validation/storage failure.
- [ ] Hide additional-locale tabs for the null/unavailable driver. Keep setup messages limited to administrators; source editing remains functional.
- [ ] Change slug generation to run only when creating a type with an empty slug. Keep inline relationship creation in the source language. Keep table titles, relationship labels, database search/sort, and `scopeOrdered()` in source language, explicitly documented in the UI help and README.
- [ ] Replace hardcoded interface labels/statuses/actions with language keys, including model navigation labels and page notifications. Add matching keys to both existing language files.
- [ ] Run `composer test -- --filter='FilamentTranslations|InterfaceLocalization'`. Verify browser behavior for rich editor tab changes and invalid saves. Test the actual supported Filament major(s); do not infer compatibility merely from Composer suggestions.

**Deliverable:** Administrators can edit translations through any compliant adapter. Suggested commit: `feat: add translation editing to legal document resources`.

## Task 6: Preserve versioning and acceptance semantics across backends

**Files:** Modify `src/Models/LegalDocument.php`, both duplication actions, and `ContentTranslator::copyTranslations()`; create `tests/Feature/TranslatedVersioningTest.php`, `tests/Fixtures/RelationalTranslationDriver.php`, `tests/Fixtures/FailingTranslationDriver.php`, `tests/Feature/CustomTranslationDriverTest.php`.

**Interfaces:** Produce `LegalDocument::createNewVersion(string $version): static`. A new record is unpublished/noncurrent, retains scalar fields and extra locales, and has no copied acceptances.

- [ ] Add this outcome test for both Spatie and a related-row fixture backend:

```php
$user->acceptDocument($document);
$document->saveTranslation('bg', [
    'title' => 'Условия', 'content' => '<p>Български</p>',
    'summary_of_changes' => null,
]);
$copy = $document->createNewVersion('2.0');
expect($copy->localized('bg')->values['content'])->toBe('<p>Български</p>');
expect($copy->published_at)->toBeNull();
expect($copy->is_current)->toBeFalse();
expect($copy->acceptances()->count())->toBe(0);
expect($user->hasAcceptedDocument($document))->toBeTrue();
expect($user->hasAcceptedDocument($copy))->toBeFalse();
```

- [ ] Implement duplication on the model's database connection. Reload/lock the source during the transaction, replicate scalar fields, clear current/publication state, set the requested version, save, and copy `readAll()` maps through `putLocale()`. Do not copy loaded parent relations or acceptance relations as persisted children.
- [ ] Replace both existing `replicate()` action blocks with `createNewVersion()`. Verify duplicate-version validation/unique failures leave no orphan translations.
- [ ] Implement the relational test adapter using a fixture table with parent kind/ID, locale, and separate nullable text fields. It implements the full contract and shares the domain model's connection, demonstrating that JSON queries and Spatie APIs never leak into core.
- [ ] Implement the failing fixture to throw on the second copied locale. Assert the new draft and its first translation row are both rolled back.
- [ ] Test that switching app locale does not make an accepted version pending; publishing the copied version still requires acceptance according to the existing flag/role rules. Test nullable summaries and every translated type field through the relational adapter too.
- [ ] Run `composer test -- --filter='TranslatedVersioning|CustomTranslationDriver'` and the original acceptance/role baseline.

**Deliverable:** New-version creation works for JSON and related-row storage. Suggested commit: `fix: preserve all translations when creating legal document versions`.

## Task 7: Verify installation modes and document the extension API

**Files:** Modify `README.md`, `CHANGELOG.md`; create `.github/workflows/tests.yml`, `docs/translations.md`, and isolated dependency fixture manifests under `tests/Compatibility/`.

**Interfaces:** Publish supported configuration, the contract, storage lifecycle, explicit read/write API, and dependency compatibility limits.

- [ ] Build separate Composer installations for these scenarios; use a Composer path repository so each test environment consumes this checkout without rewriting the root manifest:

| Scenario | Required outcome |
| --- | --- |
| No translation package | Core create/read/publish/accept works; no integration class autoload |
| Spatie installed, driver null | Identical scalar behavior; no translation-table queries |
| Spatie enabled with migrations | All content, editor, notification, and copy tests pass |
| Spatie removed after prior use | Existing source content readable; stored additional locales untouched |
| Custom relational driver, no Spatie | Same contract/render/edit/copy tests pass |
| Installed Spatie, missing migrations | Actionable setup error; no silent data loss |
| Invalid custom driver or locale config | Clear configuration exception |

- [ ] Exercise supported Laravel versions with matching Testbench versions and actual PHP requirements. Spatie 6.14.1 requires PHP 8.3; keep the core PHP 8.2 absence job separate. Verify any older-v6 compatibility claim with a separate job before documenting it. Add Testbench 11/Pest compatibility only as needed for the already-declared Laravel 13 range, preserving existing constraint intent.
- [ ] Include SQLite behavior tests and a MySQL integration job for JSON, uniqueness, transaction rollback, parent row locking, and concurrent locale edits. Verify no source/translation partial write survives an error.
- [ ] Document the exact opt-in setup sequence:

```bash
composer require spatie/laravel-translatable:^6.0
php artisan vendor:publish --tag=legal-documents-spatie-migrations
php artisan migrate
```

Then set the driver, actual source language, and locales in the published config; refresh configuration caches and restart long-lived workers using the application's normal deployment process. Setting a driver never runs a migration.

- [ ] Document a custom adapter binding using `'driver' => App\Legal\MyTranslationDriver::class`, with the four full contract signatures and a link to the relational fixture. Explain that custom packages need their own storage/models; no automatic interoperability or storage conversion is promised.
- [ ] Document disabling/removal, source-only model attributes/search/sort, published view overrides, native Spatie API limitations, and the unchanged published-content editing/audit limitation. Keep UI translation instructions separate from document translation instructions.
- [ ] Run the complete matrix once after the final changes. Run `composer validate --strict` and `git diff --check`. Record exact resolved dependency versions and test results in the eventual implementation handoff.

**Deliverable:** Tested optional installation and a documented extension point. Suggested commit: `docs: document optional translation drivers and compatibility`.

## Completion criteria

- Single-language users do not install Spatie or run its migrations.
- Multilingual users install Spatie, run additive migrations, configure locales, and edit/view translated content.
- Removing Spatie preserves usable source content and leaves additional data intact.
- A relational backend passes the same behavior tests without Spatie present.
- New versions copy all translations; changing locale does not change acceptance requirements.
- No dependency-presence heuristic silently changes storage or behavior.

## Plan review record

The repository and upstream APIs were reviewed; the companion-model approach was independently challenged against configurable subclasses. This plan incorporates the resulting constraints: explicit model read API, fixed source locale, exact backend reads, batch reads without singleton state, and same-connection transactions. Actual implementation and runtime results are recorded separately in the linked implementation record.
