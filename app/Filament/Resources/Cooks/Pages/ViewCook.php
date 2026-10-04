<?php

namespace App\Filament\Resources\Cooks\Pages;

use App\Filament\Pages\CompareCooks;
use App\Filament\Resources\Cooks\Actions\EndCookAction;
use App\Filament\Resources\Cooks\CookResource;
use App\Filament\Widgets\CookTemperatureChart;
use App\Filament\Widgets\FanOutputChart;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewCook extends ViewRecord
{
    protected static string $resource = CookResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EndCookAction::make(),
            Action::make('compare')
                ->label('Compare')
                ->icon('heroicon-o-presentation-chart-line')
                ->color('gray')
                ->url(fn () => CompareCooks::urlFor([$this->getRecord()->getKey()])),
            EditAction::make(),
        ];
    }

    protected function getFooterWidgets(): array
    {
        return [
            CookTemperatureChart::class,
            FanOutputChart::class,
        ];
    }

    public function getFooterWidgetsColumns(): int|array
    {
        return 1;
    }
}
