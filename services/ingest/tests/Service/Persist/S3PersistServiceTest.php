<?php

namespace App\Tests\Service\Persist;

use App\Service\Persist\S3PersistService;
use App\Tests\Mock\Factory\MockEnvironmentServiceFactory;
use AsyncAws\SimpleS3\SimpleS3Client;
use DateTimeImmutable;
use Packages\Models\Enum\CoverageFormat;
use Packages\Models\Enum\Environment;
use Packages\Models\Enum\LineType;
use Packages\Models\Enum\Provider;
use Packages\Models\Model\Coverage;
use Packages\Models\Model\Event\Upload;
use Packages\Models\Model\File;
use Packages\Models\Model\Line\AbstractLine;
use Packages\Models\Model\Line\Branch;
use Packages\Models\Model\Line\Method;
use Packages\Models\Model\Line\Statement;
use Packages\Models\Model\Tag;
use PHPUnit\Framework\Attributes\DataProvider;
use Psr\Log\NullLogger;
use Ramsey\Uuid\Uuid;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Serializer\SerializerInterface;

class S3PersistServiceTest extends KernelTestCase
{
    #[DataProvider('coverageDataProvider')]
    public function testPersist(
        Upload $upload,
        Coverage $coverage,
        array $expectedWrittenLines
    ): void {
        $metadata = [
            'sourceFormat' => $coverage->getSourceFormat()->value,
            'commit' => $upload->getCommit(),
            'parent' => json_encode($upload->getParent()),
            'ingestTime' => $upload->getIngestTime()->format(DateTimeImmutable::ATOM),
            'uploadId' => $upload->getUploadId(),
            'provider' => $upload->getProvider()->value,
            'owner' => $upload->getOwner(),
            'repository' => $upload->getRepository(),
            'ref' => $upload->getRef(),
            'pullRequest' => $upload->getPullRequest(),
            'tag' => $upload->getTag()->getName(),
            'eventTime' => $upload->getEventTime()->format(DateTimeImmutable::ATOM),
            'projectRoot' => $upload->getProjectRoot()
        ];

        $mockS3Client = $this->createMock(SimpleS3Client::class);

        $mockS3Client->expects($this->once())
            ->method('upload')
            ->with(
                'coverage-output-dev',
                $upload->getUploadId() . '.txt',
                self::callback(
                    function ($body) use ($expectedWrittenLines) {
                        rewind($body);
                        $this->assertEquals(
                            implode(
                                '',
                                $expectedWrittenLines
                            ),
                            stream_get_contents($body)
                        );

                        return true;
                    }
                ),
                [
                    'ContentLength' => mb_strlen(
                        implode(
                            '',
                            $expectedWrittenLines
                        )
                    ),
                    'ContentType' => 'text/plain',
                    'Metadata' => $metadata
                ]
            );

        $S3PersistService = new S3PersistService(
            $mockS3Client,
            MockEnvironmentServiceFactory::getMock($this, Environment::DEVELOPMENT),
            $this->getContainer()->get(SerializerInterface::class),
            new NullLogger()
        );
        $S3PersistService->persist(
            $upload,
            $coverage
        );
    }

    public static function coverageDataProvider(): iterable
    {
        $upload = new Upload(
            Uuid::uuid4()->toString(),
            Provider::GITHUB,
            '',
            '',
            '',
            [],
            'mock-branch-reference',
            'project/root',
            1,
            new Tag('mock-tag', '')
        );

        for ($numberOfLines = 1; $numberOfLines <= 10; $numberOfLines++) {
            $coverage = new Coverage(CoverageFormat::LCOV, 'mock/project/root');
            $expectedWrittenLines = [];

            for ($numberOfFiles = 1; $numberOfFiles <= 3; $numberOfFiles++) {
                // Clone the coverage object so that we can add an additional file per yield, while
                // modifying the original object
                $coverage = clone $coverage;

                $expectedWrittenLines[0] = sprintf(
                    ">> SourceFormat: %s, GeneratedAt: %s, ProjectRoot: %s, TotalFiles: %s\n",
                    $coverage->getSourceFormat()->value,
                    $coverage->getGeneratedAt()?->format(DateTimeImmutable::ATOM) ?? 'unknown',
                    $coverage->getRoot(),
                    $numberOfFiles
                );

                $file = new File('mock-file-' . $numberOfFiles);

                $expectedWrittenLines[] = sprintf(
                    "\n> FileName: %s, TotalLines: %s\n",
                    $file->getFileName(),
                    $numberOfLines
                );

                for ($i = 1; $i <= $numberOfLines; $i++) {
                    $line = match ($i % 3) {
                        0 => new Branch($i, $i % 2, [0 => 0, 1 => 2, 3 => 0]),
                        1 => new Statement($i, $i % 2),
                        2 => new Method($i, $i % 2, 'mock-method-' . $i)
                    };
                    $file->setLine($line);

                    $expectedWrittenLines[] = self::getExpectedWrittenLine($line);
                }

                $coverage->addFile($file);

                yield sprintf('%s files with %s line(s) each', $numberOfFiles, $numberOfLines) => [
                    $upload,
                    $coverage,
                    $expectedWrittenLines
                ];
            }
        }
    }

    private static function getExpectedWrittenLine(AbstractLine $line): string
    {
        return match ($line->getType()) {
            LineType::STATEMENT => sprintf(
                "Type: \"%s\", LineNumber: \"%s\", LineHits: \"%s\"\n",
                $line->getType()->value,
                $line->getLineNumber(),
                $line->getLineHits()
            ),
            LineType::BRANCH => sprintf(
                "BranchHits: %s, Type: \"%s\", LineNumber: \"%s\", LineHits: \"%s\"\n",
                json_encode($line->getBranchHits()),
                $line->getType()->value,
                $line->getLineNumber(),
                $line->getLineHits()
            ),
            LineType::METHOD => sprintf(
                "Name: \"%s\", Type: \"%s\", LineNumber: \"%s\", LineHits: \"%s\"\n",
                $line->getName(),
                $line->getType()->value,
                $line->getLineNumber(),
                $line->getLineHits()
            ),
        };
    }
}
