<?php

namespace App\Filament\Resources\Cooks\Schemas;

use App\Models\Guru;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class CookForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make()
                    ->columns(['sm' => 1, 'xl' => 2])
                    ->columnSpanFull()
                    ->schema([
                        TextInput::make('name')
                            ->required()
                            ->maxLength(255),
                        Select::make('guru_id')
                            ->label('CyberQ')
                            ->relationship('guru', titleAttribute: 'name')
                            ->default(fn () => Guru::query()->value('id'))
                            ->required(),
                        DateTimePicker::make('started_at')
                            ->default(now())
                            ->seconds(false)
                            ->required(),
                        DateTimePicker::make('ended_at')
                            ->seconds(false)
                            ->after('started_at')
                            ->visibleOn('edit'),
                        Textarea::make('description')
                            ->rows(3)
                            ->maxLength(255)
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}
