<?php

namespace Vlados\LegalDocuments\Contracts;

use Illuminate\Database\Eloquent\Model;

interface TranslationDriver
{
    /**
     * Return exact additional locales without fallback. When called within a transaction,
     * use a current/locking read so version copies include changes committed before the parent lock.
     *
     * @return array<string, array<string, string|null>>
     */
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
