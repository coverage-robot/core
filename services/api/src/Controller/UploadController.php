<?php

namespace App\Controller;

use App\Exception\SigningException;
use App\Service\UploadService;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @psalm-import-type SigningParameters from UploadService
 */
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
        try {
            /** @var SigningParameters $parameters */
            $parameters = $this->uploadService->getSigningParametersFromRequest($request);

            $signedUrl = $this->uploadService->buildSignedUploadUrl(
                (string)$parameters['owner'],
                (string)$parameters['repository'],
                (string)$parameters['fileName'],
                isset($parameters['pullRequest']) ? (string)$parameters['pullRequest'] : null,
                (string)$parameters['commit'],
                (string)$parameters['parent'],
                (string)$parameters['tag'],
                (string)$parameters['provider']
            );

            $this->uploadLogger->info(
                'Successfully generated signed url for upload request.',
                [
                    'parameters' => $parameters,
                    'signedUrl' => $signedUrl
                ]
            );

            return $this->json($signedUrl);
        } catch (SigningException $e) {
            return $this->json(
                [
                    'error' => $e->getMessage()
                ],
                Response::HTTP_BAD_REQUEST
            );
        }
    }
}
