<?php

use Illuminate\Support\Facades\Route;

/*
 * Portal (SPA) servido pelo Laravel nesta leva. O build do Vite vai para
 * `public/spa` (ver frontend/vite.config.ts). Qualquer rota que não seja da
 * API nem o healthcheck devolve o index do SPA, para o roteamento no cliente.
 */
Route::get('/{qualquer?}', function (?string $qualquer = null) {
    $index = public_path('spa/index.html');

    if (! is_file($index)) {
        abort(404, 'Portal não buildado. Rode `npm run build` em frontend/.');
    }

    return response()->file($index);
})->where('qualquer', '^(?!api|up).*$');
