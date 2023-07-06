<?php

namespace App\Controller;

use App\Exception\SigningException;
use App\Service\AuthTokenService;
use App\Service\UploadService;
use Exception\AuthenticationException;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class UploadController extends AbstractController
{
    public function __construct(
        private readonly UploadService $uploadService,
        private readonly AuthTokenService $authTokenService,
        private readonly LoggerInterface $uploadLogger
    ) {
    }

    #[Route('/upload', name: 'upload', methods: ['POST'])]
    public function handleUpload(Request $request): JsonResponse
    {
        try {
            $parameters = $this->uploadService->getSigningParametersFromRequest($request);

            $token = $this->authTokenService->getProjectTokenFromRequest($request);

            if (!$token || !$this->authTokenService->validateParametersWithProjectToken($parameters, $token)) {
                throw AuthenticationException::invalidProjectToken();
            }

            $signedUrl = $this->uploadService->buildSignedUploadUrl($parameters);

            $this->uploadLogger->info(
                'Successfully generated signed url for upload request.',
                [
                    'parameters' => $parameters,
                    'signedUrl' => $signedUrl
                ]
            );

            return $this->json($signedUrl);
        } catch (AuthenticationException $e) {
            return $this->json(
                [
                    'error' => $e->getMessage()
                ],
                Response::HTTP_UNAUTHORIZED
            );
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
