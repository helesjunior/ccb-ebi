<?php

use App\Support\EnvArquivo;

return [
    // Chave de criptografia dos dados sensíveis, SEPARADA da APP_KEY (ADR-0006).
    // Nunca há fallback para APP_KEY: se faltar, a operação de cifra falha.
    'chave' => EnvArquivo::obter('SENSITIVE_DATA_KEY'),

    'retencao' => [
        // Rotina de descarte/anonimização DESLIGADA até o DPO aprovar (RNF-07).
        'ativa' => filter_var(EnvArquivo::obter('PRIVACIDADE_RETENCAO_ATIVA') ?? 'false', FILTER_VALIDATE_BOOL),

        // Mapa RESERVADO de prazo por tipo de dado, em meses (provisório do ADR-0019).
        // Ainda NÃO é consultado pelo motor de retenção (ExpurgarRetencao): o prazo
        // vigente de cada registro é a coluna `prazo_retencao`. Mantido para o dia
        // em que houver consumidor real; enquanto isso, não dirige nada.
        'prazos' => [
            'laudo' => 0,          // apagado ao fim da exceção (por registro; 0 = só com prazo_retencao explícito)
            'diagnostico' => 0,
            'saude' => 0,
        ],
    ],
];
