<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Resources\UsuarioResource;
use Illuminate\Http\Request;

class MeController
{
    public function me(Request $request): UsuarioResource
    {
        return UsuarioResource::make($request->user());
    }
}
