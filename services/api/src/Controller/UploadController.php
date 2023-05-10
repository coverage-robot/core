<?php

namespace App\Controller;

use App\Service\UploadService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

class UploadController extends AbstractController
{
    public function __construct(
        private readonly UploadService $uploadService
    ) {
    }

    #[Route('/upload', name: 'upload')]
    public function handleUpload(Request $request): JsonResponse
    {
        $body = $request->toArray();

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
            $body['pullRequest'] ? (string)$body['pullRequest'] : null,
            (string)$body['commit'],
            (string)$body['parent'],
            (string)$body['provider']
        );

        return $this->json($signedUrl);
    }
}
