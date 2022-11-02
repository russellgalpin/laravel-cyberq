<?php

namespace App\Charts;

use App\Models\Reading;
use ConsoleTVs\Charts\BaseChart;
use Illuminate\Http\Request;
use Chartisan\PHP\Chartisan;

class CookChart extends BaseChart
{
    /**
     * Determines the chart name to be used on the
     * route. If null, the name will be a snake_case
     * version of the class name.
     */
    public ?string $name = 'cook-chart';

    /**
     * Determines the name suffix of the chart route.
     * This will also be used to get the chart URL
     * from the blade directrive. If null, the chart
     * name will be used.
     */
    public ?string $routeName = 'cook-chart';

    /**
     * Determines the prefix that will be used by the chart
     * endpoint.
     */
    public ?string $prefix = 'charts';

    /**
     * Determines the middlewares that will be applied
     * to the chart endpoint.
     */
    public ?array $middlewares = [];

    /**
     * Handles the HTTP request for the given chart.
     * It must always return an instance of Chartisan
     * and never a string or an array.
     */
    public function handler(Request $request): Chartisan
    {
        $readings = Reading::query()->where('cook_id', $request->get('cook_id'))->get();

        $chart = Chartisan::build()
            ->labels(
                $readings->filter(function ($reading) {
                    return $reading->probe_id == 1;
                })->map(function ($val) {
                    return $val->created_at->format('H:i:s');
                })->values()->toArray()
            );

        $probes = [];

        $readings->each(function($reading) use (&$probes) {
            $probes[$reading->probe->name][] = $reading->temperature / 10;
        });

        foreach ($probes as $key => $readings) {
            $chart->dataset($key, $readings);
        }

        return $chart;
    }
}
