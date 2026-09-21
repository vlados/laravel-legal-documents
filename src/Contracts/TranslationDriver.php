<?php

namespace Vlados\LegalDocuments\Contracts;

use Illuminate\Database\Eloquent\Model;

interface TranslationDriver
{
    /** @return array<string, array<string, string|null>> Exact additional locales, without fallback. */
    public function readAll(Model $record): array;

    /**
     * @param list<Model> $records
     * @return list<array<string, array<string, string|null>>> Same order and count as the input.
     */
    public function readMany(array $records): array;

    /** Replace one complete locale, preserving all others, on the parent's database connection. */
    public function putLocale(Model $record, string $locale, array $values): void;

    public function forgetLocale(Model $record, string $locale): void;
}
