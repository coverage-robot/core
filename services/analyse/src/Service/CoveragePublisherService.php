<?php

namespace App\Service;

use App\Model\PublishableCoverageDataInterface;
use App\Model\Upload;
use App\Service\Publisher\PublisherServiceInterface;
use Symfony\Component\DependencyInjection\Attribute\TaggedIterator;

class CoveragePublisherService
{
    public function __construct(
        #[TaggedIterator('app.publisher_service', defaultPriorityMethod: 'getPriority')]
        private readonly iterable $publishers
    ) {
    }

    public function publish(Upload $upload, PublishableCoverageDataInterface $coverageData): bool
    {
        $successful = true;

        /** @var PublisherServiceInterface $publisher */
        foreach ($this->publishers as $publisher) {
            if (!$publisher->supports($upload, $coverageData)) {
                continue;
            }

            $successful = $publisher->publish($upload, $coverageData) && $successful;
        }

        return $successful;
    }
}
