<?php

namespace App\Support;

use RuntimeException;

/**
 * Recurso referenciado por uma operacao de autorizacao nao existe. Quem
 * consulta hierarquia usa isto para negar em vez de estourar 500; o 404 real
 * continua sendo decidido por quem carrega o recurso.
 */
class RecursoNaoEncontradoException extends RuntimeException {}
