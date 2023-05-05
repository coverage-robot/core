<?php

namespace App\Service\Publisher;

use App\Model\PublishableCoverageDataInterface;
use App\Model\Upload;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

#[AutoconfigureTag('app.publisher_service')]
interface PublisherServiceInterface
{
    public function supports(Upload $upload, PublishableCoverageDataInterface $coverageData): bool;

    public function publish(Upload $upload, PublishableCoverageDataInterface $coverageData): bool;
}
