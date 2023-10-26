<?php

namespace App\Enum;

enum QueryResult: string
{
    /**
     * A collection of commits from the data warehouse.
     */
    case COMMIT_COLLECTION = 'COMMIT_COLLECTION';

    /**
     * A singular commit from the data warehouse.
     */
    case COMMIT = 'COMMIT';

    /**
     * The total coverage for a commit.
     */
    case COVERAGE = 'COVERAGE';

    /**
     * A collection of coverage data, split by file.
     */
    case FILE_COVERAGE_COLLECTION = 'FILE_COVERAGE_COLLECTION';

    /**
     * A singular file's coverage data.
     */
    case FILE_COVERAGE = 'FILE_COVERAGE';

    /**
     * A collection of coverage data, split by line.
     */
    case LINE_COVERAGE_COLLECTION = 'LINE_COVERAGE_COLLECTION';

    /**
     * A singular line's coverage data.
     */
    case LINE_COVERAGE = 'LINE_COVERAGE';

    /**
     * A collection of coverage data, split by tag.
     */
    case TAG_COVERAGE_COLLECTION = 'TAG_COVERAGE_COLLECTION';

    /**
     * A singular tag's coverage data.
     */
    case TAG_COVERAGE = 'TAG_COVERAGE';

    /**
     * Information about the uploads for commits in the data warehouse.
     */
    case TOTAL_UPLOADS = 'TOTAL_UPLOADS';
}
