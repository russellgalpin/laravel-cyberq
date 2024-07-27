<?php

namespace App\Filament\Widgets;

use App\Models\Cook;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class StatsOverview extends BaseWidget
{
    protected function getStats(): array
    {
        $cook = Cook::whereNull('ended_at')->first();

        $pitTemp = $cook->readings()
            ->orderByDesc('created_at')
            ->whereHas('probe', fn($query) => $query->where('identifier', 'COOK_TEMP'))
            ->first()
            ?->temperature / 10;

        $cook1 = $cook->readings()
            ->orderByDesc('created_at')
            ->whereHas('probe', fn($query) => $query->where('identifier', 'FOOD1_TEMP'))
            ->first()
            ?->temperature / 10;

        $cook2 = $cook->readings()
            ->orderByDesc('created_at')
            ->whereHas('probe', fn($query) => $query->where('identifier', 'FOOD2_TEMP'))
            ->first()
            ?->temperature / 10;

        return [
            Stat::make('Pit Temp', $pitTemp . '°F'),
            Stat::make('Food 1 Temp', $cook1. '°F'),
            Stat::make('Food 2 Temp', $cook2. '°F'),
        ];
    }
}
