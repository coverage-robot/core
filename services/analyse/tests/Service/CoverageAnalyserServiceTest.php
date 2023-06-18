<?php

namespace App\Tests\Service;

use App\Client\BigQueryClient;
use App\Client\Github\GithubAppClient;
use App\Client\Github\GithubAppInstallationClient;
use App\Model\CachedPublishableCoverageData;
use App\Query\LineCoverageQuery;
use App\Query\TotalCoverageQuery;
use App\Query\TotalDiffCoverageQuery;
use App\Service\CoverageAnalyserService;
use App\Service\Diff\Github\GithubDiffParserService;
use App\Service\DiffParserService;
use App\Service\QueryService;
use Packages\Models\Enum\Provider;
use Packages\Models\Model\Upload;
use PHPUnit\Framework\TestCase;
use SebastianBergmann\Diff\Parser;

class CoverageAnalyserServiceTest extends TestCase
{
    public function testAnalyse(): void
    {
        $coverageAnalyserService = new CoverageAnalyserService(
            new QueryService(
                new BigQueryClient(),
                [new LineCoverageQuery(),
                new TotalCoverageQuery()]
            ),
            new DiffParserService([
                Provider::GITHUB->value => new GithubDiffParserService(new GithubAppInstallationClient(new GithubAppClient()), new Parser())
            ])
        );

        $mockUpload = $this->createMock(Upload::class);
        $mockUpload
            ->method('getOwner')
            ->willReturn('ryanmab');
        $mockUpload
            ->method('getRepository')
            ->willReturn('coverage');
        $mockUpload->method("getPullRequest")->willReturn(134);
        $mockUpload->method("getProvider")->willReturn(Provider::GITHUB);
        $mockUpload
            ->method('getCommit')
            ->willReturn('99ee3b8cd21c82e531867a30043164b3ecc5099e');

        $data = $coverageAnalyserService->analyse($mockUpload);

        $this->assertInstanceOf(
            CachedPublishableCoverageData::class,
            $data
        );

        $data->getDiffCoveragePercentage();
    }
}
