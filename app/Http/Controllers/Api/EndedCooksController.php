<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\CookResource;
use App\Models\Cook;
use Illuminate\Validation\ValidationException;

class EndedCooksController extends Controller
{
    public function store(Cook $cook): CookResource
    {
        if (! $cook->in_progress) {
            throw ValidationException::withMessages(['cook' => 'This cook is not in progress.']);
        }

        $cook->end();

        return new CookResource($cook);
    }
}
