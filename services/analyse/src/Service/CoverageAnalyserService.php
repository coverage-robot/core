<?php

namespace App\Service;

use App\Client\BigQueryClient;
use Psr\Log\LoggerInterface;

class CoverageAnalyserService
{
    public function __construct(
        private readonly BigQueryClient $bigQueryClient,
        private readonly LoggerInterface $logger
    ) {
    }

    public function analyse(string $uniqueId): void
    {
        $query = <<<SQL
WITH unnested AS (
  SELECT
    *,
    (
      SELECT
        IF (
          value <> '',
          CAST(value AS int),
          0
        )
      FROM
        UNNEST(metadata)
      WHERE
        KEY = "lineHits"
    ) AS hits,
    IF (
      type = "BRANCH",
      (
        SELECT
          IF (
            value <> '',
            CAST(value AS int),
            0
          )
        FROM
          UNNEST(metadata)
        WHERE
          KEY = "partial"
      ),
      0
    ) AS isPartiallyHit
  FROM
    `coverage-384615.line_analytics.lines`
),
coverage AS (
  SELECT
    IF(
      SUM(hits) = 0,
      "uncovered",
      IF (
        MAX(isPartiallyHit) = 1,
        "partial",
        "covered"
      )
    ) as state
  FROM
    unnested
  GROUP BY
    fileName,
    lineNumber
),
summed AS (
  SELECT
COUNT(*) as lines,
SUM(IF(state = "covered", 1, 0)) as covered,
SUM(IF(state = "partial", 1, 0)) as partial,
SUM(IF(state = "uncovered", 1, 0)) as uncovered,
FROM
  coverage
)
SELECT
SUM(lines) as lines,
SUM(covered) as covered,
SUM(partial) as partial,
SUM(uncovered) as uncovered,
CONCAT(ROUND((SUM(covered) + SUM(partial)) / SUM(lines) * 100, 2), "%") as coverage
FROM
summed
SQL;


        $job = $this->bigQueryClient->query($query);

        $results = $this->bigQueryClient->runQuery($job);

        $rows = $results->rows(
            [
                'maxResults' => 1
            ]
        );

        $line = sprintf(
            "Total Lines: %s\n\rCoverage: %s\n\rPartial: %s\n\rUncovered: %s\n\rCoverage (%%): %s\n\r",
            $rows->current()['lines'],
            $rows->current()['covered'],
            $rows->current()['partial'],
            $rows->current()['uncovered'],
            $rows->current()['coverage']
        );

        $this->logger->info($line);
    }
}
