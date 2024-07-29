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

        $stats = [];

        $pitTemp = $cook->readings()
            ->orderByDesc('created_at')
            ->whereHas('probe', fn($query) => $query->where('identifier', 'COOK_TEMP'))
            ->first();

        if ($pitTemp) {
            $stats[] = Stat::make('Pit Temp', $pitTemp->temperatureInFahrenheit . '°F')
                ->description('Set point: ' . $pitTemp->setPointInFahrenheit . '°F');
        }

        $cook1 = $cook->readings()
            ->orderByDesc('created_at')
            ->whereHas('probe', fn($query) => $query->where('identifier', 'FOOD1_TEMP'))
            ->first();

        if ($cook1) {
            $stats[] = Stat::make('Food 1 Temp', $cook1->temperatureInFahrenheit . '°F')
                ->description('Set point: ' . $cook1->setPointInFahrenheit . '°F');
        }

        $cook2 = $cook->readings()
            ->orderByDesc('created_at')
            ->whereHas('probe', fn($query) => $query->where('identifier', 'FOOD2_TEMP'))
            ->first();

        if ($cook2) {
            $stats[] = Stat::make('Food 2 Temp', $cook2->temperatureInFahrenheit . '°F')
                ->description('Set point: ' . $cook2->setPointInFahrenheit . '°F');
        }

        return $stats;
    }
}
