<?php

namespace Packages\Message\PublishableMessage;

use Packages\Contracts\PublishableMessage\PublishableMessage;
use Symfony\Component\Serializer\Attribute\DiscriminatorMap;

#[DiscriminatorMap(
    'type',
    [
        PublishableMessage::MISSING_COVERAGE_ANNOTATION->value => PublishableMissingCoverageAnnotationMessage::class,
        PublishableMessage::PARTIAL_BRANCH_ANNOTATION->value => PublishablePartialBranchAnnotationMessage::class,
    ]
)]
interface PublishableAnnotationInterface
{
    public function getFileName(): string;

    public function getStartLineNumber(): int;

    public function getEndLineNumber(): int;
}
