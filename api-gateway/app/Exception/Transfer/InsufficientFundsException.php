<?php

namespace App\Exception\Transfer;

use Throwable;
use Hyperf\Server\Exception\ServerException;

class InsufficientFundsException extends ServerException
{
    public function __construct(
        public readonly string $errorType = 'INSUFFICIENT_BALANCE',
        string $message = 'Saldo insuficiente para realizar a transferência',
        int $code = 400,
        Throwable $previous = null
    ) {
        parent::__construct($message, $code, $previous);
    }
}