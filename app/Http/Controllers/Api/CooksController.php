<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreCookRequest;
use App\Http\Requests\Api\UpdateCookRequest;
use App\Http\Resources\CookResource;
use App\Models\Cook;
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
            ...$request->validated(),
            'started_at' => $request->validated('started_at', now()),
        ]);

        return new CookResource($cook);
    }

    public function show(Cook $cook): CookResource
    {
        return (new CookResource($cook))->withSummary();
    }

    public function update(UpdateCookRequest $request, Cook $cook): CookResource
    {
        $cook->update($request->validated());

        return new CookResource($cook);
    }

    public function destroy(Cook $cook): Response
    {
        $cook->delete();

        return response()->noContent();
    }
}
