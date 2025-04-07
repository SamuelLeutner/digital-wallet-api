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

    protected string $queue = 'ws_notifications_queue';

    public function __construct(array $data = [])
    {
        $this->payload = [
            'generated_at' => date('H:i:s d-m-Y'),
            'payload' => $data,
        ];
    }
}