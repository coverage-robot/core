<?php

namespace App\Service;

use App\Model\GraphParameters;
use App\Model\SigningParameters;
use Symfony\Component\HttpFoundation\Request;

interface AuthTokenServiceInterface
{
    /**
     * Produces a token with a length of 50 (`TOKEN_LENGTH * 2`)
     */
    public const TOKEN_LENGTH = 25;

    public const MAX_TOKEN_RETRIES = 3;

    /**
     * Attempt to retrieve the upload token from a request.
     *
     * In practice this performs a lookup in the request headers for the
     * 'Authorization' key, and decodes it based on the Basic schema pattern.
     */
    public function getUploadTokenFromRequest(Request $request): ?string;

    public function getGraphTokenFromRequest(Request $request): ?string;

    /**
     * Validate a potential upload using a user-provided upload token.
     */
    public function validateParametersWithUploadToken(SigningParameters $parameters, string $token): bool;

    public function validateParametersWithGraphToken(GraphParameters $parameters, string $token): bool;

    /**
     * Create a new unique upload token.
     */
    public function createNewUploadToken(): string;

    /**
     * Create a new unique graph token.
     */
    public function createNewGraphToken(): string;
}
