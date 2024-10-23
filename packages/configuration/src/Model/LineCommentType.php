<?php

declare(strict_types=1);

namespace Packages\Configuration\Model;

enum LineCommentType: string
{
    case REVIEW_COMMENT = 'review_comment';
    case ANNOTATION = 'annotation';
    case HIDDEN = 'hidden';
}
