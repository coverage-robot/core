<?php

namespace App\Service;

use App\Exception\SigningException;
use App\Model\SignedUrl;
use App\Model\SigningParameters;
use Symfony\Component\HttpFoundation\Request;

interface UploadServiceInterface
{
    /**
     * @throws SigningException
     */
    public function getSigningParametersFromRequest(Request $request): SigningParameters;

    public function buildSignedUploadUrl(SigningParameters $signingParameters): SignedUrl;
}
