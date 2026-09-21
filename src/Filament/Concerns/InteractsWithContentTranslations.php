<?php

namespace Vlados\LegalDocuments\Filament\Concerns;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;
use Vlados\LegalDocuments\Exceptions\InvalidTranslation;
use Vlados\LegalDocuments\Translations\ContentTranslator;

trait InteractsWithContentTranslations
{
    #[Locked]
    public array $originalContentTranslations = [];

    #[Locked]
    public array $originalSourceContent = [];

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $translator = app(ContentTranslator::class);
        $this->originalSourceContent = array_intersect_key($data, array_flip($translator->fields($this->getRecord())));
        $this->originalContentTranslations = $translator->available()
            ? array_intersect_key($translator->translations($this->getRecord()), array_flip($translator->additionalLocales()))
            : [];

        return array_merge($data, [
            'content_translations' => $this->originalContentTranslations,
            'removed_content_locales' => [],
        ]);
    }

    protected function handleRecordCreation(array $data): Model
    {
        // Create & create another reuses this page instance with a new record.
        $this->originalContentTranslations = [];
        $this->originalSourceContent = [];
        $model = new ($this->getModel());
        [$source, $changes, $removals] = $this->translationChanges($model, $data);

        return $model->getConnection()->transaction(function () use ($source, $changes, $removals) {
            $record = parent::handleRecordCreation($source);
            $this->persistTranslationChanges($record, $changes, $removals);

            return $record;
        });
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        [$source, $changes, $removals] = $this->translationChanges($record, $data);
        foreach ($this->originalSourceContent as $field => $original) {
            if (array_key_exists($field, $source) && $source[$field] === $original) {
                unset($source[$field]);
            }
        }

        return $record->getConnection()->transaction(function () use ($record, $source, $changes, $removals) {
            $fresh = $record->newQuery()->whereKey($record->getKey())->lockForUpdate()->firstOrFail();
            $record->setRawAttributes($fresh->getAttributes(), true);
            $record = parent::handleRecordUpdate($record, $source);
            $this->persistTranslationChanges($record, $changes, $removals);
            foreach (app(ContentTranslator::class)->fields($record) as $field) {
                $this->originalSourceContent[$field] = $record->getAttribute($field);
                $this->data[$field] = $record->getAttribute($field);
            }

            return $record;
        });
    }

    private function translationChanges(Model $record, array $data): array
    {
        $translator = app(ContentTranslator::class);
        $translations = $data['content_translations'] ?? [];
        $removals = $data['removed_content_locales'] ?? [];
        unset($data['content_translations'], $data['removed_content_locales']);
        if (! is_array($translations) || ! is_array($removals)) {
            throw ValidationException::withMessages(['data.content_translations' => __('legal-documents::legal-documents.invalid_translation')]);
        }
        $allowed = $translator->available() ? $translator->additionalLocales() : [];
        foreach (array_unique(array_merge(array_keys($translations), array_keys($removals))) as $locale) {
            if (! in_array($locale, $allowed, true)) {
                throw ValidationException::withMessages(['data.content_translations' => __('legal-documents::legal-documents.invalid_translation')]);
            }
        }
        $changes = [];
        foreach ($translations as $locale => $values) {
            if (! is_array($values) || array_diff(array_keys($values), $translator->fields($record)) !== []) {
                throw ValidationException::withMessages(["data.content_translations.{$locale}" => __('legal-documents::legal-documents.invalid_translation')]);
            }
            if (($removals[$locale] ?? false) === true) {
                continue;
            }
            $populated = collect($values)->contains(fn ($value) => ! $translator->isBlank($value));
            if (! $populated && ! isset($this->originalContentTranslations[$locale])) {
                continue;
            }
            try {
                $values = $translator->normalize($record, $values);
            } catch (InvalidTranslation $exception) {
                throw ValidationException::withMessages(["data.content_translations.{$locale}.".$translator->fields($record)[0] => $exception->getMessage()]);
            }
            // Unchanged tabs must not overwrite another editor's more recent save.
            if ($values !== ($this->originalContentTranslations[$locale] ?? null)) {
                $changes[$locale] = $values;
            }
        }
        foreach ($removals as $locale => $remove) {
            if (! is_bool($remove)) {
                throw ValidationException::withMessages(["data.removed_content_locales.{$locale}" => __('legal-documents::legal-documents.invalid_translation')]);
            }
        }

        return [$data, $changes, array_keys(array_filter($removals))];
    }

    private function persistTranslationChanges(Model $record, array $changes, array $removals): void
    {
        $translator = app(ContentTranslator::class);
        $original = $this->originalContentTranslations;
        foreach ($changes as $locale => $values) {
            $translator->saveLocale($record, $locale, $values);
            $original[$locale] = $values;
        }
        foreach ($removals as $locale) {
            $translator->forgetLocale($record, $locale);
            unset($original[$locale]);
        }
        $this->originalContentTranslations = $original;
        foreach ($removals as $locale) {
            $this->data['content_translations'][$locale] = [];
            $this->data['removed_content_locales'][$locale] = false;
        }
    }
}
