<?php

namespace App\Controller;

use App\Exception\AuthenticationException;
use App\Exception\SigningException;
use App\Service\AuthTokenService;
use App\Service\UploadService;
use Packages\Event\Handler\TraceContextAwareTrait;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class UploadController extends AbstractController
{
    use TraceContextAwareTrait;

    public function __construct(
        private readonly UploadService $uploadService,
        private readonly AuthTokenService $authTokenService,
        private readonly LoggerInterface $uploadLogger
    ) {
        $this->setTraceHeaderFromEnvironment();
    }

    #[Route('/upload', name: 'upload', methods: ['POST'])]
    public function handleUpload(Request $request): JsonResponse
    {
        try {
            $parameters = $this->uploadService->getSigningParametersFromRequest($request);

            $token = $this->authTokenService->getUploadTokenFromRequest($request);

            if (!$token || !$this->authTokenService->validateParametersWithUploadToken($parameters, $token)) {
                throw AuthenticationException::invalidUploadToken();
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
