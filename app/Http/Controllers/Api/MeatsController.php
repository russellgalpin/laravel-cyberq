<?php

namespace App\Http\Controllers\Api;

use App\Enums\Meat;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

class MeatsController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json([
            'data' => array_map(fn (Meat $meat) => $meat->toArray(), Meat::cases()),
        ]);
    }
}
