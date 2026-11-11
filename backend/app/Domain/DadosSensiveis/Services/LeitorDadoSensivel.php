<?php

namespace App\Domain\DadosSensiveis\Services;

use App\Domain\Auditoria\Contracts\RegistrarEvento;
use App\Domain\DadosSensiveis\Contracts\LerDadoSensivel;
use App\Domain\DadosSensiveis\Models\DadoSensivelParticipante;
use App\Domain\DadosSensiveis\Support\CofreChaveSensivel;
use App\Support\ProblemException;
use RuntimeException;

class LeitorDadoSensivel implements LerDadoSensivel
{
    public function __construct(private readonly RegistrarEvento $auditoria) {}

    public function ler(string $id, string $atorId, string $papelAtuante, ?string $escopo = null): string
    {
        if (trim($atorId) === '') {
            throw new RuntimeException('Leitura de dado sensível exige ator identificado.');
        }

        $dado = DadoSensivelParticipante::query()->find($id);

        if ($dado === null) {
            throw new ProblemException('dado_sensivel_inexistente', 'Não encontrado', 404, 'Dado sensível não encontrado.');
        }

        if (! $this->permite($dado->controle_acesso, $papelAtuante)) {
            throw new ProblemException('dado_sensivel_negado', 'Acesso negado', 403, 'Acesso ao dado sensível negado.');
        }

        $this->auditoria->registrar([
            'acao' => 'dado_sensivel.ler',
            'recurso' => 'dado_sensivel_participante',
            'ator_id' => $atorId,
            'origem' => 'usuario',
            'escopo' => $escopo,
            'metadados_minimizados' => [
                'tipo' => $dado->tipo,
                'finalidade' => $dado->finalidade,
                'dado_id' => $dado->id,
            ],
        ]);

        if ($dado->conteudo_cifrado !== null) {
            return CofreChaveSensivel::encrypter()->decryptString($dado->conteudo_cifrado);
        }

        return (string) $dado->referencia_segura;
    }

    private function permite(string $controleAcesso, string $papel): bool
    {
        // Formato: lista de papéis separados por vírgula; "*" libera todos.
        $permitidos = array_map('trim', explode(',', $controleAcesso));

        return in_array('*', $permitidos, true) || in_array($papel, $permitidos, true);
    }
}
