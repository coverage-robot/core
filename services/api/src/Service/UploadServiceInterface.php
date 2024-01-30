<?php

namespace App\Service;

use App\Model\SignedUrl;
use App\Model\SigningParameters;
use Symfony\Component\HttpFoundation\Request;

interface UploadServiceInterface
{
    public function getSigningParametersFromRequest(Request $request): SigningParameters;

    public function buildSignedUploadUrl(SigningParameters $signingParameters): SignedUrl;
}
