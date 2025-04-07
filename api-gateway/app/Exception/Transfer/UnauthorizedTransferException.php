<?php

namespace App\Exception\Transfer;

use Throwable;
use Hyperf\Server\Exception\ServerException;

class UnauthorizedTransferException extends ServerException
{
    public function __construct(
        protected $message = 'Transferência não autorizada pelo serviço externo',
        int $code = 400,
        Throwable $previous = null
    ) {
        parent::__construct($message, $code, $previous);
    }
}