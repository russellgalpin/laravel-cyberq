<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreCookRequest;
use App\Http\Requests\Api\UpdateCookRequest;
use App\Http\Resources\CookResource;
use App\Models\Cook;
use App\Models\Probe;
use App\Services\Guru\CyberQUnreachable;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class CooksController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $cooks = Cook::query()
            ->when($request->filled('search'), fn ($query) => $query->where('name', 'like', '%'.$request->string('search').'%'))
            ->latest('started_at')
            ->paginate(min($request->integer('per_page', 25), 100));

        return CookResource::collection($cooks);
    }

    public function store(StoreCookRequest $request): CookResource
    {
        $cook = Cook::create([
            ...$request->safe()->except(['probes', 'pit_target', 'food_target']),
            'started_at' => $request->validated('started_at', now()),
        ]);

        $cook->useProbes($request->validated('probes', Probe::DEFAULT_FOR_NEW_COOKS));

        $resource = new CookResource($cook);

        try {
            $cook->sendTargets($request->validated('pit_target'), $request->validated('food_target'));
        } catch (CyberQUnreachable $exception) {
            $resource->additional(['meta' => [
                'warning' => "The cook has started, but the CyberQ did not take the targets: {$exception->getMessage()}",
            ]]);
        }

        return $resource;
    }

    public function show(Cook $cook): CookResource
    {
        return (new CookResource($cook))->withSummary();
    }

    public function update(UpdateCookRequest $request, Cook $cook): CookResource
    {
        $cook->update($request->safe()->except('probes'));

        if ($request->has('probes')) {
            $cook->useProbes($request->validated('probes'));
        }

        return new CookResource($cook);
    }

    public function destroy(Cook $cook): Response
    {
        $cook->delete();

        return response()->noContent();
    }
}
