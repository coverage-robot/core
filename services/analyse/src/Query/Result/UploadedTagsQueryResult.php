<?php

declare(strict_types=1);

namespace App\Query\Result;

use Override;
use Symfony\Component\Validator\Constraints as Assert;

final readonly class UploadedTagsQueryResult implements QueryResultInterface
{
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Regex(pattern: '/^[a-zA-Z0-9\.\-_,]+$/')]
        private string $tagName,
    ) {
    }

    public function getTagName(): string
    {
        return $this->tagName;
    }

    #[Override]
    public function getTimeToLive(): int|false
    {
        /**
         * This query can be cached for 1 hour, as the previously uploaded tags are unlikely to
         * change frequently, but if they do we want to respond fairly quickly.
         *
         * We _could_ do something smarter here, and cache for a shorter period depending on the results
         * of this query (i.e. last brand new tag was uploaded recently, might indicate more tags are
         * likely to be uploaded soon), but for now we'll keep it simple.
         */
        return 3600;
    }
}
