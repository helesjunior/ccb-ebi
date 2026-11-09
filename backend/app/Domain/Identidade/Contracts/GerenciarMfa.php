<?php

namespace App\Domain\Identidade\Contracts;

use App\Support\ProblemException;
use Illuminate\Contracts\Auth\Authenticatable;

/**
 * Gestao de MFA (CA-15): inscricao/confirmacao de TOTP, codigos de recuperacao
 * de uso unico e validacao de codigo. Implementa tambem a politica de
 * exigencia de segundo fator (`ExigirMfa`).
 *
 * Recebe `Authenticatable` (nao o modelo) para que `app/Http` nao precise
 * importar o modelo do modulo (ADR-0002); a implementacao valida que o usuario
 * e da identidade.
 */
interface GerenciarMfa extends ExigirMfa
{
    /**
     * Inicia a inscricao de TOTP: gera o segredo (base32) e a URI otpauth.
     *
     * @return array{segredo: string, otpauth_uri: string}
     */
    public function iniciarInscricao(Authenticatable $usuario): array;

    /**
     * Confirma a inscricao: valida o codigo TOTP e marca `confirmado_em`.
     *
     * @throws ProblemException 422 quando o codigo e invalido.
     */
    public function confirmar(Authenticatable $usuario, string $codigo): void;

    /**
     * Gera 10 codigos de recuperacao novos (uso unico), devolvidos em claro
     * apenas neste momento.
     *
     * @return array<int, string>
     */
    public function gerarCodigosRecuperacao(Authenticatable $usuario): array;

    /**
     * Valida um codigo TOTP ou um codigo de recuperacao nao usado (marcando-o
     * como usado quando aceito).
     */
    public function validar(Authenticatable $usuario, string $codigo): bool;

    /**
     * Conclui o segundo fator: valida o codigo, revoga o token de desafio e
     * emite um token de sessao plena (`*`). Devolve o token em claro.
     *
     * @throws ProblemException 422 quando o codigo e invalido.
     */
    public function validarEConcluir(Authenticatable $usuario, string $codigo): string;
}
