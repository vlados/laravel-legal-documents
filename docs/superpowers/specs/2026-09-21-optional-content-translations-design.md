# Optional content translations

Status: implemented on `feature/optional-content-translations`. See the [implementation record](../plans/2026-09-21-optional-content-translations-results.md) for verification and remaining CI checks.

## Goal and confirmed requirements

Support translated legal documents through an optional, bundled integration with `spatie/laravel-translatable`, while allowing applications to supply a different translation backend. The package must work in one language without any translation dependency.

The user confirmed that source-language operation must also survive removing Spatie after translations have been created. Preserve a source-language copy in the existing scalar columns. The source language is the language already stored there; it is not necessarily English.

## Original code at planning time

- `composer.json:25` requires Spatie, but neither domain model uses its translation trait. The working tree also contains an unrelated Laravel 13 compatibility edit; preserve it.
- `LegalDocument` stores `title`, `content`, and nullable `summary_of_changes` as strings. `LegalDocumentType` stores `name` and nullable `description` as strings.
- Models and relationships are referenced directly throughout the package. Replacing the domain model classes would require updating relationships, queries, Filament resources, Livewire, and a query embedded in Blade.
- Both document duplication actions use `replicate()`, which will not copy related translation records.
- Document acceptance is unique per user/document ID. Publication selects one current version per document type.
- Frontend and notification interface strings already use Laravel language files. Many Filament labels are hardcoded Bulgarian; this is separate from translating database content.
- The repository has no tests, runner configuration, or `composer test` script, although the README advertises that command.

## Alternatives and recommendation

| Approach | Benefit | Cost |
| --- | --- | --- |
| Required Spatie trait on domain models | Familiar native Spatie model API | Does not satisfy optional installation or independent backends |
| Configurable domain model subclasses and a translation contract | Native Spatie attributes; custom models can use other packages | Must replace every model lookup, convert existing scalar fields, and explicitly convert/export data before returning to scalar models |
| **Translation contract plus companion storage models** | Existing source data and domain models continue working; backends own their storage | Two extra tables for Spatie; explicit content API and package-owned editing controls |

Recommend the third approach. This is a package translation API with a genuine Spatie storage adapter. It deliberately does not claim that `LegalDocument` itself implements Spatie's model API.

## Public behavior

```php
// Existing API remains source-language content.
$document->title;
$document->content;

// New backend-independent API returns a consistent language for the record.
$content = $document->localized('bg');
$content->locale;                    // Actual content language, possibly fallback
$content->requestedLocale;           // Requested language
$content->isFallback;
$content->values['title'];
$content->values['content'];
$content->values['summary_of_changes'];

// Persistence is explicit; the model must already be saved.
$document->saveTranslation('bg', [
    'title' => 'Общи условия',
    'content' => '<p>Съдържание...</p>',
    'summary_of_changes' => null,
]);

$document->forgetTranslation('bg');
```

Provide the same methods for document types, with `name` and `description`. Do not override Eloquent attribute access, scalar assignment, or `toArray()`. Existing consumers continue receiving scalar source content; applications and published Blade overrides opt into `localized()`.

## Configuration and dependency behavior

```php
'translations' => [
    'driver' => null,              // null, 'spatie', or adapter class name
    'source_locale' => null,       // Must be explicitly set when enabling translations
    'locales' => [],               // Example: ['en', 'bg']
    'fallback_locale' => null,     // Optional; source language is the final fallback
],
```

