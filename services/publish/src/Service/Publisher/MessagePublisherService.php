<?php

namespace App\Service\Publisher;

use Packages\Models\Model\PublishableMessage\PublishableMessageInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\TaggedIterator;

class MessagePublisherService
{
    public function __construct(
        #[TaggedIterator('app.publisher_service', defaultPriorityMethod: 'getPriority')]
        private readonly iterable $publishers,
        private readonly LoggerInterface $publisherServiceLogger
    ) {
    }

    /**
     * Publish the message with _all_ publishers which support it.
     *
     * @param PublishableMessageInterface $publishableMessage
     * @return bool
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

            $successful = $publisher->publish($publishableMessage) && $successful;

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
