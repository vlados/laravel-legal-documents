<?php

namespace Vlados\LegalDocuments\Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;
use Vlados\LegalDocuments\Contracts\TranslationDriver;

class RelationalTranslationDriver implements TranslationDriver
{
    public bool $failCopy = false;

    public function readAll(Model $record): array
    {
        return $this->readRecords([$record], $record->getConnection()->transactionLevel() > 0)[0];
    }

    public function readMany(array $records): array
    {
        return $this->readRecords($records);
    }

    private function readRecords(array $records, bool $lockForUpdate = false): array
    {
        $result = [];
        foreach ($records as $record) {
            $query = $record->getConnection()->table('fixture_translations')
                ->where('parent_type', $record->getTable())->where('parent_id', $record->getKey());
            if ($lockForUpdate) {
                $query->lockForUpdate();
            }
            $rows = $query->get();
            $map = [];
            foreach ($rows as $row) {
                $fields = $record instanceof \Vlados\LegalDocuments\Models\LegalDocument
                    ? ['title', 'content', 'summary_of_changes'] : ['name', 'description'];
                $map[$row->locale] = array_intersect_key((array) $row, array_flip($fields));
            }
            $result[] = $map;
        }
        return $result;
    }

    public function putLocale(Model $record, string $locale, array $values): void
    {
        if ($this->failCopy && $record->version === '2.0' && $locale === 'de') {
            throw new \RuntimeException('Translation copy failed');
        }
        $record->getConnection()->table('fixture_translations')->updateOrInsert([
            'parent_type' => $record->getTable(), 'parent_id' => $record->getKey(), 'locale' => $locale,
        ], $values);
    }

    public function forgetLocale(Model $record, string $locale): void
    {
        $record->getConnection()->table('fixture_translations')->where([
            'parent_type' => $record->getTable(), 'parent_id' => $record->getKey(), 'locale' => $locale,
        ])->delete();
    }
}
