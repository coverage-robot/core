<?php

namespace App\Client;

use App\Enum\EnvironmentVariable;
use App\Model\Webhook\WebhookInterface;
use App\Service\EnvironmentService;
use AsyncAws\Core\Exception\Http\HttpException;
use AsyncAws\Sqs\Input\SendMessageRequest;
use AsyncAws\Sqs\SqsClient;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Serializer\SerializerInterface;

class SqsMessageClient
{
    public function __construct(
        private readonly SqsClient $sqsClient,
        private readonly EnvironmentService $environmentService,
        private readonly SerializerInterface $serializer
    ) {
    }

    public function queueIncomingWebhook(WebhookInterface $webhook): bool
    {
        $response = $this->sqsClient->sendMessage(
            new SendMessageRequest([
                'QueueUrl' => $this->environmentService->getVariable(EnvironmentVariable::WEBHOOK_QUEUE),
                'MessageBody' => $this->serializer->serialize($webhook, 'json'),
                'MessageGroupId' => $webhook->getMessageGroup()
            ])
        );

        try {
            $response->resolve();

            return $response->info()['status'] === Response::HTTP_OK;
        } catch (HttpException) {
            return false;
        }
    }
}
