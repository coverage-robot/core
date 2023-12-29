<?php

namespace App\Service\Publisher;

use Packages\Contracts\PublishableMessage\PublishableMessageInterface;
use Packages\Telemetry\Enum\Unit;
use Packages\Telemetry\Service\MetricService;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\TaggedIterator;

class MessagePublisherService
{
    public function __construct(
        #[TaggedIterator('app.publisher_service', defaultPriorityMethod: 'getPriority')]
        private readonly iterable $publishers,
        private readonly LoggerInterface $publisherServiceLogger,
        private readonly MetricService $metricService
    ) {
    }

    /**
     * Publish the message with _all_ publishers which support it.
     */
    public function publish(PublishableMessageInterface $publishableMessage): bool
    {
        $successful = true;

        foreach ($this->publishers as $publisher) {
            if (!$publisher instanceof PublisherServiceInterface) {
                $this->publisherServiceLogger->critical(
                    'Publisher does not implement the correct interface.',
                    [
                        'persistService' => $publisher::class
                    ]
                );

                continue;
            }

            $this->publisherServiceLogger->info(
                sprintf(
                    'Publishing %s using %s',
                    (string)$publishableMessage,
                    $publisher::class
                )
            );

            if (!$publisher->supports($publishableMessage)) {
                $this->publisherServiceLogger->info(
                    sprintf(
                        'Not publishing using %s, as it does not support %s',
                        $publisher::class,
                        (string)$publishableMessage,
                    )
                );

                continue;
            }

            $publishSucceeded = $publisher->publish($publishableMessage);

            if ($publishSucceeded) {
                $this->metricService->put(
                    metric: 'PublishedResults',
                    value: 1,
                    unit: Unit::COUNT,
                    dimensions: [
                        ['owner'],
                        ['owner', 'type']
                    ],
                    properties: [
                        'owner' => $publishableMessage->getEvent()
                            ?->getOwner(),
                        'type' => $publishableMessage->getType()
                            ->value
                    ]
                );
            }

            $successful = $publishSucceeded && $successful;

            $this->publisherServiceLogger->info(
                sprintf(
                    'Publishing using %s continues to be a %s',
                    $publisher::class,
                    $successful ? 'success' : 'fail'
                )
            );
        }

        return $successful;
    }
}
