<?php

namespace App\Exception\Transfer;

use Throwable;
use Hyperf\Server\Exception\ServerException;

class AlreadyProcessedException extends ServerException
{
    public function __construct(
        protected $message = 'Esta transação já foi processada anteriormente',
        int $code = 400,
        Throwable $previous = null
    ) {
        parent::__construct($message, $code, $previous);
    }
}