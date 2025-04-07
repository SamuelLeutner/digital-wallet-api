<?php

declare(strict_types=1);

namespace App\Amqp\Consumer;

use Throwable;
use Hyperf\Amqp\Result;
use PhpAmqpLib\Message\AMQPMessage;
use Hyperf\Amqp\Annotation\Consumer;
use Hyperf\Amqp\Message\ConsumerMessage;
use App\Repository\NotificationRepository;

#[Consumer(
    exchange: 'ws_notifications',
    routingKey: 'ws.notify',
    queue: 'ws_notifications_queue',
    name: "NotificationsConsumer",
    nums: 1,
)]
class NotificationsConsumer extends ConsumerMessage
{
    public function __construct(
        private readonly NotificationRepository $notificationRepository,
    ) {
    }

    public function consumeMessage($data, AMQPMessage $message): Result
    {
        try {
            $this->createNotification($data['payload']);

            return Result::ACK;
        } catch (Throwable $e) {
            return Result::ACK;
        }
    }

    private function createNotification(array $payload): void
    {
        $this->notificationRepository->create([
            'user_id' => $payload['user_id'],
            'title' => 'Transferência Recebida',
            'message' => $this->formatNotificationMessage($payload['data']['amount']),
        ]);
    }

    private function formatNotificationMessage(float $amount): string
    {
        return sprintf(
            'Você recebeu uma transferência de R$ %s',
            number_format($amount, 2, ',', '.')
        );
    }
}
