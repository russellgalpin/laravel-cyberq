<?php

namespace App\Filament\Resources\Cooks\Tables;

use App\Filament\Resources\Cooks\Actions\EndCookAction;
use App\Models\Cook;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class CooksTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('started_at', 'desc')
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->description(fn (Cook $cook) => $cook->ended_automatically ? 'Ended automatically' : null),
                TextColumn::make('started_at')
                    ->dateTime('D j M Y, H:i')
                    ->sortable(),
                TextColumn::make('ended_at')
                    ->dateTime('D j M Y, H:i')
                    ->placeholder('In progress')
                    ->sortable(),
                TextColumn::make('duration')
                    ->state(fn (Cook $cook) => $cook->duration()?->forHumans(['short' => true, 'parts' => 2])),
                IconColumn::make('in_progress')
                    ->label('In progress')
                    ->boolean(),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
                EndCookAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
