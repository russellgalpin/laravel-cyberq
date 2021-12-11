<?php

use App\Models\Reading;
use Chartisan\PHP\Chartisan;
use Illuminate\Support\Facades\Route;

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

Route::get('/test', function() {
    $readings = Reading::all();

    print Chartisan::build()
        ->labels($readings->pluck('created_at')->map(function ($val) {
            return $val->toDateString();
        })->toArray())
        ->dataset('probe1', $readings->pluck('temperature')->toArray());


});

Route::get('/', function () {
    return view('welcome');
});
