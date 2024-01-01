<?php

namespace Packages\Message\PublishableMessage;

use Packages\Contracts\PublishableMessage\PublishableMessage;
use Symfony\Component\Serializer\Attribute\DiscriminatorMap;
use Symfony\Component\Validator\Constraints as Assert;

#[DiscriminatorMap(
    'type',
    [
        PublishableMessage::MISSING_COVERAGE_ANNOTATION->value => PublishableMissingCoverageAnnotationMessage::class,
        PublishableMessage::PARTIAL_BRANCH_ANNOTATION->value => PublishablePartialBranchAnnotationMessage::class,
    ]
)]
interface PublishableAnnotationInterface
{
    #[Assert\NotBlank]
    public function getFileName(): string;

    #[Assert\GreaterThanOrEqual(1)]
    public function getStartLineNumber(): int;

    #[Assert\GreaterThanOrEqual(1)]
    public function getEndLineNumber(): int;
}
