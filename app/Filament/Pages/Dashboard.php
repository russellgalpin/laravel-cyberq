<?php

namespace App\Filament\Pages;

use App\Filament\Actions\SetTargetsAction;
use App\Filament\Resources\Cooks\CookResource;
use App\Filament\Widgets\CookTemperatureChart;
use App\Filament\Widgets\CurrentCookStats;
use App\Filament\Widgets\FanOutputChart;
use App\Filament\Widgets\FanOutputGauge;
use App\Models\Cook;
use Filament\Actions\Action;

class Dashboard extends \Filament\Pages\Dashboard
{
    public function getWidgets(): array
    {
        return [
            CurrentCookStats::class,
            CookTemperatureChart::class,
            FanOutputGauge::class,
            FanOutputChart::class,
        ];
    }

    public function getColumns(): int|array
    {
        return ['md' => 3];
    }

    protected function getHeaderActions(): array
    {
        return [
            SetTargetsAction::make(),
            Action::make('startCook')
                ->label('Start a cook')
                ->icon('heroicon-o-play')
                ->visible(fn () => Cook::current() === null)
                ->url(CookResource::getUrl('create')),
        ];
    }
}
