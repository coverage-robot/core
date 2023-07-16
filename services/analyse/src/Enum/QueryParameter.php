<?php

namespace App\Enum;

enum QueryParameter
{
    /**
     * The added lines from multiple file's diffs.
     *
     * ```php
     * [
     *      "path/file.php" => [1, 2, 3],
     *      "path/file-2.php" => [4, 5, 6],
     * ]
     * ```
     */
    case LINE_SCOPE;

    /**
     * The limit to apply on query results.
     */
    case LIMIT;

    case UPLOAD;

    case COMMIT;
}
