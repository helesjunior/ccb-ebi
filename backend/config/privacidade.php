<?php

use App\Support\EnvArquivo;

return [
    // Chave de criptografia dos dados sensíveis, SEPARADA da APP_KEY (ADR-0006).
    // Nunca há fallback para APP_KEY: se faltar, a operação de cifra falha.
    'chave' => EnvArquivo::obter('SENSITIVE_DATA_KEY'),

    'retencao' => [
        // Rotina de descarte/anonimização DESLIGADA até o DPO aprovar (RNF-07).
        'ativa' => filter_var(EnvArquivo::obter('PRIVACIDADE_RETENCAO_ATIVA') ?? 'false', FILTER_VALIDATE_BOOL),

        // Prazo por tipo de dado, em meses (padrão provisório do ADR-0019).
        'prazos' => [
            'laudo' => 0,          // apagado ao fim da exceção (por registro; 0 = só com prazo_retencao explícito)
            'diagnostico' => 0,
            'saude' => 0,
        ],
    ],
];
