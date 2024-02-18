<?php

namespace Packages\Configuration\Enum;

use Packages\Configuration\Model\LineCommentType;

enum SettingKey: string
{
    /**
     * The setting that controls how line comments are rendered on pull requests.
     *
     * @see LineCommentType
     */
    case LINE_COMMENT_TYPE = 'line_comment.type';

    /**
     * Allows for custom path replacement rules when injecting coverage, to ensure
     * the file names map to version control paths.
     */
    case PATH_REPLACEMENTS = 'path_replacements';

    /**
     * Allows for tag-wide behaviour changes, such as turning off carrying forward.
     */
    case DEFAULT_TAG_BEHAVIOUR = 'tag_behaviour.default';

    /**
     * Allows for individual tag behaviour changes, such as turning carrying forward off.
     *
     * This should take precedence over the default behaviour.
     */
    case INDIVIDUAL_TAG_BEHAVIOURS = 'tag_behaviour.tags';
}
