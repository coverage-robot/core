<?php

declare(strict_types=1);

namespace App\Enum;

enum QueryResult: string
{
    /**
     * The total coverage for a commit.
     */
    case COVERAGE = 'COVERAGE';

    /**
     * A singular file's coverage data.
     */
    case FILE_COVERAGE = 'FILE_COVERAGE';

    /**
     * A singular line's coverage data.
     */
    case LINE_COVERAGE = 'LINE_COVERAGE';

    /**
     * A singular tag's coverage data.
     */
    case TAG_COVERAGE = 'TAG_COVERAGE';

    /**
     * Information about the uploads for commits in the data warehouse.
     */
    case TOTAL_UPLOADS = 'TOTAL_UPLOADS';

    /**
     * Information about the availability of a tag on various commits in a repository.
     */
    case TAG_AVAILABILITY = 'TAG_AVAILABILITY';

    /**
     * All of the tags which have been uploaded to a project in the past.
     */
    case UPLOADED_TAGS = 'UPLOADED_TAGS';
}
