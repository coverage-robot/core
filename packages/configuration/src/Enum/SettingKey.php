<?php

namespace Packages\Configuration\Enum;

enum SettingKey: string
{
    /**
     * The setting that controls whether or not line annotations posted as part of
     * the coverage report.
     */
    case LINE_ANNOTATION = 'line_annotations';

    /**
     * Allows for custom path replacement rules when injecting coverage, to ensure
     * the file names map to version control paths.
     */
    case PATH_REPLACEMENTS = 'path_replacements';
}
