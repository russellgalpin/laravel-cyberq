<?php

namespace App\Filament\Widgets;

use App\Models\Cook;
use Filament\Widgets\ChartWidget;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Carbon;

class CurrentCookChart extends ChartWidget
{
    protected static ?string $pollingInterval = '10s';

    protected int | string | array $columnSpan = 8;

    protected Cook $cook;

    public function __construct()
    {
        $this->cook = Cook::whereNull('ended_at')->first();
    }

    public function getHeading(): string|Htmlable|null
    {
        return 'Current cook: ' .  $this->cook->name;
    }

    protected function getData(): array
    {
        $datasets = collect();

        $labels = collect([
            [
                'probe' => 'COOK_TEMP',
                'color' => '#891C26',
                'name' => 'Pit Temp'
            ],
            [
                'probe' => 'FOOD1_TEMP',
                'color' => '#891C26',
                'name' => 'Probe 1'
            ],
            [
                'probe' => 'FOOD2_TEMP',
                'color' => '#891C26',
                'name' => 'Probe 2'
            ]
        ])
            ->each(function ($probe) use ($datasets) {
                $readings = $this->cook->readings()
                    ->whereHas('probe', fn($query) => $query->where('identifier', $probe['probe']))
                    ->get();

                $readings = $readings->when(
                    $readings->count() > 200,
                    fn ($readings) => $readings->nth(2)
                );

                $datasets->push([
                    'label' => $probe['name'],
                    'data' => $readings->pluck('temperature')->map(fn($value) => $value / 10)->toArray(),
                    'backgroundColor' => $probe['color'],
                    'borderColor' => $probe['color'],
                    'fill' => false,
                    'lineTension' => '1'
                ]);
            })->take(1)
            ->map(function ($probe) {
                $readings = $this->cook->readings()
                    ->whereHas('probe', fn($query) => $query->where('identifier', $probe['probe']))
                    ->get();

                $readings = $readings->when(
                    $readings->count() > 200,
                    fn ($readings) => $readings->nth(2)
                );

                return $readings->pluck('created_at')->map(function (Carbon $val) {
                    return $val->toDateTimeString();
                })->toArray();
            })
            ->first();

        return [
            'datasets' => $datasets->toArray(),
            'labels' => $labels
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }
}
