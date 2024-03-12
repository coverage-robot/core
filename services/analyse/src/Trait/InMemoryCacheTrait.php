<?php

namespace App\Trait;

use App\Service\CachingCoverageAnalyserService;
use App\Service\Diff\CachingDiffParserService;
use App\Service\History\CachingCommitHistoryService;
use WeakMap;

/**
 * A simple set of helper methods to utilise an in-memory cache for method calls.
 *
 * This uses an object (can be anything) for the 'search parameters' to difference cache values,
 * and can be used in the same class by changing the method name.
 *
 * Notable usages of the in-memory cache:
 * @see CachingCoverageAnalyserService
 * @see CachingDiffParserService
 * @see CachingCommitHistoryService
 */
trait InMemoryCacheTrait
{
    /**
     * @var array<string, WeakMap<object, mixed>>
     */
    private array $cache = [];

    /**
     * Set a value in the cache for a given method and object.
     */
    private function setCacheValue(string $methodName, object $object, mixed $value): void
    {
        if (!isset($this->cache[$methodName])) {
            $this->cache[$methodName] = new WeakMap();
        }

        $this->cache[$methodName]->offsetSet($object, $value);
    }

    /**
     * Check if a value exists in the cache for a given method and object.
     */
    private function hasCacheValue(string $methodName, object $object): bool
    {
        if (!isset($this->cache[$methodName])) {
            return false;
        }

        return $this->cache[$methodName]->offsetExists($object);
    }

    /**
     * Get a value from the cache for a given method and object.
     */
    private function getCacheValue(string $methodName, object $object, mixed $default = null): mixed
    {
        if (!$this->hasCacheValue($methodName, $object)) {
            return $default;
        }

        return $this->cache[$methodName]->offsetGet($object);
    }

    /**
     * Get all values from the cache for a given method.
     */
    private function getAllCacheValues(string $methodName): WeakMap
    {
        return $this->cache[$methodName] ?? new WeakMap();
    }
}
