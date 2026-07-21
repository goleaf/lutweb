<?php

namespace App\Filament\Resources\WizardStyles\Schemas;

use App\Enums\LutTransformVersion;
use App\ValueObjects\LutTransformParameters;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;

class WizardStyleForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Basic Information')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                TextInput::make('name')
                                    ->required()
                                    ->maxLength(255)
                                    ->live(onBlur: true)
                                    ->afterStateUpdated(fn ($state, $set): mixed => $set('slug', Str::slug((string) $state))),
                                TextInput::make('slug')
                                    ->required()
                                    ->maxLength(255)
                                    ->unique(ignoreRecord: true),
                                Select::make('transform_version')
                                    ->options([
                                        LutTransformVersion::V1->value => 'Transform V1',
                                    ])
                                    ->default(LutTransformVersion::V1->value)
                                    ->required(),
                                TextInput::make('sort_order')
                                    ->numeric()
                                    ->minValue(0)
                                    ->default(0)
                                    ->required(),
                                Toggle::make('is_active')
                                    ->label('Active'),
                                Toggle::make('is_featured')
                                    ->label('Featured'),
                            ]),
                        Textarea::make('description')
                            ->rows(3)
                            ->maxLength(1_000)
                            ->columnSpanFull(),
                    ])
                    ->columnSpanFull(),
                self::parameterSection('Base Look', 'base_parameters', defaults: LutTransformParameters::defaults()),
                self::parameterSection('Allowed Minimums', 'minimum_parameters', defaults: LutTransformParameters::minimums()),
                self::parameterSection('Allowed Maximums', 'maximum_parameters', defaults: LutTransformParameters::maximums()),
                self::parameterSection('Variation Strength', 'variation_amounts', variation: true),
            ]);
    }

    /**
     * @param  array<string, int>|null  $defaults
     */
    private static function parameterSection(string $title, string $statePath, ?array $defaults = null, bool $variation = false): Section
    {
        $fields = collect(LutTransformParameters::schema())
            ->map(function (array $definition) use ($statePath, $defaults, $variation): TextInput {
                $key = $definition['key'];
                $default = $variation ? 0 : ($defaults[$key] ?? $definition['default']);
                $minimum = $variation ? 0 : $definition['minimum'];
                $maximum = $variation
                    ? (LutTransformParameters::isHueKey($key) ? 1800 : LutTransformParameters::span($key))
                    : $definition['maximum'];

                return TextInput::make($statePath.'.'.$key)
                    ->label($definition['label'])
                    ->numeric()
                    ->minValue(self::display($minimum, $definition))
                    ->maxValue(self::display($maximum, $definition))
                    ->step(self::display($definition['ui_step'], $definition))
                    ->default($default)
                    ->suffix($definition['unit'])
                    ->required()
                    ->formatStateUsing(fn ($state): string => self::display((int) ($state ?? $default), $definition))
                    ->dehydrateStateUsing(fn ($state): int => self::canonical($state, $definition));
            })
            ->all();

        return Section::make($title)
            ->schema([
                Grid::make(4)->schema($fields),
            ])
            ->columnSpanFull();
    }

    /**
     * @param  array<string, mixed>  $definition
     */
    private static function display(int $value, array $definition): string
    {
        return rtrim(rtrim(number_format($value / (int) $definition['display_scale'], 2, '.', ''), '0'), '.');
    }

    /**
     * @param  array<string, mixed>  $definition
     */
    private static function canonical(mixed $state, array $definition): int
    {
        return (int) round((float) $state * (int) $definition['display_scale']);
    }
}
