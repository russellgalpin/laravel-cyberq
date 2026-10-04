<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Cook;
use App\Models\Probe;
use App\Support\CookTimeline;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CookTimelinesController extends Controller
{
    public function show(Request $request, Cook $cook): JsonResponse
    {
        $timeline = new CookTimeline($cook, maxPoints: min(max($request->integer('max_points', 300), 20), 1000));

        $probes = collect(Probe::TEMPERATURES)
            ->filter(fn (string $identifier) => $timeline->hasReadingsFor($identifier))
            ->map(fn (string $identifier) => [
                'identifier' => $identifier,
                'label' => Probe::LABELS[$identifier],
                'temperatures' => self::points($timeline->temperatures($identifier)),
                'targets' => self::points($timeline->setPoints($identifier)),
            ])
            ->values();

        return response()->json([
            'data' => [
                'probes' => $probes,
                'fan_output' => self::points($timeline->fanOutput()),
            ],
        ]);
    }

    /**
     * @param  list<array{0: int, 1: float}>  $series
     * @return list<array{t: int, v: float}>
     */
    private static function points(array $series): array
    {
        return array_map(fn (array $point) => ['t' => $point[0], 'v' => $point[1]], $series);
    }
}
