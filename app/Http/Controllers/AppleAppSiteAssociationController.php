<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;

/**
 * Tells iOS which apps may use this site's passkeys.
 */
class AppleAppSiteAssociationController extends Controller
{
    public function show(): JsonResponse
    {
        return response()->json([
            'webcredentials' => [
                'apps' => array_values(config('services.ios.app_ids')),
            ],
        ]);
    }
}
