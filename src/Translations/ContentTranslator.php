<?php

namespace Vlados\LegalDocuments\Translations;

use Illuminate\Database\Eloquent\Model;
use Vlados\LegalDocuments\Contracts\TranslationDriver;
use Vlados\LegalDocuments\Exceptions\InvalidTranslation;
use Vlados\LegalDocuments\Exceptions\InvalidTranslationConfiguration;
use Vlados\LegalDocuments\Models\LegalDocument;
use Vlados\LegalDocuments\Models\LegalDocumentType;

class ContentTranslator
{
    public function __construct(private TranslationDriver $driver) {}

    public function available(): bool
    {
        $this->sourceLocale();

        return ! $this->driver instanceof SingleLanguageDriver;
    }

    public function sourceLocale(): string
    {
        $source = config('legal-documents.translations.source_locale');
        $locales = config('legal-documents.translations.locales', []);
        $fallback = $this->configuredFallbackLocale();
        $configured = config('legal-documents.translations.driver') !== null;
        if (! is_array($locales) || ! array_is_list($locales)) {
            throw new InvalidTranslationConfiguration('translations.locales must be a list of locale identifiers.');
        }

        if ($configured && (! is_string($source) || trim($source) === '' || ! is_array($locales) || ! in_array($source, $locales, true))) {
            throw new InvalidTranslationConfiguration('Set a fixed translations.source_locale and include it in translations.locales before enabling translations.');
        }

        foreach (is_array($locales) ? $locales : [] as $locale) {
            if (! is_string($locale) || ! preg_match('/\A[a-zA-Z0-9]+(?:[-_][a-zA-Z0-9]+)*\z/D', $locale)) {
                throw new InvalidTranslationConfiguration('Translation locales must use language identifiers such as en, bg, or pt-BR.');
            }
        }

        if ($fallback !== null && (! is_string($fallback) || ! in_array($fallback, $locales, true))) {
            throw new InvalidTranslationConfiguration('The effective translations.fallback_locale (including any inherited Spatie fallback) must be included in translations.locales.');
        }

        $source ??= config('app.fallback_locale', 'en');
        if (! is_string($source) || trim($source) === '') {
            throw new InvalidTranslationConfiguration('Configure a nonempty source locale or application fallback locale.');
        }

        return $source;
    }

    private function configuredFallbackLocale(): mixed
    {
        if (config('legal-documents.translations.driver') === 'spatie'
            && ! $this->driver instanceof SingleLanguageDriver
            && class_exists(\Spatie\Translatable\Translatable::class)) {
            $fallback = app(\Spatie\Translatable\Translatable::class)->fallbackLocale ?? null;
            if ($fallback !== null) {
                return $fallback;
            }
        }

        return config('legal-documents.translations.fallback_locale');
    }

    public function additionalLocales(): array
    {
        return array_values(array_diff(config('legal-documents.translations.locales', []), [$this->sourceLocale()]));
    }

    public function fields(Model $record): array
    {
        return match (true) {
            $record instanceof LegalDocument => ['title', 'content', 'summary_of_changes'],
            $record instanceof LegalDocumentType => ['name', 'description'],
            default => throw new InvalidTranslation('Only legal documents and document types support content translations.'),
        };
    }

    private function assertPersisted(Model $record): void
    {
        $this->fields($record);
        if (! $record->exists || $record->getKey() === null) {
            throw new InvalidTranslation('Save the record before reading or writing translations.');
        }
    }

    /** Exact stored additional translations, for editors and adapter-independent copying. */
    public function translations(Model $record): array
    {
        $this->assertPersisted($record);
        $source = $this->sourceLocale();
        $translations = $this->driver->readAll($record);
        unset($translations[$source]);

        return $translations;
    }

    public function localize(Model $record, ?string $locale = null): LocalizedContent
    {
        return $this->localizeMany([$record], $locale)[0];
    }

    /** @param list<Model> $records @return list<LocalizedContent> */
    public function localizeMany(array $records, ?string $locale = null): array
    {
        $source = $this->sourceLocale();
        $locale ??= app()->getLocale();
        foreach ($records as $record) {
            $this->assertPersisted($record);
        }
        if ($records === []) {
            return [];
        }
        $records = array_values($records);
        $maps = $locale === $source ? array_fill(0, count($records), []) : $this->driver->readMany($records);
        if (! array_is_list($maps) || count($maps) !== count($records)) {
            throw new InvalidTranslation('TranslationDriver::readMany must return one map per input record, in the same order.');
        }

        return array_map(fn (Model $record, array $map) => $this->resolve($record, $map, $locale, $source), $records, $maps);
    }

