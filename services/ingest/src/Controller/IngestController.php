<?php

namespace App\Controller;

use App\Service\CoverageFileRetrievalService;
use App\Service\CoverageFileParserService;
use Bref\Event\S3\S3Event;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;

class IngestController extends AbstractController
{
    public function __construct(
        private readonly CoverageFileRetrievalService $coverageFileRetrievalService,
        private readonly CoverageFileParserService $coverageFileParserService
    ) {
    }

    public function handle(S3Event $event): JsonResponse
    {
        foreach ($event->getRecords() as $coverageFile) {
            $source = $this->coverageFileRetrievalService->ingestFromS3(
                $coverageFile->getBucket(),
                $coverageFile->getObject()
            );

            $this->coverageFileParserService->parse($source);
        }
    }
}
