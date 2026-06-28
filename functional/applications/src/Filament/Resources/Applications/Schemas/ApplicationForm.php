<?php

namespace Functional\Applications\Filament\Resources\Applications\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Functional\Applications\Models\Pack;

/** Defines the form schema for Application create/edit. */
class ApplicationForm
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

            $translationInputs[] = Textarea::make("description.{$locale}")
                ->label("Description ({$locale})");
        }

        return $schema->components([
            Select::make('pack_id')
                ->relationship('pack')
                ->getOptionLabelFromRecordUsing(fn (Pack $record): string => $record->name)
                ->searchable()
                ->preload()
                ->required(),

            TextInput::make('slug')
                ->required()
                ->maxLength(255),

            Section::make('Translations')
                ->schema($translationInputs),
        ]);
    }
}
