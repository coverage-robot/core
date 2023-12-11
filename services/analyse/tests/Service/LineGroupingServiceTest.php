<?php

namespace App\Tests\Service;

use App\Query\Result\LineCoverageQueryResult;
use App\Service\LineGroupingService;
use DateTimeImmutable;
use Packages\Event\Model\EventInterface;
use Packages\Message\PublishableMessage\PublishableMissingCoverageAnnotationMessage;
use Packages\Message\PublishableMessage\PublishablePartialBranchAnnotationMessage;
use Packages\Models\Enum\LineState;
use PHPUnit\Framework\TestCase;

class LineGroupingServiceTest extends TestCase
{
    public function testGroupingAnnotationsIntoBlocks(): void
    {
        $mockEvent = $this->createMock(EventInterface::class);
        $validUntil = new DateTimeImmutable();

        $groupingService = new LineGroupingService();

        $annotations = $groupingService->generateAnnotations(
            $mockEvent,
            [
                new LineCoverageQueryResult(
                    'mock-file',
                    1,
                    LineState::COVERED,
                    false,
                    false,
                    true,
                    1,
                    1
                ),
                new LineCoverageQueryResult(
                    'mock-file',
                    2,
                    LineState::UNCOVERED,
                    false,
                    false,
                    true,
                    1,
                    0
                ),
                new LineCoverageQueryResult(
                    'mock-file',
                    3,
                    LineState::UNCOVERED,
                    false,
                    false,
                    true,
                    1,
                    0
                ),
                new LineCoverageQueryResult(
                    'mock-file',
                    4,
                    LineState::COVERED,
                    false,
                    false,
                    true,
                    1,
                    1
                ),
                new LineCoverageQueryResult(
                    'mock-file',
                    8,
                    LineState::UNCOVERED,
                    false,
                    false,
                    true,
                    1,
                    0
                ),
                new LineCoverageQueryResult(
                    'mock-file',
                    9,
                    LineState::UNCOVERED,
                    false,
                    false,
                    true,
                    1,
                    0
                ),
            ],
            $validUntil
        );

        $this->assertEquals(
            [
                new PublishableMissingCoverageAnnotationMessage(
                    $mockEvent,
                    'mock-file',
                    false,
                    2,
                    3,
                    $validUntil
                ),
                new PublishableMissingCoverageAnnotationMessage(
                    $mockEvent,
                    'mock-file',
                    false,
                    8,
                    9,
                    $validUntil
                ),
            ],
            $annotations
        );
    }

    public function testIdentifyingMissingBranchesInsideMissingCoverage(): void
    {
        $mockEvent = $this->createMock(EventInterface::class);
        $validUntil = new DateTimeImmutable();

        $groupingService = new LineGroupingService();

        $annotations = $groupingService->generateAnnotations(
            $mockEvent,
            [
                new LineCoverageQueryResult(
                    'mock-file',
                    1,
                    LineState::PARTIAL,
                    false,
                    true,
                    false,
                    2,
                    1
                ),
                new LineCoverageQueryResult(
                    'mock-file',
                    4,
                    LineState::UNCOVERED,
                    false,
                    false,
                    true,
                    1,
                    0
                ),
                new LineCoverageQueryResult(
                    'mock-file',
                    6,
                    LineState::UNCOVERED,
                    false,
                    false,
                    true,
                    1,
                    0
                ),
                new LineCoverageQueryResult(
                    'mock-file',
                    7,
                    LineState::PARTIAL,
                    false,
                    true,
                    false,
                    5,
                    2
                ),
                new LineCoverageQueryResult(
                    'mock-file',
                    10,
                    LineState::COVERED,
                    false,
                    false,
                    true,
                    1,
                    1
                ),
            ],
            $validUntil
        );

        $this->assertEquals(
            [
                new PublishablePartialBranchAnnotationMessage(
                    $mockEvent,
                    'mock-file',
                    1,
                    2,
                    1,
                    $validUntil
                ),
                new PublishablePartialBranchAnnotationMessage(
                    $mockEvent,
                    'mock-file',
                    7,
                    5,
                    2,
                    $validUntil
                ),
                new PublishableMissingCoverageAnnotationMessage(
                    $mockEvent,
                    'mock-file',
                    false,
                    1,
                    7,
                    $validUntil
                ),
            ],
            $annotations
        );
    }

