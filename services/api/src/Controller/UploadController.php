<?php

namespace App\Controller;

use App\Service\UploadService;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

class UploadController extends AbstractController
{
    public function __construct(
        private readonly UploadService $uploadService,
        private readonly LoggerInterface $uploadLogger
    ) {
    }

    #[Route('/upload', name: 'upload', methods: ['POST'])]
    public function handleUpload(Request $request): JsonResponse
    {
        $body = $request->toArray();

        if (!array_key_exists('data', $body)) {
            $this->uploadLogger->info(
                'No data key found in request body.',
                [
                    'parameters' => $body
                ]
            );
        }

        $body = $body['data'];

        $this->uploadLogger->info(
            'Beginning to generate signed url for upload request.',
            [
                'parameters' => $body
            ]
        );

        if (!$this->uploadService->validatePayload($body)) {
            return $this->json(
                [
                    'error' => 'Invalid payload',
                ],
                400
            );
        }

        $signedUrl = $this->uploadService->buildSignedUploadUrl(
            (string)$body['owner'],
            (string)$body['repository'],
            (string)$body['fileName'],
            isset($body['pullRequest']) ? (string)$body['pullRequest'] : null,
            (string)$body['commit'],
            (string)$body['parent'],
            (string)$body['provider']
        );

        return $this->json($signedUrl);
    }
}
