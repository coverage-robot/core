<?php

namespace Packages\Message\PublishableMessage;

use Packages\Contracts\PublishableMessage\PublishableMessage;
use Symfony\Component\Serializer\Attribute\DiscriminatorMap;
use Symfony\Component\Validator\Constraints as Assert;

#[DiscriminatorMap(
    'type',
    [
        PublishableMessage::MISSING_COVERAGE_LINE_COMMENT->value => PublishableMissingCoverageLineCommentMessage::class,
        PublishableMessage::PARTIAL_BRANCH_LINE_COMMENT->value => PublishablePartialBranchLineCommentMessage::class,
    ]
)]
interface PublishableLineCommentInterface extends PublishableMessageInterface
{
    #[Assert\NotBlank]
    public function getFileName(): string;

    #[Assert\GreaterThanOrEqual(1)]
    public function getStartLineNumber(): int;

    #[Assert\GreaterThanOrEqual(1)]
    public function getEndLineNumber(): int;
}
