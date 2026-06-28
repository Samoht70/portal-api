<?php

namespace Functional\Applications\Filament\Resources\Packs\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

/** Defines the form schema for Pack create/edit. */
class PackForm
{
    public static function configure(Schema $schema): Schema
    {
        $locales = config('translatable.locales', [config('app.locale')]);
        $default = config('app.locale');

        $translationInputs = [];
        foreach ($locales as $locale) {
            $translationInputs[] = TextInput::make("name.{$locale}")
                ->label("Name ({$locale})")
                ->required($locale === $default)
                ->maxLength(255);
        }

        return $schema->components([
            TextInput::make('slug')
                ->required()
                ->maxLength(255),

            Section::make('Translations')
                ->schema($translationInputs),
        ]);
    }
}