- `null` means single-language operation even if Spatie happens to be installed for another feature.
- Enabling translations requires a fixed `source_locale` matching the existing content and a locale allowlist containing that language. Do not infer the source from the current request locale. Changing the source language later is a data migration, outside this release.
- In default single-language configuration, the locale label can use `config('app.fallback_locale')`; setting `source_locale` is still recommended. Do not backfill historical acceptance locales from this assumption.
- `'spatie'` selects the bundled adapter only if `trait_exists(Spatie\Translatable\HasTranslations::class)` succeeds. Integration classes must not be autoloaded before that check.
- If Spatie is absent, public reads and source editing work in single-language mode. Non-source writes fail with a specific exception explaining that the driver is unavailable; the UI hides translation controls and shows a setup notice to administrators when Spatie was explicitly configured.
- Invalid driver classes, invalid locale configuration, missing tables for an installed/enabled adapter, and storage failures are real errors. Do not turn every adapter exception into silent source fallback.
- Move Spatie from `require` to `suggest`; install it only in integration test jobs. Add `illuminate/database` as a direct dependency because the package uses Eloquent and can no longer obtain it transitively through Spatie.
- The editor uses the package contract. It does not require `lara-zeus/spatie-translatable`; remove the suggestion that this plugin is required for translation support.

## Storage and contract

Keep the original source fields unchanged. The Spatie adapter adds two opt-in tables:

| Table | Parent key | JSON fields |
| --- | --- | --- |
| `legal_document_spatie_translations` | unique `legal_document_id`, cascading foreign key | `title`, `content`, `summary_of_changes` |
| `legal_document_type_spatie_translations` | unique `legal_document_type_id`, cascading foreign key | `name`, `description` |

One companion record stores all additional locales for its parent. Only companion models use Spatie's `HasTranslations`; explicitly set their table names and `$translatable` fields. Store only additional locales so there is no second copy of the source text to synchronize. JSON columns are nullable; a missing translation map is empty. Preserve existing HTML content format.

Publish new adapter migrations under a separate `legal-documents-spatie-migrations` tag, excluded from the set published by the core migration tag. Existing installations need no content conversion or backfill. Running the new migrations before enabling the driver is safe. Disabling/removing the driver leaves translation rows intact; dropping these tables is destructive and is not part of disabling support.

```php
namespace Vlados\LegalDocuments\Contracts;

use Illuminate\Database\Eloquent\Model;

interface TranslationDriver
{
    /** @return array<string, array<string, string|null>> Locale => complete field set. */
    public function readAll(Model $record): array;

    /** @param list<Model> $records
     *  @return list<array<string, array<string, string|null>>> Same order/count as input.
     */
    public function readMany(array $records): array;

    /** Replace this locale's complete field set; preserve every other locale. */
    public function putLocale(Model $record, string $locale, array $values): void;

    public function forgetLocale(Model $record, string $locale): void;
}
```

The contract is storage-only: no fallback, publication, acceptance, Filament, or route behavior. Records must be persisted domain models; validate the supported model types. `readMany()` gives bounded database access without forcing stateful backend caching. Keep results in the current render/send operation, never a process-wide singleton.

`putLocale()` receives a complete allowlisted field set. Required fields must contain nonblank strings; omitted optional fields normalize to null, and blank optional strings normalize to null. Unknown fields and unsupported locales are rejected by the core service. Source-language saves update only the existing scalar fields. Source-language deletion is rejected. Adapter storage must never shadow the source locale.

`SingleLanguageDriver` returns empty maps for reads and rejects translation mutations; source writes bypass it. A custom adapter can use another package or relational storage. It must preserve the same semantics, use the parent's database connection for atomic writes/copies, and arrange cascading cleanup when the parent is deleted. Remote storage and nontransactional adapters are outside the first supported contract.

Ship a relational test adapter to prove the abstraction does not depend on JSON or Spatie. A production Astrotomic adapter is not included in this release; its host-side models/migrations would be supplied by the application or a later adapter package.

## Locale resolution and fallback

The core `ContentTranslator` owns resolution. For a document, choose the first complete candidate in this order: requested locale, configured fallback locale, source locale; deduplicate candidates. A complete document requires both nonblank title and body. A complete type requires a nonblank name. Unsupported requested locales go directly to configured fallback/source.

Resolve title, body, and summary together from the selected document locale. If the selected locale has no optional summary, return null instead of mixing in a summary from another language. Resolve type labels separately because a type can have different translation coverage. Return actual and requested locales in `LocalizedContent`; use actual locale for the content `lang` attribute and a fallback indication.

