<?php

namespace App\Model;

interface PublishableCoverageDataInterface
{
    public function getTotalUploads(): int;

    public function getTotalLines(): int;

    public function getAtLeastPartiallyCoveredLines(): int;

    public function getUncoveredLines(): int;

    public function getTotalCoveragePercentage(): float;

    public function getCommitLineCoverage(): array;

    public function getTagCoverage(): array;
}
