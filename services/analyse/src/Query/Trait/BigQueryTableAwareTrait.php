<?php

declare(strict_types=1);

namespace App\Query\Trait;

use App\Enum\EnvironmentVariable;
use Packages\Contracts\Environment\EnvironmentServiceInterface;

trait BigQueryTableAwareTrait
{
    public function __construct(
        private readonly EnvironmentServiceInterface $environmentService
    ) {
    }

    public function getTable(?string $tableName = null): string
    {
        return sprintf(
            '%s.%s.%s',
            $this->environmentService->getVariable(EnvironmentVariable::BIGQUERY_PROJECT),
            $this->environmentService->getVariable(EnvironmentVariable::BIGQUERY_ENVIRONMENT_DATASET),
            $tableName ?? $this->environmentService->getVariable(EnvironmentVariable::BIGQUERY_LINE_COVERAGE_TABLE)
        );
    }
}
