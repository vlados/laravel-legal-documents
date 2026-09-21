<?php

namespace Vlados\LegalDocuments\Translations\Spatie;

use Illuminate\Database\Eloquent\Model;
use Vlados\LegalDocuments\Contracts\TranslationDriver;
use Vlados\LegalDocuments\Exceptions\InvalidTranslation;
use Vlados\LegalDocuments\Exceptions\InvalidTranslationConfiguration;
use Vlados\LegalDocuments\Models\LegalDocument;
use Vlados\LegalDocuments\Models\LegalDocumentType;
use Vlados\LegalDocuments\Translations\Spatie\Models\DocumentTranslations;
use Vlados\LegalDocuments\Translations\Spatie\Models\DocumentTypeTranslations;

class SpatieTranslationDriver implements TranslationDriver
{
    private function storage(Model $record): array
    {
        [$class, $foreignKey] = match (true) {
            $record instanceof LegalDocument => [DocumentTranslations::class, 'legal_document_id'],
            $record instanceof LegalDocumentType => [DocumentTypeTranslations::class, 'legal_document_type_id'],
            default => throw new InvalidTranslation('Unsupported translation record.'),
        };
        if (! $record->exists || $record->getKey() === null) {
            throw new InvalidTranslation('Translation storage requires a persisted parent.');
        }
        $model = (new $class)->setConnection($record->getConnection()->getName());

        return [$model, $foreignKey];
    }

    private function assertMigrated(Model $model): void
    {
        if (! $model->getConnection()->getSchemaBuilder()->hasTable($model->getTable())) {
            throw new InvalidTranslationConfiguration('Publish legal-documents-spatie-migrations and run php artisan migrate on the parent database connection before using the Spatie driver.');
        }
    }

    public function readAll(Model $record): array
    {
        return $this->readMany([$record])[0];
    }

    public function readMany(array $records): array
    {
        $groups = [];
        $result = array_fill(0, count($records), []);
        foreach (array_values($records) as $index => $record) {
            [$storage, $foreignKey] = $this->storage($record);
            $key = $storage->getConnectionName().':'.$storage->getTable();
            $groups[$key]['storage'] = $storage;
            $groups[$key]['foreignKey'] = $foreignKey;
            $groups[$key]['ids'][$index] = $record->getKey();
        }
        foreach ($groups as $group) {
            $storage = $group['storage'];
            $foreignKey = $group['foreignKey'];
            $this->assertMigrated($storage);
            $rows = $storage->newQuery()->whereIn($foreignKey, array_values($group['ids']))->get()->keyBy($foreignKey);
            foreach ($group['ids'] as $index => $id) {
                $row = $rows->get($id);
                if ($row) {
                    foreach ($row->getTranslations() as $field => $translations) {
                        foreach ($translations as $locale => $value) {
                            $result[$index][$locale][$field] = $value;
                        }
                    }
                    foreach ($result[$index] as &$values) {
                        $values = array_replace(array_fill_keys($row->getTranslatableAttributes(), null), $values);
                    }
                    unset($values);
                }
            }
        }

        return $result;
    }

    public function putLocale(Model $record, string $locale, array $values): void
    {
        $this->mutate($record, function ($row) use ($locale, $values) {
            foreach ($row->getTranslatableAttributes() as $field) {
                $value = $values[$field] ?? null;
                if ($value === null) {
                    $row->forgetTranslation($field, $locale);
                } else {
                    $row->setTranslation($field, $locale, $value);
                }
            }
            $row->save();
        });
    }

    public function forgetLocale(Model $record, string $locale): void
    {
        $this->mutate($record, function ($row) use ($locale) {
            if ($row->exists) {
                $row->forgetAllTranslations($locale)->save();
            }
        });
    }

    private function mutate(Model $record, callable $callback): void
    {
        [$storage, $foreignKey] = $this->storage($record);
        $this->assertMigrated($storage);
        $record->getConnection()->transaction(function () use ($record, $storage, $foreignKey, $callback) {
            // Lock the parent even before a companion row exists, serializing first writes too.
            $record->newQuery()->whereKey($record->getKey())->lockForUpdate()->firstOrFail();
            $row = $storage->newQuery()->lockForUpdate()->firstOrNew([$foreignKey => $record->getKey()]);
            $callback($row);
        });
    }
}
