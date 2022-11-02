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

Route::get('/chart/{cook}', function(Cook $cook) {
    $readings = $cook->readings;

    print Chartisan::build()
        ->labels($readings->pluck('created_at')->map(function ($val) {
            return $val->toDateString();
        })->toArray())
        ->dataset('probe1', $readings->pluck('temperature')->toArray());


});

Route::get('/cook/{cook}', function (Cook $cook) {
    return view('cook')->with('cook', $cook);
});

Route::get('/', function () {
    $cook = Cook::query()->orderBy('started_at', 'desc')->first(); 
    return view('cook')->with('cook', $cook);
});
