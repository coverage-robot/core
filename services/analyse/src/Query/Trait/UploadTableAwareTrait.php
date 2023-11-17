<?php

namespace App\Query\Trait;

use App\Enum\EnvironmentVariable;
use Packages\Contracts\Environment\EnvironmentServiceInterface;

trait UploadTableAwareTrait
{
    public function __construct(
        private readonly EnvironmentServiceInterface $environmentService
    ) {
    }

    public function getTable(): string
    {
        return $this->environmentService->getVariable(EnvironmentVariable::BIGQUERY_UPLOAD_TABLE);
    }
}
