<?php

namespace App\Filament\Resources\PackageDocumentTemplates\Schemas;

use App\Enums\PackageDocumentKind;
use App\Enums\PackageDocumentStatus;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Validation\Rules\Unique;

class PackageDocumentTemplateForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Document')
                ->schema([
                    Grid::make(2)
                        ->schema([
                            Select::make('kind')
                                ->options([
                                    PackageDocumentKind::License->value => PackageDocumentKind::License->label(),
                                    PackageDocumentKind::InstallationGuide->value => PackageDocumentKind::InstallationGuide->label(),
                                ])
                                ->required(),
                            Select::make('status')
                                ->options([
                                    PackageDocumentStatus::Draft->value => PackageDocumentStatus::Draft->label(),
                                    PackageDocumentStatus::Active->value => PackageDocumentStatus::Active->label(),
                                    PackageDocumentStatus::Archived->value => PackageDocumentStatus::Archived->label(),
                                ])
                                ->default(PackageDocumentStatus::Draft->value)
                                ->required(),
                            TextInput::make('title')
                                ->required()
                                ->maxLength(255),
                            TextInput::make('version')
                                ->required()
                                ->maxLength(120)
                                ->unique(
                                    ignoreRecord: true,
                                    modifyRuleUsing: fn (Unique $rule, callable $get): Unique => $rule->where('kind', $get('kind'))
                                ),
                            Toggle::make('is_current')
                                ->label('Current')
                                ->helperText('Use the Activate action to safely make this the only current template for its kind.')
                                ->disabled()
                                ->dehydrated(false),
                        ]),
                    Textarea::make('body')
                        ->required()
                        ->rows(16)
                        ->columnSpanFull()
                        ->helperText('Plain text only. HTML and Markdown are escaped during package PDF generation.'),
                ])
                ->columnSpanFull(),
        ]);
    }
}
