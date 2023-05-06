<?php

namespace App\Model;

interface PublishableCoverageDataInterface
{
    public function getTotalUploads(): int;
    
    public function getTotalLines(): int;

    public function getAtLeastPartiallyCoveredLines(): int;

    public function getUncoveredLines(): int;

    public function getCoveragePercentage(): float;

    public function getCommitLineCoverage(): array;
}
