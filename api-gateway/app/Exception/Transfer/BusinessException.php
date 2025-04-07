<?php

namespace App\Exception\Transfer;

use Throwable;
use Hyperf\Server\Exception\ServerException;

class BusinessException extends ServerException
{
    public function __construct(
        public readonly string $errorType,
        string $message = '',
        int $code = 400,
        private readonly ?array $details = null,
        Throwable $previous = null
    ) {
        parent::__construct($message, $code, $previous);
    }

    public function getDetails(): ?array
    {
        return $this->details;
    }
}