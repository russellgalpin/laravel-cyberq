<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\GuruResource;
use App\Models\Guru;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class GurusController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        return GuruResource::collection(Guru::query()->with('probes')->orderBy('name')->get());
    }
}
