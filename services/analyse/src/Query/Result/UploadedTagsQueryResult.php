<?php

namespace App\Query\Result;

use Symfony\Component\Validator\Constraints as Assert;

final class UploadedTagsQueryResult implements QueryResultInterface
{
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Regex(pattern: '/^[a-zA-Z0-9\.\-_]+$/')]
        private readonly string $tagName,
    ) {
    }

    public function getTagName(): string
    {
        return $this->tagName;
    }
}
