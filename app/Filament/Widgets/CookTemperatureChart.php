<?php

namespace App\Filament\Widgets;

use App\Filament\Widgets\Concerns\ShowsCook;
use App\Models\Probe;
use App\Support\CookTimeline;
use Filament\Support\RawJs;
use Leandrocfe\FilamentApexCharts\Widgets\ApexChartWidget;

class CookTemperatureChart extends ApexChartWidget
{
    use ShowsCook;

    protected static ?string $chartId = 'cookTemperatureChart';

    protected int|string|array $columnSpan = 'full';

    protected function getHeading(): string
    {
        return 'Temperatures';
    }

    protected function getSubheading(): ?string
    {
        return $this->cook() ?
            'Dashed lines are the targets. Drag across the chart to zoom in.' :
            'No cook in progress.';
    }

    protected function getOptions(): array
    {
        $cook = $this->cook();

        if (! $cook) {
            return [];
        }

        $timeline = new CookTimeline($cook);

        $probes = collect(Probe::TEMPERATURES)->filter(fn (string $identifier) => $timeline->hasReadingsFor($identifier));

        $temperatureSeries = $probes->map(fn (string $identifier) => [
            'name' => ChartPalette::PROBE_LABELS[$identifier],
            'data' => $timeline->temperatures($identifier),
            'color' => ChartPalette::PROBES[$identifier],
            'dash' => 0,
        ]);

        $targetSeries = $probes
            ->map(fn (string $identifier) => [
                'name' => ChartPalette::PROBE_LABELS[$identifier].' target',
                'data' => $timeline->setPoints($identifier),
                'color' => ChartPalette::PROBES[$identifier],
                'dash' => 6,
            ])
            ->filter(fn (array $series) => $series['data'] !== []);

        $series = $temperatureSeries->concat($targetSeries)->values();

        return [
            'chart' => [
                'type' => 'line',
                'height' => 380,
                'animations' => ['enabled' => false],
                'zoom' => ['enabled' => true, 'type' => 'x'],
                'toolbar' => ['show' => true, 'tools' => ['download' => true, 'zoomin' => true, 'zoomout' => true, 'reset' => true, 'pan' => false, 'zoom' => true]],
            ],
            'series' => $series->map(fn (array $series) => ['name' => $series['name'], 'data' => $series['data']])->all(),
            'colors' => $series->pluck('color')->all(),
            'stroke' => [
                'width' => 2,
                'curve' => 'smooth',
                'dashArray' => $series->pluck('dash')->all(),
            ],
            'xaxis' => [
                'type' => 'datetime',
                'labels' => ['datetimeUTC' => false],
            ],
            'yaxis' => [
                'title' => ['text' => '°F'],
            ],
            'legend' => ['show' => true, 'position' => 'top', 'showForSingleSeries' => false],
            'grid' => ['strokeDashArray' => 3],
            'markers' => ['size' => 0, 'hover' => ['size' => 5]],
            'tooltip' => [
                'shared' => true,
                'x' => ['format' => 'ddd HH:mm'],
            ],
        ];
    }

    protected function extraJsOptions(): ?RawJs
    {
        return RawJs::make(<<<'JS'
        {
            yaxis: {
                title: { text: '°F' },
                labels: { formatter: (value) => value === null ? '' : `${Math.round(value)}°` },
            },
            tooltip: {
                y: { formatter: (value) => value === null || value === undefined ? '-' : `${value.toFixed(1)}°F` },
            },
        }
        JS);
    }
}
