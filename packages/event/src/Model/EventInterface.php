<?php

namespace Packages\Event\Model;

use DateTimeImmutable;
use Packages\Event\Enum\Event;
use Packages\Models\Enum\Provider;
use Stringable;
use Symfony\Component\Serializer\Annotation\DiscriminatorMap;

#[DiscriminatorMap(
    'type',
    [
        Event::UPLOADS_STARTED->value => UploadsStarted::class,
        Event::UPLOAD->value => Upload::class,
        Event::UPLOADS_FINALISED->value => UploadsFinalised::class,
        Event::JOB_STATE_CHANGE->value => JobStateChange::class,
        Event::COVERAGE_FINALISED->value => CoverageFinalised::class,
        Event::INGEST_STARTED->value => IngestStarted::class,
        Event::INGEST_SUCCESS->value => IngestSuccess::class,
        Event::INGEST_FAILURE->value => IngestFailure::class,
        Event::ANALYSE_FAILURE->value => AnalyseFailure::class,
    ]
)]
interface EventInterface extends Stringable
{
    public function getProvider(): Provider;

    public function getOwner(): string;

    public function getRepository(): string;

    public function getCommit(): string;

    public function getPullRequest(): int|string|null;

    public function getRef(): string;

    public function getType(): Event;

    public function getEventTime(): DateTimeImmutable;
}
