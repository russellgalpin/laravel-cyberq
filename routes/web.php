<?php

use App\Http\Controllers\AppleAppSiteAssociationController;
use Illuminate\Support\Facades\Route;

Route::get('/.well-known/apple-app-site-association', [AppleAppSiteAssociationController::class, 'show']);
