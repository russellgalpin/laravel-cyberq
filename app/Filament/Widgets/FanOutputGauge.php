<?php

namespace App\Filament\Widgets;

use App\Filament\Widgets\Concerns\ShowsCook;
use App\Models\Cook;
use App\Models\Probe;
use Leandrocfe\FilamentApexCharts\Widgets\ApexChartWidget;

class FanOutputGauge extends ApexChartWidget
{
    use ShowsCook;

    protected static ?string $chartId = 'fanOutputGauge';

    protected static ?string $heading = 'Fan output now';

    protected int|string|array $columnSpan = 1;

    public static function canView(): bool
    {
        return parent::canView() && Cook::current() !== null;
    }

    protected function getOptions(): array
    {
        $fanOutput = $this->cook()?->latestReadingFor(Probe::FAN_OUTPUT)?->temperature;

        return [
            'chart' => [
                'type' => 'radialBar',
                'height' => 260,
            ],
            'series' => [$fanOutput ?? 0],
            'plotOptions' => [
                'radialBar' => [
                    'hollow' => ['size' => '65%'],
                    'dataLabels' => [
                        'name' => ['show' => true, 'fontFamily' => 'inherit'],
                        'value' => ['show' => true, 'fontFamily' => 'inherit', 'fontWeight' => 600, 'fontSize' => '22px'],
                    ],
                ],
            ],
            'stroke' => ['lineCap' => 'round'],
            'labels' => ['Fan'],
            'colors' => [ChartPalette::SERIES[0]],
        ];
    }
}
