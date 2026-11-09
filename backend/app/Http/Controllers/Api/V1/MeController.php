<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Resources\AtorResource;
use Illuminate\Http\Request;

class MeController
{
    public function me(Request $request): AtorResource
    {
        return AtorResource::make($request->user());
    }
}
