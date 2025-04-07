<?php

namespace App\Exception;

use Throwable;
use Hyperf\Server\Exception\ServerException;

class InsufficientFundsException extends ServerException
{
    public function __construct(
        public readonly string $errorType,
        string $message = 'Saldo insuficiente para realizar a transferência',
        int $code = 400,
        Throwable $previous = null
    ) {
        parent::__construct($message, $code, $previous);
    }
}