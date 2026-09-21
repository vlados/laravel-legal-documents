<?php

namespace Vlados\LegalDocuments\Traits;

use Vlados\LegalDocuments\Translations\ContentTranslator;
use Vlados\LegalDocuments\Translations\LocalizedContent;

trait HasLocalizedContent
{
    public function localized(?string $locale = null): LocalizedContent
    {
        return app(ContentTranslator::class)->localize($this, $locale);
    }

    public function saveTranslation(string $locale, array $values): void
    {
        app(ContentTranslator::class)->saveLocale($this, $locale, $values);
    }

    public function forgetTranslation(string $locale): void
    {
        app(ContentTranslator::class)->forgetLocale($this, $locale);
    }
}
