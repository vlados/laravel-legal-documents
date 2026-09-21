<?php

namespace Vlados\LegalDocuments\Filament;

use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Model;
use Vlados\LegalDocuments\Translations\ContentTranslator;

class ContentTranslationFields
{
    public static function make(Model $model): array
    {
        $translator = app(ContentTranslator::class);
        if (! $translator->available()) {
            return config('legal-documents.translations.driver') === null ? [] : [
                Placeholder::make('translation_setup')->label(__('legal-documents::legal-documents.translations'))
                    ->content(__('legal-documents::legal-documents.translation_driver_unavailable'))->columnSpanFull(),
            ];
        }
        $tabs = [];
        foreach ($translator->additionalLocales() as $locale) {
            $fields = [];
            foreach ($translator->fields($model) as $field) {
                $path = "content_translations.{$locale}.{$field}";
                $component = match ($field) {
                    'content' => RichEditor::make($path)->toolbarButtons([
                        'blockquote', 'bold', 'bulletList', 'h2', 'h3', 'italic', 'link',
                        'orderedList', 'redo', 'strike', 'underline', 'undo',
                    ]),
                    'description', 'summary_of_changes' => Textarea::make($path)->rows(3),
                    default => TextInput::make($path)->maxLength(255),
                };
                $fields[] = $component->label(__('legal-documents::legal-documents.translation_fields.'.$field));
            }
            $fields[] = Toggle::make("removed_content_locales.{$locale}")
                ->label(__('legal-documents::legal-documents.remove_translation'))->default(false);
            $tabs[] = Tab::make($locale)->schema($fields);
        }

        return $tabs === [] ? [] : [
            Section::make(__('legal-documents::legal-documents.translations'))
                ->description(__('legal-documents::legal-documents.translation_help', ['locale' => $translator->sourceLocale()]))
                ->schema([Tabs::make('content_translations_tabs')->tabs($tabs)])
                ->columnSpanFull(),
        ];
    }
}
