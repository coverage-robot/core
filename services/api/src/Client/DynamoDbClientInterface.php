<?php

namespace App\Client;

use Packages\Contracts\Provider\Provider;

interface DynamoDbClientInterface
{
    /**
     * Get the coverage percentage for a particular repositories ref.
     */
    public function getCoveragePercentage(
        Provider $provider,
        string $owner,
        string $repository,
        string $ref
    ): ?float;

    /**
     * Set the coverage percentage for a particular repositories ref.
     */
    public function setCoveragePercentage(
        Provider $provider,
        string $owner,
        string $repository,
        string $ref,
        float $coveragePercentage
    ): void;
}
