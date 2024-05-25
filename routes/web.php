<?php

use App\Models\Reading;
use Chartisan\PHP\Chartisan;
use Illuminate\Support\Facades\Route;
use App\Models\Cook;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/
Route::get('/cook/{cook}', function (Cook $cook) {

    $pit = $cook->readings()
        ->whereHas('probe', fn($query) => $query->where('identifier', 'COOK_TEMP'))->get();
    $probe1 = $cook->readings()
        ->whereHas('probe', fn($query) => $query->where('identifier', 'FOOD1_TEMP'))->get();
    $probe2 = $cook->readings()
        ->whereHas('probe', fn($query) => $query->where('identifier', 'FOOD2_TEMP'))->get();

    $chart = (new \App\Charts\CookChart)
        ->labels($pit->pluck('created_at')->map(function ($val) {
            return $val->toDateTimeString();
        })->toArray());

    $chart->dataset('pit', 'line', $pit->pluck('temperature')->map(fn($value) => $value / 100)->toArray())->options([
        'backgroundColor' => '#891C26',
        'color' => '#891C26',
        'fill' => false,
        'lineTension' => '1'
    ]);
    $chart->dataset('probe1', 'line', $probe1->pluck('temperature')->map(fn($value) => $value / 100)->toArray())->options([
        'backgroundColor' => '#024FA6',
        'color' => '#024FA6',
        'fill' => false,
        'lineTension' => '1'
    ]);
    $chart->dataset('probe2', 'line', $probe2->pluck('temperature')->map(fn($value) => $value / 100)->toArray())->options([
        'backgroundColor' => '#36DB53',
        'color' => '#36DB53',
        'fill' => false,
        'lineTension' => '1'
    ]);

    return view('cook')->with('cook', $cook)->with('chart', $chart);
});

Route::get('/', function () {
    $cook = Cook::query()->orderBy('started_at', 'desc')->first();
    return view('cook')->with('cook', $cook);
});
