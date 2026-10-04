<?php

namespace App\Filament\Resources\Cooks\Actions;

use App\Models\Cook;
use Filament\Actions\Action;

class EndCookAction
{
    public static function make(): Action
    {
        return Action::make('endCook')
            ->label('End cook')
            ->icon('heroicon-m-stop-circle')
            ->color('danger')
            ->requiresConfirmation()
            ->modalDescription('The CyberQ will stop being recorded for this cook.')
            ->visible(fn (Cook $record) => $record->in_progress)
            ->action(fn (Cook $record) => $record->end())
            ->successNotificationTitle('Cook ended');
    }
}
