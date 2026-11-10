<?php

namespace App\Domain\DadosSensiveis\Services;

use App\Domain\DadosSensiveis\Contracts\ArmazenarDadoSensivel;
use App\Domain\DadosSensiveis\Models\DadoSensivelParticipante;
use App\Domain\DadosSensiveis\Support\CofreChaveSensivel;
use App\Support\ProblemException;

class ArmazenadorDadoSensivel implements ArmazenarDadoSensivel
{
    public function armazenar(
        ?string $participanteId,
        string $tipo,
        string $conteudo,
        ?string $referenciaSegura,
        string $finalidade,
        string $baseLegal,
        string $controleAcesso,
        ?\DateTimeInterface $prazoRetencao,
    ): DadoSensivelParticipante {
        $temConteudo = $conteudo !== '';
        $temReferencia = is_string($referenciaSegura) && $referenciaSegura !== '';

        // Exatamente um dos dois (nem ambos, nem nenhum).
        if ($temConteudo === $temReferencia) {
            throw new ProblemException(
                'dado_sensivel_invalido',
                'Conteúdo inválido',
                422,
                'Informe conteúdo ou referência segura, nunca os dois nem nenhum.',
            );
        }

        return DadoSensivelParticipante::query()->create([
            'participante_id' => $participanteId,
            'tipo' => $tipo,
            'conteudo_cifrado' => $temConteudo ? CofreChaveSensivel::encrypter()->encryptString($conteudo) : null,
            'referencia_segura' => $temReferencia ? $referenciaSegura : null,
            'finalidade' => $finalidade,
            'base_legal' => $baseLegal,
            'controle_acesso' => $controleAcesso,
            'prazo_retencao' => $prazoRetencao,
        ]);
    }
}
