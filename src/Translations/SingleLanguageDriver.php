<?php

namespace Vlados\LegalDocuments\Translations;

use Illuminate\Database\Eloquent\Model;
use Vlados\LegalDocuments\Contracts\TranslationDriver;
use Vlados\LegalDocuments\Exceptions\TranslationDriverUnavailable;

class SingleLanguageDriver implements TranslationDriver
{
    public function readAll(Model $record): array
    {
        return [];
    }

    public function readMany(array $records): array
    {
        return array_fill(0, count($records), []);
    }

    public function putLocale(Model $record, string $locale, array $values): void
    {
        throw new TranslationDriverUnavailable('Additional translations require an available legal-documents translation driver. Install spatie/laravel-translatable and enable its driver, or configure a custom driver.');
    }

    public function forgetLocale(Model $record, string $locale): void
    {
        $this->putLocale($record, $locale, []);
    }
}