If even the source document is incomplete, raise a content validation error. Do not render an empty document for acceptance. Do not use Spatie's global fallback configuration or callbacks to choose legal document content. Read its stored maps with `getTranslations()` and apply package rules locally.

## Application integration

- Resolve the current HTTP application locale at rendering time. The host application owns locale middleware and locale URLs; this release does not translate slugs or introduce locale-prefixed routes.
- Update public title/body, revision summaries, navigation labels/descriptions, acceptance list/modal, and mail/database notifications to consume localized content.
- Move the navigation query from Blade into the Livewire component and batch content reads for related types and revision history. `readMany()` must not cause one query per displayed row.
- Resolve queued notification content inside `toMail()`/`toDatabase()`, using Laravel's active recipient locale. Respect `HasLocalePreference`; never set one model's locale and reuse it between recipients.
- Persist `content_locale` alongside the translated fields in new database notification payloads. Existing notification records remain readable.
- Keep acceptance identity and reacceptance behavior per document version, independent of viewing language. This release does not introduce per-language publication or acceptance records.
- Centralize creation of a new version in `LegalDocument::createNewVersion(string $version): static`: save the draft and copy every translation in one database transaction. Both duplication actions call it.

## Filament and interface localization

Use ordinary source fields plus a separate “Translations” section with one tab per additional configured locale. Tabs have independent form state; source fields are never rebound when switching locale. Save only populated translation tabs and explicit removals through `ContentTranslator`, together with source changes in one transaction. Inactive tabs must preserve their rich text HTML, and a partial locale (title without body) must fail validation.

Keep list search/sort and relationship selectors based on the scalar source fields for this release, with source-language labels on those controls. This keeps SQL pagination/search predictable for every adapter. Locale-aware database querying is a later capability, not an implied feature of `localized()`.

Generate a type slug only on initial source-name entry when the slug is empty; translating a name must never change the URL. Inline type creation remains a source-language operation, with translations editable on the type's edit screen.

Move Filament interface strings to the existing English/Bulgarian Laravel language files. Interface language follows Laravel independently of the content backend. Validate the actual Filament API/version matrix: the current resources use `Filament\Schemas\Schema`, while Composer advertises both major versions 3 and 4.

## Boundaries and follow-up decisions

- Source-language model attributes remain scalar. Native `$document->getTranslation()` and third-party plugins expecting `HasTranslations` on the domain model are not supported by this design.
- Existing editing of published documents is not made immutable in this compatibility release. Significant wording changes should use a new version; the current system cannot reconstruct the exact historical wording after an in-place edit. A snapshot/immutability feature and acceptance locale audit fields need a separate design, rather than claiming this translation adapter solves them.
- Translation search, automatic translation, source-language reassignment, and live migration between storage drivers are outside scope. Changing drivers does not automatically convert data; source content remains available.

## Sources checked

- [Spatie installation](https://spatie.be/docs/laravel-translatable/v6/installation-setup): the integration requires its trait and translation map storage.
- [Spatie translation API](https://spatie.be/docs/laravel-translatable/v6/basic-usage/getting-and-settings-translations): setting/getting translations and explicit saves.
- [Spatie fallback behavior](https://spatie.be/docs/laravel-translatable/v6/basic-usage/handling-missing-translations): per-attribute/global fallback is separate from the proposed whole-document policy.
- [Spatie 6.14.1 composer.json](https://github.com/spatie/laravel-translatable/blob/6.14.1/composer.json): this inspected tag requires PHP 8.3 and allows Laravel 11–13. Do not equate the entire v6 series with PHP 8.2 support.
- [Laravel notification localization](https://laravel.com/docs/12.x/notifications#localizing-notifications): recipient locale preferences and queued locale handling.
- [Astrotomic installation](https://docs.astrotomic.info/laravel-translatable/installation): demonstrates why other backends may need their own models and related-table schema.
