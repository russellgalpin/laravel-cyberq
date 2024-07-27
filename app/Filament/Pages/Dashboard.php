<?php

namespace App\Filament\Pages;


use App\Filament\Widgets\CurrentCookChart;
use App\Filament\Widgets\FanSpeedChart;
use App\Filament\Widgets\StatsOverview;
use Filament\Facades\Filament;

class Dashboard extends \Filament\Pages\Dashboard
{
    protected static ?string $navigationIcon = 'heroicon-o-document-text';

    protected static string $view = 'filament.pages.dashboard';

    public function getHeaderWidgets(): array
    {
        return [
            StatsOverview::class,
            CurrentCookChart::class,
            FanSpeedChart::class,
        ];
    }

    public function getHeaderWidgetsColumns(): int | array
    {
        return 12;
    }
}
