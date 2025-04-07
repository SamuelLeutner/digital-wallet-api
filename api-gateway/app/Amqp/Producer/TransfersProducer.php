<?php

declare(strict_types=1);

namespace App\Amqp\Producer;

use Hyperf\Amqp\Annotation\Producer;
use Hyperf\Amqp\Message\ProducerMessage;

#[Producer(exchange: 'transfers', routingKey: 'transfers.create')]
class TransfersProducer extends ProducerMessage
{
    protected array $properties = [
        'delivery_mode' => 2,
    ];

    public function __construct($data)
    {
        $this->payload = $data;
    }
}
