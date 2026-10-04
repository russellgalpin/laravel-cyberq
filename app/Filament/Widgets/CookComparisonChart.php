<?php

namespace App\Filament\Widgets;

use App\Models\Cook;
use App\Models\Probe;
use App\Support\CookTimeline;
use Filament\Support\RawJs;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Illuminate\Support\Collection;
use Leandrocfe\FilamentApexCharts\Widgets\ApexChartWidget;

class CookComparisonChart extends ApexChartWidget
{
    use InteractsWithPageFilters;

    public const int MAXIMUM_COOKS = 8;

    protected static ?string $chartId = 'cookComparisonChart';

    protected int|string|array $columnSpan = 'full';

    protected ?string $pollingInterval = null;

    protected function getHeading(): string
    {
        return ChartPalette::PROBE_LABELS[$this->probe()].' by hours into the cook';
    }

    protected function getSubheading(): ?string
    {
        return 'Each line starts when its cook started, so cooks of different lengths line up.';
    }

    public function probe(): string
    {
        $probe = $this->pageFilters['probe'] ?? Probe::FOOD1;

        return in_array($probe, Probe::TEMPERATURES, true) ? $probe : Probe::FOOD1;
    }

    /** @return Collection<int, Cook> */
    public function cooks(): Collection
    {
        $ids = array_slice((array) ($this->pageFilters['cooks'] ?? []), 0, self::MAXIMUM_COOKS);

        return Cook::query()->whereKey($ids)->orderBy('started_at')->get();
    }

    protected function getOptions(): array
    {
        $series = $this->cooks()
            ->values()
            ->map(fn (Cook $cook) => [
                'name' => "{$cook->name} ({$cook->started_at->format('j M Y')})",
                'data' => (new CookTimeline($cook, maxPoints: 250))->temperaturesByElapsedHours($this->probe()),
            ]);

        $longestCookHours = (int) ceil($series->flatMap(fn (array $cook) => array_column($cook['data'], 0))->max() ?? 1);
        $hoursPerTick = (int) ceil($longestCookHours / 16);

        return [
            'chart' => [
                'type' => 'line',
                'height' => 420,
                'animations' => ['enabled' => false],
                'zoom' => ['enabled' => true, 'type' => 'x'],
            ],
            'series' => $series->all(),
            'colors' => array_slice(ChartPalette::SERIES, 0, max($series->count(), 1)),
            'stroke' => ['width' => 2, 'curve' => 'smooth'],
            'xaxis' => [
                'type' => 'numeric',
                'title' => ['text' => 'Hours since the cook started'],
                'min' => 0,
                'max' => max(1, (int) ceil($longestCookHours / $hoursPerTick) * $hoursPerTick),
                'tickAmount' => max(1, (int) ceil($longestCookHours / $hoursPerTick)),
            ],
            'yaxis' => ['title' => ['text' => '°F']],
            'legend' => ['show' => true, 'position' => 'top', 'showForSingleSeries' => true],
            'grid' => ['strokeDashArray' => 3],
            'noData' => ['text' => 'Choose some cooks to compare.'],
            'tooltip' => ['shared' => false],
        ];
    }

    protected function extraJsOptions(): ?RawJs
    {
        return RawJs::make(<<<'JS'
        {
            xaxis: {
                labels: { formatter: (value) => `${Math.round(value)}h` },
            },
            yaxis: {
                title: { text: '°F' },
                labels: { formatter: (value) => value === null ? '' : `${Math.round(value)}°` },
            },
            tooltip: {
                x: { formatter: (value) => `${Math.floor(value)}h ${String(Math.round((value % 1) * 60)).padStart(2, '0')}m in` },
                y: { formatter: (value) => value === null || value === undefined ? '-' : `${value.toFixed(1)}°F` },
            },
        }
        JS);
    }
}
