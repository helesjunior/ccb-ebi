<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Organizacao\Contracts\GerirOrganizacao;
use App\Http\Resources\CentralResource;
use Symfony\Component\HttpFoundation\Response;

class CentralController
{
    public function __construct(private readonly GerirOrganizacao $gerir) {}

    public function show(): Response
    {
        $central = $this->gerir->central();

        if ($central === null) {
            return response()->json(null);
        }

        return (new CentralResource($central))->response();
    }
}
