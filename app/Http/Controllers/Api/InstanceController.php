<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

/**
 * Lets the app confirm a server address really is a CyberQ instance before signing in.
 */
class InstanceController extends Controller
{
    public const int API_VERSION = 1;

    public function show(): JsonResponse
    {
        return response()->json([
            'data' => [
                'app' => 'cyberq',
                'name' => config('app.name'),
                'api_version' => self::API_VERSION,
            ],
        ]);
    }
}
