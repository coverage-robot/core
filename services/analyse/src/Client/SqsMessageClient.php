<?php

namespace App\Client;

use App\Enum\EnvironmentVariable;
use App\Service\EnvironmentService;
use AsyncAws\Sqs\Input\SendMessageRequest;
use AsyncAws\Sqs\SqsClient;
use JsonException;
use Packages\Models\Model\PublishableMessage\PublishableMessageInterface;
use Symfony\Component\HttpFoundation\Response;

class SqsMessageClient
{
    public function __construct(
        private readonly SqsClient $sqsClient,
        private readonly EnvironmentService $environmentService
    ) {
    }

    /**
     * @throws JsonException
     */
    public function queuePublishableMessage(PublishableMessageInterface $publishableMessage): bool
    {
        $response = $this->sqsClient->sendMessage(
            new SendMessageRequest([
                'QueueUrl' => $this->environmentService->getVariable(EnvironmentVariable::PUBLISH_QUEUE),
                'MessageBody' => json_encode($publishableMessage, JSON_THROW_ON_ERROR),
                'MessageGroupId' => $publishableMessage->getMessageGroup()
            ])
        );

        $response->resolve();

        return $response->info()['status'] === Response::HTTP_OK;
    }
}
