<?php

namespace App\Query\Trait;

use App\Enum\EnvironmentVariable;
use App\Service\EnvironmentService;
use Packages\Contracts\Environment\EnvironmentServiceInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

trait UploadTableAwareTrait
{
    public function __construct(
        #[Autowire(service: EnvironmentService::class)]
        private readonly EnvironmentServiceInterface $environmentService
    ) {
    }

    public function getTable(): string
    {
        return $this->environmentService->getVariable(EnvironmentVariable::BIGQUERY_UPLOAD_TABLE);
    }
}
