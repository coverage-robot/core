<?php

namespace App\Client;

use App\Enum\EnvironmentVariable;
use App\Service\EnvironmentService;
use AsyncAws\Core\Exception\Http\HttpException;
use AsyncAws\Sqs\Enum\MessageSystemAttributeNameForSends;
use AsyncAws\Sqs\Input\SendMessageRequest;
use AsyncAws\Sqs\SqsClient;
use Packages\Contracts\Environment\EnvironmentServiceInterface;
use Packages\Message\PublishableMessage\PublishableMessageInterface;
use Packages\Telemetry\Service\TraceContext;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Serializer\SerializerInterface;

class SqsMessageClient
{
    public function __construct(
        private readonly SqsClient $sqsClient,
        #[Autowire(service: EnvironmentService::class)]
        private readonly EnvironmentServiceInterface $environmentService,
        private readonly SerializerInterface $serializer
    ) {
    }

    public function queuePublishableMessage(PublishableMessageInterface $publishableMessage): bool
    {
        $request = [
            'QueueUrl' => $this->environmentService->getVariable(EnvironmentVariable::PUBLISH_QUEUE),
            'MessageBody' => $this->serializer->serialize($publishableMessage, 'json'),
            'MessageGroupId' => $publishableMessage->getMessageGroup(),
        ];

        if ($this->environmentService->getVariable(EnvironmentVariable::TRACE_ID) !== '') {
            /**
             * The trace header will be propagated to the next service in the chain if provided
             * from a previous request.
             *
             * This value is propagated into the environment in a number of methods. But in the
             * SQS context that's handled by a trait in the event processors.
             *
             * @see TraceContext
             */
            $request['MessageSystemAttributes'] = [
                MessageSystemAttributeNameForSends::AWSTRACE_HEADER => [
                    'StringValue' => $this->environmentService->getVariable(EnvironmentVariable::TRACE_ID),
                    'DataType' => 'String',
                ],
            ];
        }

        $response = $this->sqsClient->sendMessage(new SendMessageRequest($request));

        try {
            $response->resolve();

            return $response->info()['status'] === Response::HTTP_OK;
        } catch (HttpException) {
            return false;
        }
    }
}
