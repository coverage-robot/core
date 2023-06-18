<?php

namespace App\Service\Publisher;

use App\Model\PublishableCoverageDataInterface;
use Packages\Models\Model\Upload;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

#[AutoconfigureTag('app.publisher_service')]
interface PublisherServiceInterface
{
    /**
     * Check if the publisher supports being executed with the given upload and coverage data.
     */
    public function supports(Upload $upload, PublishableCoverageDataInterface $coverageData): bool;

    /**
     * Execute the implementation for publishing the upload and coverage data.
     */
    public function publish(Upload $upload, PublishableCoverageDataInterface $coverageData): bool;

    /**
     * Specify the priority of the publisher.
     *
     * The smaller the number, the lower the priority (e.g. 1 is executed before 0).
     */
    public static function getPriority(): int;
}
