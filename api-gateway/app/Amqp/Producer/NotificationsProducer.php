<?php

declare(strict_types=1);

namespace App\Amqp\Producer;

use Hyperf\Amqp\Annotation\Producer;
use Hyperf\Amqp\Message\ProducerMessage;

#[Producer(exchange: 'ws_notifications', routingKey: 'ws.notify')]
class NotificationsProducer extends ProducerMessage
{
    protected array $properties = [
        'delivery_mode' => 2,
        'expiration' => 86400000,
        'content_type' => 'application/json',
    ];

    public function __construct($data)
    {
        $this->payload = $data;
    }
}