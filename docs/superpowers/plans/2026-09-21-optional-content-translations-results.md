# Optional content translations implementation record

Implemented on 2026-09-21 on `feature/optional-content-translations`. The pre-existing Laravel 13 dependency constraints are preserved. The results below record verification before commit and pull-request publication.

## Delivered

- Tasks 1–3: optional dependency boundary, scalar source preservation, vendor-neutral storage contract, whole-document fallback, and a bundled Spatie adapter using separately published companion-table migrations.
- Task 4: localized public content, batched related content, fallback language indication, and recipient-localized notifications without changing acceptance identity.
- Task 5: generic Filament translation tabs, atomic saves, explicit deletion, stable existing slugs, and English/Bulgarian interface labels.
- Task 6: transactional version creation that copies all stored locales, including disabled locales, without copying acceptances. Relational-driver tests cover rollback after a partial copy.
- Task 7: isolated Composer installations, a true two-process dependency-removal check, compatibility/MySQL CI definitions, and the [integration guide](../../translations.md).

## Verification

Local PHP version: 8.4.23. All suites below were run after the final code changes.

| Environment/check | Result |
| --- | --- |
| Laravel 13.32.0 / Testbench 11.2.0, no Spatie (`composer test`) | 27 passed, 22 skipped; 63 assertions |
| Laravel 12.69.2 / Testbench 10.11.0, no Spatie (`bash compatibility/test.sh core`) | 27 passed, 22 skipped; 63 assertions |
| Laravel 12.69.2 / Testbench 10.11.0, Spatie 6.14.1, Filament 4.13.4, Livewire 3.8.9 (`bash compatibility/test.sh spatie`) | 46 passed, 3 skipped; 149 assertions |
| Dependency removal (`bash compatibility/test-removal.sh`) | Seed process with Spatie passed (2 assertions); second process without Spatie passed (5 assertions) |
| Composer manifest validation | `composer validate --strict` passed |
| PHP syntax and whitespace | PHP lint of source/tests/migrations/language files and `git diff --check` passed |
| Browser | Bulgarian title edit survived switching to German and saving; both languages retained rich HTML. An incomplete translation was rejected and stored content was unchanged. |

Pest 3.8.7 was used in all local environments. In core runs, integration/UI tests are intentionally skipped because their optional dependencies are absent. In the Spatie run, skips are the dependency-absence scenario, the separately invoked removal lifecycle, and the opt-in MySQL check.

## Review fixes and implementation adjustments

An independent implementation review identified three issues, addressed before the final verification:

1. Translation-only saves could overwrite concurrent source edits. Locked source baselines now omit unchanged source fields and refresh current attributes within the transaction.
2. “Create and create another” could reuse translation baselines. Creation now resets baselines, with regression coverage for repeated translation values.
3. A companion read could use an old MySQL repeatable-read snapshot despite the parent lock. Companion writes now use a locking read; a dedicated two-connection MySQL regression is included in CI.

Compatibility installations live under `compatibility/`, outside the test discovery tree, to avoid recursive traversal through Composer path-repository symlinks. The Filament integration targets version 4, matching the resource APIs already used by this repository. Locale identifiers allow letters, digits, dashes, and underscores; invalid identifiers are rejected before form paths are built. Failure-injection and custom-driver assertions are consolidated in the versioning tests instead of separate test files proposed in the plan.

## Follow-up: shared Spatie fallback settings

The user's subsequent request to reuse Spatie settings is implemented for its global fallback locale. With the Spatie driver enabled, an explicit Spatie fallback takes precedence; an unset/null setting falls back to this package's configuration. Source language, enabled locales, and driver selection remain package settings because Spatie has no corresponding global configuration. Whole-document resolution continues to ignore per-field callbacks and arbitrary-language fallback.

After this follow-up, the Laravel 13 core suite passed 27 tests (28 integration skips, 63 assertions), the Laravel 12 Spatie suite passed 52 tests (3 skips, 160 assertions), and both dependency-removal phases passed again. Targeted regression tests cover precedence, unset/null settings, changes after service resolution, custom/disabled drivers, and inherited-locale validation. Changed PHP files pass syntax checks; `git diff --check` passes. The earlier compatibility table records the initial implementation run.

## Remaining verification

The remote CI matrix has not run. Laravel 11/PHP 8.2 and Laravel 13 with Spatie are configured in CI but were not executed locally. The MySQL regression has not run locally: the installed server requires unavailable credentials and Docker was not running. Its CI service uses a dedicated disposable `legal_documents_test` database. The MySQL job specifically covers current-row behavior within an existing repeatable-read transaction; the broader behavior suite ran on SQLite.

Published-content immutability, translated SQL search/sorting, locale-specific routes, and automatic migration between translation vendors remain outside this feature's scope.
