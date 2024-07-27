<?php

namespace App\Filament\Resources\CookResource\Pages;

use App\Filament\Resources\CookResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditCook extends EditRecord
{
    protected static string $resource = CookResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
