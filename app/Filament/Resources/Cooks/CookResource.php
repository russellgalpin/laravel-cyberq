<?php

namespace App\Filament\Resources\Cooks;

use App\Filament\Resources\Cooks\Pages\CreateCook;
use App\Filament\Resources\Cooks\Pages\EditCook;
use App\Filament\Resources\Cooks\Pages\ListCooks;
use App\Filament\Resources\Cooks\Pages\ViewCook;
use App\Filament\Resources\Cooks\Schemas\CookForm;
use App\Filament\Resources\Cooks\Schemas\CookInfolist;
use App\Filament\Resources\Cooks\Tables\CooksTable;
use App\Models\Cook;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class CookResource extends Resource
{
    protected static ?string $model = Cook::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedFire;

    protected static ?string $recordTitleAttribute = 'name';

    protected static ?int $navigationSort = 1;

    public static function form(Schema $schema): Schema
    {
        return CookForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return CookInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return CooksTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCooks::route('/'),
            'create' => CreateCook::route('/create'),
            'view' => ViewCook::route('/{record}'),
            'edit' => EditCook::route('/{record}/edit'),
        ];
    }
}
