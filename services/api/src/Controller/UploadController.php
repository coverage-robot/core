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

        /** @var array{ data: array<array-key, mixed> } $body */
        $parameters = $body['data'];

        $this->uploadLogger->info(
            'Beginning to generate signed url for upload request.',
            [
                'parameters' => $parameters
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

        /** @var array{
         *     owner: mixed,
         *     repository: mixed,
         *     fileName: mixed,
         *     pullRequest?: mixed,
         *     commit: mixed,
         *     parent: mixed,
         *     provider: mixed
         * } $parameters
         */
        $signedUrl = $this->uploadService->buildSignedUploadUrl(
            (string)$parameters['owner'],
            (string)$parameters['repository'],
            (string)$parameters['fileName'],
            isset($parameters['pullRequest']) ? (string)$parameters['pullRequest'] : null,
            (string)$parameters['commit'],
            (string)$parameters['parent'],
            (string)$parameters['provider']
        );

        return $this->json($signedUrl);
    }
}
