<?php

namespace Packages\Models\Model\Event;

use DateTimeImmutable;
use Packages\Models\Enum\EventType;
use Packages\Models\Enum\Provider;
use Stringable;
use Symfony\Component\Serializer\Annotation\DiscriminatorMap;

#[DiscriminatorMap(
    'type',
    [
        EventType::UPLOAD->value => Upload::class,
        EventType::JOB_STATE_CHANGE->value => JobStateChange::class,
        EventType::COVERAGE_FINALISED->value => CoverageFinalised::class
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

    public function getEventTime(): DateTimeImmutable;
}
