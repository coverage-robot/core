<?php

namespace Packages\Message\PublishableMessage;

use Packages\Contracts\PublishableMessage\PublishableMessage;
use Symfony\Component\Serializer\Annotation\DiscriminatorMap;

#[DiscriminatorMap(
    'type',
    [
        PublishableMessage::PULL_REQUEST->value => PublishablePullRequestMessage::class,
        PublishableMessage::MISSING_COVERAGE_ANNOTATION->value => PublishableMissingCoverageAnnotationMessage::class,
        PublishableMessage::PARTIAL_BRANCH_ANNOTATION->value => PublishablePartialBranchAnnotationMessage::class,
        PublishableMessage::CHECK_RUN->value => PublishableCheckRunMessage::class,
        PublishableMessage::COLLECTION->value => PublishableMessageCollection::class,
    ]
)]
interface PublishableMessageInterface extends \Packages\Contracts\PublishableMessage\PublishableMessageInterface
{
}
