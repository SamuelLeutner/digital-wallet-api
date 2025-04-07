<?php

declare(strict_types=1);

namespace App\Amqp\Producer;

use App\Exception\Transfer\BusinessException;
use Carbon\Carbon;
use Hyperf\Amqp\Annotation\Producer;
use Hyperf\Amqp\Message\ProducerMessage;

#[Producer(exchange: 'saga', routingKey: 'saga.compensate')]
class SagaCompensateProducer extends ProducerMessage
{
    public const MAX_COMPENSATION_ATTEMPTS = 3;
    protected array $properties = [
        'delivery_mode' => 2,
        'priority' => 1,
    ];

    public function __construct($data)
    {
        if (($data['compensation_attempt'] ?? 0) >= self::MAX_COMPENSATION_ATTEMPTS) {
            throw new BusinessException('MAX_COMPENSATION_ATTEMPTS', 'Maximum compensation attempts reached', 500);
        }

        $this->payload = array_merge($data, [
            'compensation_attempt' => ($data['compensation_attempt'] ?? 0) + 1,
            'timestamp' => Carbon::now()->toDateTimeString(),
        ]);
    }
}
