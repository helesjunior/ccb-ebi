<?php

namespace App\Domain\Organizacao\Services;

use App\Domain\Auditoria\Contracts\RegistrarEvento;
use App\Domain\Organizacao\Contracts\TransferirIgreja;
use App\Domain\Organizacao\Exceptions\SobreposicaoVigenciaException;
use App\Domain\Organizacao\Models\Igreja;
use App\Domain\Organizacao\Models\IgrejaVinculacao;
use App\Domain\Organizacao\Models\Setor;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class ServicoTransferencia implements TransferirIgreja
{
    public function __construct(private readonly RegistrarEvento $auditoria) {}

    public function executar(
        string $igrejaId,
        string $setorDestinoId,
        \DateTimeInterface $data,
        ?string $motivo = null,
    ): IgrejaVinculacao {
        $inicio = \DateTimeImmutable::createFromInterface($data)->setTime(0, 0);

        return DB::transaction(function () use ($igrejaId, $setorDestinoId, $inicio, $motivo): IgrejaVinculacao {
            $igreja = Igreja::query()->find($igrejaId);

            if ($igreja === null) {
                throw new RuntimeException("Igreja {$igrejaId} nao encontrada.");
            }

            $setorDestino = Setor::query()->find($setorDestinoId);

            if ($setorDestino === null) {
                throw new RuntimeException("Setor {$setorDestinoId} nao encontrado.");
            }

            $ativo = IgrejaVinculacao::query()
                ->where('igreja_id', $igrejaId)
                ->whereNull('fim')
                ->lockForUpdate()
                ->first();

            if ($ativo !== null && $ativo->setor_id === $setorDestinoId) {
                throw new SobreposicaoVigenciaException(
                    'A igreja ja esta vinculada a este setor.'
                );
            }

            // Exige que a data seja posterior ao inicio do vinculo ativo:
            // evita janelas negativas ou vazias ([inicio, fim) vazio).
            if ($ativo !== null && $inicio <= $ativo->inicio) {
                throw new SobreposicaoVigenciaException(
                    'A data de transferencia deve ser posterior ao inicio do vinculo vigente.'
                );
            }

            // Nenhuma data pode cair dentro (nem no inicio) de outro vinculo
            // ja encerrado da igreja. O vinculo ativo (que sera fechado nesta
            // operacao) fica de fora.
            $ativoId = $ativo?->getKey();

            $sobrepoe = IgrejaVinculacao::query()
                ->where('igreja_id', $igrejaId)
                ->when($ativoId !== null, fn ($q) => $q->whereKeyNot($ativoId))
                ->where('inicio', '<=', $inicio)
                ->where(function ($q) use ($inicio): void {
                    $q->whereNull('fim')->orWhere('fim', '>', $inicio);
                })
                ->lockForUpdate()
                ->exists();

            if ($sobrepoe) {
                throw new SobreposicaoVigenciaException(
                    'A data de transferencia sobrepoe um vinculo existente.'
                );
            }

            if ($ativo !== null) {
                $ativo->fim = Carbon::instance($inicio);
                $ativo->save();
            }

            $novo = IgrejaVinculacao::query()->create([
                'igreja_id' => $igrejaId,
                'setor_id' => $setorDestinoId,
                'inicio' => $inicio,
                'fim' => null,
            ]);

            $this->auditoria->registrar([
                'acao' => 'transferir',
                'recurso' => 'igreja',
                'escopo' => $setorDestinoId,
                'origem' => 'web',
                'motivo' => $motivo,
                'metadados_minimizados' => [
                    'igreja_id' => $igrejaId,
                    'setor_anterior_id' => $ativo?->setor_id,
                    'setor_novo_id' => $setorDestinoId,
                    'data' => $inicio->format('Y-m-d'),
                ],
            ]);

            return $novo;
        });
    }
}
