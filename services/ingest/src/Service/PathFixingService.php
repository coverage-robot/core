<?php

namespace App\Service;

class PathFixingService
{
    /**
     * Remove the root of a path, if present.
     *
     * Particularly useful for making the paths inside a coverage file relative to
     * the Git project root during parsing.
     */
    public function removePathRoot(string $path, string $root): string
    {
        if (str_starts_with($path, $root)) {
            $path = substr($path, strlen($root));
        }

        return trim($path, '/');
    }
}
