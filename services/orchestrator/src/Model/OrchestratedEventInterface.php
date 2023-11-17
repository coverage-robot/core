<?php

namespace App\Model;

use App\Enum\OrchestratedEvent;
use App\Enum\OrchestratedEventState;
use DateTimeImmutable;
use Packages\Contracts\Provider\Provider;
use Stringable;
use Symfony\Component\Serializer\Annotation\DiscriminatorMap;

#[DiscriminatorMap(
    'type',
    [
        OrchestratedEvent::INGESTION->value => Ingestion::class,
        OrchestratedEvent::JOB->value => Job::class,
        OrchestratedEvent::FINALISED->value => Finalised::class,
    ]
)]
interface OrchestratedEventInterface extends Stringable
{
    public function getProvider(): Provider;

    public function getOwner(): string;

    public function getRepository(): string;

    public function getCommit(): string;

    public function getState(): OrchestratedEventState;

    public function getEventTime(): DateTimeImmutable;

    /**
     * Get a unique identifier for the repository this particular event belongs to
     *
     * This is for query patterns which involve querying for state changes for _specific events_
     * under a particular repository.
     */
    public function getUniqueRepositoryIdentifier(): string;

    /**
     * Get a unique identifier for this particular event.
     *
     * This is for query patterns which involve querying state changes over the
     * event store for _exactly one_ event.
     */
    public function getUniqueIdentifier(): string;
}
