<?php

namespace App\Query\Trait;

use App\Enum\EnvironmentVariable;
use App\Service\EnvironmentService;

trait UploadTableAwareTrait
{
    public function __construct(
        private EnvironmentService $environmentService
    ) {
    }

    public function getTable(): string
    {
        return $this->environmentService->getVariable(EnvironmentVariable::BIGQUERY_UPLOAD_TABLE);
    }
}
