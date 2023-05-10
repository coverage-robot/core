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
            $body["owner"],
            $body["repository"],
            $body["fileName"],
            $body["pullRequest"] ?? null,
            $body["commit"],
            $body["parent"],
            $body["provider"]
        );

        return $this->json($signedUrl);
    }
}
