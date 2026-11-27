<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Cook;
use App\Support\CookEvents;
use Illuminate\Http\JsonResponse;

class CookEventsController extends Controller
{
    public function index(Cook $cook): JsonResponse
    {
        return response()->json(['data' => (new CookEvents($cook))->all()]);
    }
}
