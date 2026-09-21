<?php

namespace Vlados\LegalDocuments\Translations;

final readonly class LocalizedContent
{
    public function __construct(
        public string $requestedLocale,
        public string $locale,
        public array $values,
        public bool $isFallback,
    ) {}
}
