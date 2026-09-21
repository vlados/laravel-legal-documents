<?php

namespace Vlados\LegalDocuments\Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;
use Vlados\LegalDocuments\Contracts\TranslationDriver;

class ArrayTranslationDriver implements TranslationDriver
{
    public array $data = [];
    public int $reads = 0;

    private function key(Model $record): string
    {
        return $record->getConnectionName().':'.$record->getTable().':'.$record->getKey();
    }

    public function seed(Model $record, array $translations): void
    {
        $this->data[$this->key($record)] = $translations;
    }

    public function readAll(Model $record): array
    {
        return $this->data[$this->key($record)] ?? [];
    }

    public function readMany(array $records): array
    {
        $this->reads++;
        return array_map(fn ($record) => $this->readAll($record), $records);
    }

    public function putLocale(Model $record, string $locale, array $values): void
    {
        $this->data[$this->key($record)][$locale] = $values;
    }

    public function forgetLocale(Model $record, string $locale): void
    {
        unset($this->data[$this->key($record)][$locale]);
    }
}
