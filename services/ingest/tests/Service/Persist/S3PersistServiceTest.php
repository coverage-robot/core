<?php

declare(strict_types=1);

namespace App\Tests\Service\Persist;

use App\Model\Coverage;
use App\Model\File;
use App\Model\Line\AbstractLine;
use App\Model\Line\Branch;
use App\Model\Line\Method;
use App\Model\Line\Statement;
use App\Service\Persist\S3PersistService;
use AsyncAws\SimpleS3\SimpleS3Client;
use DateTimeImmutable;
use Packages\Configuration\Mock\MockEnvironmentServiceFactory;
use Packages\Contracts\Environment\Environment;
use Packages\Contracts\Event\Event;
use Packages\Contracts\Format\CoverageFormat;
use Packages\Contracts\Line\LineType;
use Packages\Contracts\Provider\Provider;
use Packages\Contracts\Tag\Tag;
use Packages\Event\Model\Upload;
use PHPUnit\Framework\Attributes\DataProvider;
use Psr\Log\NullLogger;
use Ramsey\Uuid\Uuid;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Serializer\SerializerInterface;
use Packages\Contracts\Environment\Service;

final class S3PersistServiceTest extends KernelTestCase
{
    /**
     * @param non-empty-list<string> $expectedWrittenLines
     */
    #[DataProvider('coverageDataProvider')]
    public function testPersist(
        Upload $upload,
        Coverage $coverage,
        array $expectedWrittenLines
    ): void {
        $metadata = [
            'sourceFormat' => $coverage->getSourceFormat()->value,
            'commit' => $upload->getCommit(),
            'parent' => json_encode($upload->getParent(), JSON_THROW_ON_ERROR),
            'uploadId' => $upload->getUploadId(),
            'provider' => $upload->getProvider()->value,
            'projectId' => '0192c0b2-a63e-7c29-8636-beb65b9097ee',
            'owner' => $upload->getOwner(),
            'repository' => $upload->getRepository(),
            'ref' => $upload->getRef(),
            'pullRequest' => $upload->getPullRequest(),
            'baseRef' => $upload->getBaseRef(),
            'baseCommit' => $upload->getBaseCommit(),
            'tag' => $upload->getTag()->getName(),
            'eventTime' => $upload->getEventTime()->format(DateTimeImmutable::ATOM),
            'projectRoot' => $upload->getProjectRoot(),
            'type' => Event::UPLOAD->value
        ];

        $mockS3Client = $this->createMock(SimpleS3Client::class);

        $mockS3Client->expects($this->once())
            ->method('upload')
            ->with(
                'coverage-output-dev',
                $upload->getUploadId() . '.txt',
                self::callback(
                    function ($body) use ($expectedWrittenLines): bool {
                        rewind($body);
                        $this->assertSame(
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
            MockEnvironmentServiceFactory::createMock(
                Environment::DEVELOPMENT,
                Service::INGEST
            ),
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
            uploadId: Uuid::uuid4()->toString(),
            provider: Provider::GITHUB,
            projectId: '0192c0b2-a63e-7c29-8636-beb65b9097ee',
            owner: '',
            repository: '',
            commit: '',
            parent: [],
            ref: 'mock-branch-reference',
            projectRoot: 'project/root',
            tag: new Tag('mock-tag', '', [3]),
            pullRequest: 1,
            baseCommit: 'commit-on-main',
            baseRef: 'main'
        );

        for ($numberOfLines = 1; $numberOfLines <= 10; ++$numberOfLines) {
            $coverage = new Coverage(
                sourceFormat: CoverageFormat::LCOV,
                root: 'mock/project/root'
            );
            $expectedWrittenLines = [];

            for ($numberOfFiles = 1; $numberOfFiles <= 3; ++$numberOfFiles) {
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

                for ($i = 1; $i <= $numberOfLines; ++$i) {
                    $line = match ($i % 3) {
                        0 => new Branch(
                            lineNumber: $i,
                            lineHits: $i % 2,
                            branchHits: [0 => 0, 1 => 2, 3 => 0]
                        ),
                        1 => new Statement(
                            lineNumber: $i,
                            lineHits: $i % 2
                        ),
                        2 => new Method(
                            lineNumber: $i,
                            lineHits: $i % 2,
                            name: 'mock-method-' . $i
                        )
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
                json_encode($line->getBranchHits(), JSON_THROW_ON_ERROR),
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
