<?php

namespace Packages\Message\PublishableMessage;

use Packages\Contracts\PublishableMessage\PublishableMessage;
use Symfony\Component\Serializer\Annotation\DiscriminatorMap;

#[DiscriminatorMap(
    'type',
    [
        PublishableMessage::LINE_COMMENT_COLLECTION->value => PublishableLineCommentMessageCollection::class,
        PublishableMessage::COLLECTION->value => PublishableMessageCollection::class,
        PublishableMessage::PULL_REQUEST->value => PublishablePullRequestMessage::class,
        PublishableMessage::CHECK_RUN->value => PublishableCheckRunMessage::class,
    ]
)]
interface PublishableMessageInterface extends \Packages\Contracts\PublishableMessage\PublishableMessageInterface
{
}
