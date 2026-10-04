<?php

namespace App\Filament\Resources\Cooks\Pages;

use App\Filament\Resources\Cooks\CookResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListCooks extends ListRecords
{
    protected static string $resource = CookResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