    private function resolve(Model $record, array $translations, string $requested, string $source): LocalizedContent
    {
        $sourceValues = [];
        foreach ($this->fields($record) as $field) {
            $sourceValues[$field] = $record->getAttribute($field);
        }
        $translations[$source] = $sourceValues;
        $allowed = array_merge(config('legal-documents.translations.locales', []), [$source]);
        $candidates = array_unique(array_filter([
            in_array($requested, $allowed, true) ? $requested : null,
            $this->configuredFallbackLocale(), $source,
        ]));
        foreach ($candidates as $candidate) {
            if (! isset($translations[$candidate]) || ! is_array($translations[$candidate])) {
                continue;
            }
            try {
                $values = $this->normalize($record, $translations[$candidate]);
            } catch (InvalidTranslation) {
                continue;
            }

            return new LocalizedContent($requested, $candidate, $values, $requested !== $candidate);
        }

        throw new InvalidTranslation('The document has no complete translation or source-language content.');
    }

    public function isBlank(mixed $value): bool
    {
        return $value === null || (is_string($value) && preg_replace('/[\s\x{00A0}\x{200B}]+/u', '', html_entity_decode(strip_tags($value), ENT_QUOTES | ENT_HTML5, 'UTF-8')) === '');
    }

    public function normalize(Model $record, array $values): array
    {
        $fields = $this->fields($record);
        if (array_diff(array_keys($values), $fields) !== []) {
            throw new InvalidTranslation('Unexpected translated field. Allowed fields: '.implode(', ', $fields).'.');
        }
        $required = $record instanceof LegalDocument ? ['title', 'content'] : ['name'];
        $normalized = [];
        foreach ($fields as $field) {
            $value = $values[$field] ?? null;
            if ($value !== null && ! is_string($value)) {
                throw new InvalidTranslation("The {$field} translation must be a string or null.");
            }
            $blank = $this->isBlank($value);
            if (in_array($field, $required, true) && $blank) {
                throw new InvalidTranslation("The {$field} translation is required.");
            }
            if (in_array($field, ['title', 'name'], true) && mb_strlen($value ?? '') > 255) {
                throw new InvalidTranslation("The {$field} translation may not exceed 255 characters.");
            }
            $normalized[$field] = $blank ? null : $value;
        }

        return $normalized;
    }

    public function validateLocale(string $locale): void
    {
        if ($locale !== $this->sourceLocale() && ! in_array($locale, $this->additionalLocales(), true)) {
            throw new InvalidTranslation("The locale {$locale} is not configured for legal documents.");
        }
    }

    public function saveLocale(Model $record, string $locale, array $values): void
    {
        $this->assertPersisted($record);
        $this->validateLocale($locale);
        $values = $this->normalize($record, $values);
        $record->getConnection()->transaction(function () use ($record, $locale, $values) {
            $locked = $record->newQuery()->whereKey($record->getKey())->lockForUpdate()->firstOrFail();
            if ($locale === $this->sourceLocale()) {
                $locked->fill($values)->save();
                foreach (array_keys($values) as $field) {
                    $record->setAttribute($field, $locked->getAttribute($field));
                }
                $record->syncOriginalAttributes(array_keys($values));
            } else {
                $this->driver->putLocale($record, $locale, $values);
            }
        });
    }

    public function forgetLocale(Model $record, string $locale): void
    {
        $this->assertPersisted($record);
        $this->validateLocale($locale);
        if ($locale === $this->sourceLocale()) {
            throw new InvalidTranslation('The source language cannot be removed.');
        }
        $record->getConnection()->transaction(function () use ($record, $locale) {
            $record->newQuery()->whereKey($record->getKey())->lockForUpdate()->firstOrFail();
            $this->driver->forgetLocale($record, $locale);
        });
    }

    public function copyTranslations(Model $source, Model $target): void
    {
        $this->assertPersisted($source);
        $this->assertPersisted($target);
        if ($this->fields($source) !== $this->fields($target) || $source->getConnection() !== $target->getConnection()) {
            throw new InvalidTranslation('Translations can only be copied between matching records on the same database connection.');
        }
        $target->getConnection()->transaction(function () use ($source, $target) {
            foreach ($this->translations($source) as $locale => $values) {
                // Preserve stored locales even if no longer enabled for editing.
                $this->driver->putLocale($target, $locale, $values);
            }
        });
    }
}
