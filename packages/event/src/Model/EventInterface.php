<?php

namespace Packages\Event\Model;

use Packages\Contracts\Event\Event;
use Symfony\Component\Serializer\Annotation\DiscriminatorMap;

#[DiscriminatorMap(
    'type',
    [
        Event::UPLOADS_STARTED->value => UploadsStarted::class,
        Event::UPLOAD->value => Upload::class,
        Event::UPLOADS_FINALISED->value => UploadsFinalised::class,
        Event::JOB_STATE_CHANGE->value => JobStateChange::class,
        Event::CONFIGURATION_FILE_CHANGE->value => ConfigurationFileChange::class,
        Event::COVERAGE_FINALISED->value => CoverageFinalised::class,
        Event::COVERAGE_FAILED->value => CoverageFailed::class,
        Event::INGEST_STARTED->value => IngestStarted::class,
        Event::INGEST_SUCCESS->value => IngestSuccess::class,
        Event::INGEST_FAILURE->value => IngestFailure::class,
    ]
)]
interface EventInterface extends \Packages\Contracts\Event\EventInterface
{
}
