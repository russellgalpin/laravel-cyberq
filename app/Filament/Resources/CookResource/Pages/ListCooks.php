<?php

namespace App\Filament\Resources\CookResource\Pages;

use App\Filament\Resources\CookResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListCooks extends ListRecords
{
    protected static string $resource = CookResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
