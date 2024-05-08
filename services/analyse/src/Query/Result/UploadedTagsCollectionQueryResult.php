<?php

namespace App\Query\Result;

use Symfony\Component\Validator\Constraints as Assert;

final class UploadedTagsCollectionQueryResult implements QueryResultInterface
{
    /**
     * @param UploadedTagsQueryResult[] $uploadedTags
     */
    public function __construct(
        #[Assert\All([
            new Assert\Type(type: UploadedTagsQueryResult::class)
        ])]
        private readonly array $uploadedTags,
    ) {
    }

    public function getUploadedTags(): array
    {
        return $this->uploadedTags;
    }
}
