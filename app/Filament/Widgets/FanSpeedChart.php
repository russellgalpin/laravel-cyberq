<?php

namespace App\Filament\Widgets;

use App\Models\Cook;
use Leandrocfe\FilamentApexCharts\Widgets\ApexChartWidget;

class FanSpeedChart extends ApexChartWidget
{
    protected int | string | array $columnSpan = 4;

    /**
     * Chart Id
     *
     * @var string
     */
    protected static ?string $chartId = 'fanSpeedChart';

    /**
     * Widget Title
     *
     * @var string|null
     */
    protected static ?string $heading = 'Current Fan Output';

    /**
     * Chart options (series, labels, types, size, animations...)
     * https://apexcharts.com/docs/options
     *
     * @return array
     */
    protected function getOptions(): array
    {
        if ($cook = Cook::query()->whereNull('ended_at')->first()) {

            return [
                'chart' => [
                    'type' => 'radialBar',
                    'height' => 300,
                ],
                'series' => [$cook->readings()->whereHas('probe', fn($query) => $query->where('identifier', 'OUTPUT_PERCENT'))->orderByDesc('created_at')->first()?->temperature],
                'plotOptions' => [
                    'radialBar' => [
                        'hollow' => [
                            'size' => '70%',
                        ],
                        'dataLabels' => [
                            'show' => true,
                            'name' => [
                                'show' => true,
                                'fontFamily' => 'inherit'
                            ],
                            'value' => [
                                'show' => true,
                                'fontFamily' => 'inherit',
                                'fontWeight' => 600,
                                'fontSize' => '20px'
                            ],
                        ],

                    ],
                ],
                'stroke' => [
                    'lineCap' => 'round',
                ],
                'labels' => ['Fan Output'],
                'colors' => ['#f59e0b'],
            ];
        } else {
            return [];
        }
    }
}
