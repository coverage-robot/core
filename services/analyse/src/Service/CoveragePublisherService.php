<?php

namespace App\Service;

use App\Model\PublishableCoverageDataInterface;
use App\Service\Publisher\PublisherServiceInterface;
use Packages\Models\Model\Upload;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\TaggedIterator;

class CoveragePublisherService
{
    public function __construct(
        #[TaggedIterator('app.publisher_service', defaultPriorityMethod: 'getPriority')]
        private readonly iterable $publishers,
        private readonly LoggerInterface $publisherServiceLogger
    ) {
    }

    public function publish(Upload $upload, PublishableCoverageDataInterface $coverageData): bool
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
                    (string)$upload,
                    $publisher::class
                )
            );

            if (!$publisher->supports($upload, $coverageData)) {
                $this->publisherServiceLogger->info(
                    sprintf(
                        'Not publishing using %s, as it does not support %s',
                        $publisher::class,
                        (string)$upload,
                    )
                );

                continue;
            }

            $successful = $publisher->publish($upload, $coverageData) && $successful;

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
