<?php

declare(strict_types=1);

namespace App\Service;

use App\Exception\SigningException;
use App\Model\Project;
use App\Model\SignedUrl;
use App\Model\SigningParameters;
use Symfony\Component\HttpFoundation\Request;

interface UploadServiceInterface
{
    /**
     * @throws SigningException
     */
    public function getSigningParametersFromRequest(Request $request): SigningParameters;

    public function buildSignedUploadUrl(Project $project, SigningParameters $signingParameters): SignedUrl;
}
