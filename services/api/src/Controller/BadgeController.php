<?php

namespace App\Controller;

use App\Service\AuthTokenService;
use App\Service\BadgeService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class BadgeController extends AbstractController
{
    public function __construct(
        private readonly BadgeService $badgeService,
        private readonly AuthTokenService $authTokenService
    ) {
    }

    #[Route('/badge', name: 'badge', methods: ['GET'])]
    public function badge(): Response
    {
        return new Response(
            $this->badgeService->getBadge(),
            Response::HTTP_OK
        );
    }
}
