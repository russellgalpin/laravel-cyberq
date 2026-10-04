<?php

namespace App\Filament\Resources\Cooks\Pages;

use App\Filament\Resources\Cooks\CookResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditCook extends EditRecord
{
    protected static string $resource = CookResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }
}