    public function testMissingCoverageOnMultiFileDiffs(): void
    {
        $mockEvent = $this->createMock(EventInterface::class);
        $validUntil = new DateTimeImmutable();

        $groupingService = new LineGroupingService();

        $annotations = $groupingService->generateAnnotations(
            $mockEvent,
            [
                new LineCoverageQueryResult(
                    'mock-file-1',
                    1,
                    LineState::PARTIAL,
                    false,
                    true,
                    false,
                    2,
                    1
                ),
                new LineCoverageQueryResult(
                    'mock-file-1',
                    4,
                    LineState::UNCOVERED,
                    false,
                    false,
                    true,
                    1,
                    0
                ),
                new LineCoverageQueryResult(
                    'mock-file-1',
                    6,
                    LineState::UNCOVERED,
                    false,
                    false,
                    true,
                    1,
                    0
                ),
                new LineCoverageQueryResult(
                    'mock-file-2',
                    7,
                    LineState::PARTIAL,
                    false,
                    true,
                    false,
                    5,
                    2
                ),
                new LineCoverageQueryResult(
                    'mock-file-2',
                    10,
                    LineState::COVERED,
                    false,
                    false,
                    true,
                    1,
                    1
                ),
            ],
            $validUntil
        );

        $this->assertEquals(
            [
                new PublishablePartialBranchAnnotationMessage(
                    $mockEvent,
                    'mock-file-1',
                    1,
                    2,
                    1,
                    $validUntil
                ),
                new PublishablePartialBranchAnnotationMessage(
                    $mockEvent,
                    'mock-file-2',
                    7,
                    5,
                    2,
                    $validUntil
                ),
                new PublishableMissingCoverageAnnotationMessage(
                    $mockEvent,
                    'mock-file-1',
                    false,
                    1,
                    6,
                    $validUntil
                ),
                new PublishableMissingCoverageAnnotationMessage(
                    $mockEvent,
                    'mock-file-2',
                    false,
                    7,
                    7,
                    $validUntil
                ),
            ],
            $annotations
        );
    }

    public function testSingleLineMissingCoverage(): void
    {
        $mockEvent = $this->createMock(EventInterface::class);
        $validUntil = new DateTimeImmutable();

        $groupingService = new LineGroupingService();

        $annotations = $groupingService->generateAnnotations(
            $mockEvent,
            [
                new LineCoverageQueryResult(
                    'mock-file-1',
                    1,
                    LineState::PARTIAL,
                    false,
                    true,
                    false,
                    2,
                    1
                ),
                new LineCoverageQueryResult(
                    'mock-file-2',
                    2,
                    LineState::UNCOVERED,
                    false,
                    false,
                    true,
                    1,
                    0
                ),
            ],
            $validUntil
        );

        $this->assertEquals(
            [
                new PublishablePartialBranchAnnotationMessage(
                    $mockEvent,
                    'mock-file-1',
                    1,
                    2,
                    1,
                    $validUntil
                ),
                new PublishableMissingCoverageAnnotationMessage(
                    $mockEvent,
                    'mock-file-1',
                    false,
                    1,
                    1,
                    $validUntil
                ),
                new PublishableMissingCoverageAnnotationMessage(
                    $mockEvent,
                    'mock-file-2',
                    false,
                    2,
                    2,
                    $validUntil
                ),
            ],
            $annotations
        );
    }

    public function testMultiMethodMissingCoverage(): void
    {
        $mockEvent = $this->createMock(EventInterface::class);
        $validUntil = new DateTimeImmutable();

        $groupingService = new LineGroupingService();

        $annotations = $groupingService->generateAnnotations(
            $mockEvent,
            [
                new LineCoverageQueryResult(
                    'mock-file-1',
                    1,
                    LineState::UNCOVERED,
                    false,
                    false,
                    true,
                    0,
                    0
                ),
                new LineCoverageQueryResult(
                    'mock-file-1',
                    2,
                    LineState::UNCOVERED,
                    false,
                    false,
                    true,
                    0,
                    0
                ),
                new LineCoverageQueryResult(
                    'mock-file-1',
                    5,
                    LineState::UNCOVERED,
                    false,
                    false,
                    true,
                    0,
                    0
                ),
                new LineCoverageQueryResult(
                    'mock-file-1',
                    7,
                    LineState::UNCOVERED,
                    true,
                    false,
                    false,
                    0,
                    0
                ),
                new LineCoverageQueryResult(
                    'mock-file-1',
                    8,
                    LineState::UNCOVERED,
                    false,
                    false,
                    true,
                    0,
                    0
                ),
                new LineCoverageQueryResult(
                    'mock-file-1',
                    8,
                    LineState::PARTIAL,
                    false,
                    true,
                    false,
                    2,
                    1
                ),
                new LineCoverageQueryResult(
                    'mock-file-1',
                    9,
                    LineState::UNCOVERED,
                    false,
                    false,
                    true,
                    0,
                    0
                ),
            ],
            $validUntil
        );

        $this->assertEquals(
            [
                new PublishableMissingCoverageAnnotationMessage(
                    $mockEvent,
                    'mock-file-1',
                    false,
                    1,
                    5,
                    $validUntil
                ),
                new PublishablePartialBranchAnnotationMessage(
                    $mockEvent,
                    'mock-file-1',
                    8,
                    2,
                    1,
                    $validUntil
                ),
                new PublishableMissingCoverageAnnotationMessage(
                    $mockEvent,
                    'mock-file-1',
                    true,
                    7,
                    9,
                    $validUntil
                ),
            ],
            $annotations
        );
    }
}
