<?php

namespace App\Support;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

/**
 * Idempotencia de escrita via cabecalho `Idempotency-Key`.
 *
 * A primeira chamada executa a acao e grava a resposta; replays com a mesma
 * chave (para o mesmo usuario/rota) devolvem a resposta gravada sem reexecutar.
 */
class Idempotencia
{
    public const TTL_SEGUNDOS = 86400;

    public const CABECALHO = 'Idempotency-Key';

    /**
     * @param  Closure(): SymfonyResponse  $acao
     */
    public static function executar(Request $request, Closure $acao): SymfonyResponse
    {
        $chave = self::chave($request);

        if ($chave === null) {
            return $acao();
        }

        $gravada = Cache::get($chave);

        if (is_array($gravada)
            && isset($gravada['status'], $gravada['conteudo'])
            && is_int($gravada['status'])
            && is_string($gravada['conteudo'])
        ) {
            $contentType = isset($gravada['content_type']) && is_string($gravada['content_type'])
                ? $gravada['content_type']
                : null;

            return self::reconstruir([
                'status' => $gravada['status'],
                'conteudo' => $gravada['conteudo'],
                'content_type' => $contentType,
            ]);
        }

        $resposta = $acao();

        Cache::put($chave, [
            'status' => $resposta->getStatusCode(),
            'conteudo' => $resposta->getContent(),
            'content_type' => $resposta->headers->get('Content-Type'),
        ], self::TTL_SEGUNDOS);

        return $resposta;
    }

    public static function chave(Request $request): ?string
    {
        $valor = $request->header(self::CABECALHO);

        if (! is_string($valor) || $valor === '') {
            return null;
        }

        $identidade = $request->user()?->getAuthIdentifier() ?? $request->ip();

        return 'idempotencia:'.sha1($identidade.'|'.$request->method().'|'.$request->path().'|'.$valor);
    }

    /**
     * @param  array{status: int, conteudo: string|false|null, content_type: ?string}  $gravada
     */
    private static function reconstruir(array $gravada): SymfonyResponse
    {
        $headers = [];

        if ($gravada['content_type'] !== null) {
            $headers['Content-Type'] = $gravada['content_type'];
        }

        if (str_contains($gravada['content_type'] ?? '', 'json')) {
            return new JsonResponse(
                json_decode((string) $gravada['conteudo'], true),
                $gravada['status'],
                $headers,
            );
        }

        return new Response((string) $gravada['conteudo'], $gravada['status'], $headers);
    }
}
