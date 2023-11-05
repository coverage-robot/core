<?php

namespace App\Model;

use App\Enum\OrchestratedEvent;
use App\Enum\OrchestratedEventState;
use Packages\Models\Enum\Provider;
use Stringable;
use Symfony\Component\Serializer\Annotation\DiscriminatorMap;

#[DiscriminatorMap(
    'type',
    [
        OrchestratedEvent::INGESTION->value => Ingestion::class,
        OrchestratedEvent::JOB->value => Job::class,
    ]
)]
interface OrchestratedEventInterface extends Stringable
{
    public function getProvider(): Provider;

    public function getOwner(): string;

    public function getRepository(): string;

    public function getState(): OrchestratedEventState;
}
