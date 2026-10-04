<?php

namespace App\Filament\Widgets;

use App\Filament\Widgets\Concerns\ShowsCook;
use App\Support\CookTimeline;
use Filament\Support\RawJs;
use Leandrocfe\FilamentApexCharts\Widgets\ApexChartWidget;

class FanOutputChart extends ApexChartWidget
{
    use ShowsCook;

    protected static ?string $chartId = 'fanOutputChart';

    public function getColumnSpan(): int|string|array
    {
        return $this->record ? 'full' : 2;
    }

    protected function getHeading(): string
    {
        return 'Fan output';
    }

    protected function getSubheading(): ?string
    {
        return 'How hard the blower worked to hold the pit temperature.';
    }

    protected function getOptions(): array
    {
        $cook = $this->cook();

        if (! $cook) {
            return [];
        }

        return [
            'chart' => [
                'type' => 'area',
                'height' => 220,
                'animations' => ['enabled' => false],
                'zoom' => ['enabled' => true, 'type' => 'x'],
            ],
            'series' => [
                ['name' => 'Fan output', 'data' => (new CookTimeline($cook))->fanOutput()],
            ],
            'colors' => [ChartPalette::SERIES[0]],
            'stroke' => ['width' => 2, 'curve' => 'stepline'],
            'fill' => ['type' => 'solid', 'opacity' => 0.2],
            'dataLabels' => ['enabled' => false],
            'xaxis' => [
                'type' => 'datetime',
                'labels' => ['datetimeUTC' => false],
            ],
            'yaxis' => ['min' => 0, 'max' => 100, 'tickAmount' => 4],
            'grid' => ['strokeDashArray' => 3],
            'tooltip' => ['x' => ['format' => 'ddd HH:mm']],
        ];
    }

    protected function extraJsOptions(): ?RawJs
    {
        return RawJs::make(<<<'JS'
        {
            yaxis: {
                min: 0,
                max: 100,
                tickAmount: 4,
                labels: { formatter: (value) => `${Math.round(value)}%` },
            },
            tooltip: {
                y: { formatter: (value) => `${Math.round(value)}%` },
            },
        }
        JS);
    }
}
