<?php

namespace Packages\Message\PublishableMessage;

use Packages\Contracts\PublishableMessage\PublishableMessage;
use Symfony\Component\Serializer\Annotation\DiscriminatorMap;

#[DiscriminatorMap(
    'type',
    [
        PublishableMessage::PullRequest->value => PublishablePullRequestMessage::class,
        PublishableMessage::CheckAnnotation->value => PublishableCheckAnnotationMessage::class,
        PublishableMessage::CheckRun->value => PublishableCheckRunMessage::class,
        PublishableMessage::Collection->value => PublishableMessageCollection::class,
    ]
)]
interface PublishableMessageInterface extends \Packages\Contracts\PublishableMessage\PublishableMessageInterface
{
}
