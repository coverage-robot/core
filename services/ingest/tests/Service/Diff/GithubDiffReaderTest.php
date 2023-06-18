<?php

namespace App\Tests\Service\Diff;

use App\Client\Github\GithubAppClient;
use App\Client\Github\GithubAppInstallationClient;
use App\Service\Diff\Github\GithubDiffParserService;
use Packages\Models\Enum\Provider;
use Packages\Models\Model\Upload;
use PHPUnit\Framework\TestCase;
use SebastianBergmann\Diff\Parser;

class GithubDiffReaderTest extends TestCase
{

    public function testGetDiff()
    {
        $githubDiffReader = new GithubDiffParserService(
            new GithubAppInstallationClient(new GithubAppClient()),
            new Parser()
        );
        //99ee3b8cd21c82e531867a30043164b3ecc5099e 134 coverage ryanmab api-servic
        //e update-coverage-action-version -vv
        $githubDiffReader->get(
            new Upload(
                'mock',
                Provider::GITHUB,
                'ryanmab',
                'coverage',
                '99ee3b8cd21c82e531867a30043164b3ecc5099e',
                [],
                'update-coverage-action-version',
                '134',
                'api-service',
            )
        );
    }
}
