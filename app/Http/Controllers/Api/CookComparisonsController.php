<?php

namespace App\Http\Controllers\Api;

use App\Filament\Widgets\CookComparisonChart;
use App\Http\Controllers\Controller;
use App\Models\Cook;
use App\Models\Probe;
use App\Support\CookTimeline;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CookComparisonsController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'cooks' => ['required', 'array', 'min:1', 'max:'.CookComparisonChart::MAXIMUM_COOKS],
            'cooks.*' => ['integer', 'exists:cooks,id'],
            'probe' => ['sometimes', Rule::in(Probe::TEMPERATURES)],
        ]);

        $probe = $validated['probe'] ?? Probe::FOOD1;

        $cooks = Cook::query()
            ->whereKey($validated['cooks'])
            ->orderBy('started_at')
            ->get()
            ->map(fn (Cook $cook) => [
                'id' => $cook->id,
                'name' => $cook->name,
                'started_at' => $cook->started_at?->toIso8601String(),
                'points' => array_map(
                    fn (array $point) => ['h' => $point[0], 'v' => $point[1]],
                    (new CookTimeline($cook, maxPoints: 250))->temperaturesByElapsedHours($probe),
                ),
            ]);

        return response()->json([
            'data' => [
                'probe' => $probe,
                'label' => Probe::LABELS[$probe],
                'cooks' => $cooks,
            ],
        ]);
    }
}
