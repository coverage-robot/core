<?php

declare(strict_types=1);

namespace App\Strategy\GoCover;

use App\Exception\ParseException;
use App\Model\Coverage;
use App\Model\File;
use App\Model\Line\Branch;
use App\Model\Line\Statement;
use App\Service\PathFixingService;
use App\Strategy\ParseStrategyInterface;
use Exception;
use LogicException;
use OutOfBoundsException;
use Override;
use Packages\Contracts\Format\CoverageFormat;
use Packages\Contracts\Provider\Provider;
use Psr\Log\LoggerInterface;

final readonly class GoCoverParseStrategy implements ParseStrategyInterface
{
    /**
     * Each line is formatted as `name.go:line.column,line.column numberOfStatements count`.
     */
    // phpcs:ignore
    private const string LINE_STRUCTURE = '/^(?<file>.*?\.go):(?<startLine>[0-9]+).(?<startColumn>[0-9]+),(?<endLine>[0-9]+).(?<endColumn>[0-9]+)\s(?<statements>[0-9]+)\s(?<count>[0-9]+)$/';

    /**
     * If arguments such as mode are provided, we can skip the line.
     */
    private const string MODE_STRUCTURE = 'mode:';

    public function __construct(
        private LoggerInterface $parseStrategyLogger,
        private PathFixingService $pathFixingService
    ) {
    }

    #[Override]
    public function supports(string $content): bool
    {
        /** @var string[] $lines */
        $lines = preg_split('/\n|\r\n?/', $content);

        foreach ($lines as $line) {
            $line = trim($line);

            // Skip empty lines
            if ($line === '') {
                continue;
            }

            // If a mode (i.e. atomic) is provided, skip the line
            if (str_starts_with($line, self::MODE_STRUCTURE)) {
                continue;
            }

            // Match the record type and its data
            if (preg_match(self::LINE_STRUCTURE, $line) !== 1) {
                $this->parseStrategyLogger->error(
                    'Unable to validate structure of line in Go Cover file.',
                    [
                        'line' => $line
                    ]
                );

                return false;
            }
        }

        return true;
    }

    #[Override]
    public function parse(
        Provider $provider,
        string $owner,
        string $repository,
        string $projectRoot,
        string $content
    ): Coverage {
        if (!$this->supports($content)) {
            throw ParseException::notSupportedException();
        }

        try {
            $coverage = new Coverage(
                CoverageFormat::GO_COVER,
                root: $projectRoot,
            );
        } catch (Exception $exception) {
            throw new ParseException(
                sprintf(
                    'Failed to create coverage model for %s %s %s. Error was %s',
                    $provider->value,
                    $owner,
                    $repository,
                    $exception
                ),
                previous: $exception
            );
        }

        /** @var string[] $blocks */
        $blocks = preg_split('/\n|\r\n?/', $content);

        try {
            foreach ($blocks as $block) {
                $block = trim($block);

                // Skip empty lines
                if ($block === '') {
                    continue;
                }

                // If a mode (i.e. atomic) is provided, skip the line
                if (str_starts_with($block, self::MODE_STRUCTURE)) {
                    continue;
                }

                $coverage = $this->handleBlock(
                    $provider,
                    $owner,
                    $repository,
                    $coverage,
                    $block
                );
            }
        } catch (LogicException $logicException) {
            throw new ParseException(
                sprintf(
                    'Unable to parse coverage of line in Go Cover file for %s %s %s.',
                    $provider->value,
                    $owner,
                    $repository,
                ),
                previous: $logicException
            );
        }

        return $coverage;
    }

    /**
     * Each line of a Go Coverage file is a 'block' of coverage - where a block has a start and end line (and
     * start and end columns), but all have the same hit count.
     *
     * For example, a block which spans 2 lines (and has 2 statements) but is never hit will look
     * like this:
     *
     * ```
     * github.com/some-owner/some-repo/internal/cmd/ipv6/main.go:28.2,30.16 2 0
     * ```
     *
     * @throws LogicException
     */
    private function handleBlock(
        Provider $provider,
        string $owner,
        string $repository,
        Coverage $coverage,
        string $line
    ): Coverage {
        $line = trim($line);

        // Skip empty lines
        if ($line === '') {
            return $coverage;
        }

        $parts = [];

        // Match the record type and its data
        if (preg_match(self::LINE_STRUCTURE, $line, $parts) !== 1) {
            $this->parseStrategyLogger->error(
                'Unable to validate structure of line in Go Cover file.',
                [
                    'line' => $line
                ]
            );

            throw new ParseException(
                sprintf(
                    'Unable to parse structure of line in Go Cover file: %s',
                    $line
                )
            );
        }

        // Perform any path fixings we need to do
        $filePath = $this->pathFixingService->fixPath(
            $provider,
            $owner,
            $repository,
            $parts['file'],
            $coverage->getRoot()
        );

        $files = $coverage->getFiles();

        /**
         * Generally Go cover files are in sequential order - where all of the blocks for
         * a particular file are together sequentially. So most of the time the last item
         * of the coverage should be the one we're looking for.
         *
         * If its not, it _should_ be the first time we've seen the file path - in which case,
         * we need to setup a new file!
         */
        $file = end($files);

        if (!$file || $file->getFileName() !== $filePath) {
            $file = new File(
                fileName: $filePath,
                lines: []
            );
            $coverage->addFile($file);
        }

        $lineHits = (int)$parts['count'];

        $startLine = (int)$parts['startLine'];
        $endLine = (int)$parts['endLine'];

        try {
            /**
             * Each block of a Go cover file (represented as a single line) will span between two lines (with
             * start and end columns recorded).
             *
             * It's possible that between two blocks, one block ends at column 39, and another block starts
             * from 39 onwards (non-inclusive).
             *
             * For example:
             *
             * ```
             * github.com/some-owner/some-repo/internal/ipv4/ipv4.go:54.2,54.46 1 1
             * github.com/some-owner/some-repo/internal/ipv4/ipv4.go:54.46,56.3 1 1
             * ```
             *
             * If one starts while the other ends on the same line, that means we've uncovered an if/elseif/else
             * line - and so we should any existing line data we have into a branch, and anything else in the block
             * should continue as statements.
             */
            $alreadyParsedLine = $file->getLine((string)$startLine);

            if ($alreadyParsedLine instanceof Branch) {
                $alreadyParsedLine->addToBranchHits(count($alreadyParsedLine->getBranchHits()) + 1, $lineHits);
            }

            // The line we already have tracked is not a branch (it wont be when running through the
            // individual block data), meaning we should convert it to a branch now we officially know its
            // type isn't a simple statement.
            $file->setLine(
                new Branch(
                    lineNumber: $alreadyParsedLine->getLineNumber(),
                    lineHits: max($alreadyParsedLine->getLineHits(), $lineHits),
                    branchHits: [
                        0 => $lineHits
                    ]
                )
            );


            if ($startLine === $endLine) {
                // This block only spans the single line - we've already recorded it, so can finish
                // here
                return $coverage;
            }

            ++$startLine;
        } catch (OutOfBoundsException) {
            // No line recorded with this line number - we can start from here.
        }

        if ($startLine > $endLine) {
            $this->parseStrategyLogger->error(
                sprintf(
                    'Invalid Go cover file. The end of a block (%s) started before the start line (%s)',
                    $endLine,
                    $startLine,
                ),
                [
                    'startLine' => $startLine,
                    'endLine' => $endLine,
                    'line' => $line,
                ]
            );

            throw new LogicException(
                sprintf(
                    'Invalid Go cover file for %s %s %s. The start and end lines did not match up (%d > %d).',
                    $provider->value,
                    $owner,
                    $repository,
                    $startLine,
                    $endLine,
                ),
            );
        }

        for ($i = $startLine; $i <= $endLine; ++$i) {
            // Everything is a statement in Go cover files
            $line = new Statement(
                lineNumber: $i,
                lineHits: $lineHits
            );

            $file->setLine($line);
        }

        return $coverage;
    }
}
