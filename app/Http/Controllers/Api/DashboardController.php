<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\CookSnapshotResource;
use App\Models\Cook;
use App\Support\CookSnapshot;
use Illuminate\Http\JsonResponse;

class DashboardController extends Controller
{
    public function show(): JsonResponse|CookSnapshotResource
    {
        $cook = Cook::current();

        if (! $cook) {
            return response()->json(['data' => null]);
        }

        return new CookSnapshotResource(new CookSnapshot($cook));
    }
}
