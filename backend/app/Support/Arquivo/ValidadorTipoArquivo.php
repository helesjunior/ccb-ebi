<?php

namespace App\Support\Arquivo;

/**
 * Valida o **tipo real** de um arquivo por assinatura de bytes (magic numbers),
 * nunca pela extensao nem pelo MIME declarado (que sao controlados pelo
 * cliente). Aceita apenas PDF, JPEG e PNG — os formatos de laudo/evidencia.
 */
class ValidadorTipoArquivo
{
    /**
     * @return string|null o MIME real reconhecido, ou null se nao aceito.
     */
    public function mimeReal(string $conteudo): ?string
    {
        if (str_starts_with($conteudo, '%PDF-')) {
            return 'application/pdf';
        }

        // JPEG: FF D8 FF
        if (str_starts_with($conteudo, "\xFF\xD8\xFF")) {
            return 'image/jpeg';
        }

        // PNG: 89 50 4E 47 0D 0A 1A 0A
        if (str_starts_with($conteudo, "\x89PNG\r\n\x1A\n")) {
            return 'image/png';
        }

        return null;
    }
}
