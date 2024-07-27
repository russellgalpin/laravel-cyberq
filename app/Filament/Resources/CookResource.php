<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CookResource\Pages;
use App\Models\Cook;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class CookResource extends Resource
{
    protected static ?string $model = Cook::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make()
                    ->columns([
                        'sm' => 1,
                        'xl' => 2,
                        '2xl' => 2,
                    ])
                    ->schema([
                        TextInput::make('name'),
                        DateTimePicker::make('started_at')->default(now()),
                        Textarea::make('description')->columnSpan(2),
                    ])
                ]
            );
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->orderBy('started_at', 'desc');
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name'),
                Tables\Columns\TextColumn::make('started_at'),
                Tables\Columns\TextColumn::make('ended_at'),
                Tables\Columns\IconColumn::make('in_progress')->boolean()
            ])
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\Action::make('End Cook')
                    ->icon('heroicon-m-stop-circle')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->action(fn (Cook $cook) => $cook->update(['ended_at' => now()])),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCooks::route('/'),
            'create' => Pages\CreateCook::route('/create'),
            'edit' => Pages\EditCook::route('/{record}/edit'),
        ];
    }
}
