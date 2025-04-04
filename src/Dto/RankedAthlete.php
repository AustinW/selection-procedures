<?php

namespace AustinW\SelectionProcedures\Dto;

use AustinW\SelectionProcedures\Contracts\AthleteContract;

class RankedAthlete
{
    public function __construct(
        public readonly AthleteContract $athlete,
        public readonly float $combinedScore,
        public readonly int $rank,
        public readonly bool $meetsPreferentialThreshold,
        public readonly bool $meetsMinimumThreshold,
        public readonly ?string $tieBreakerInfo = null, // Store info about tie-breakers if needed
        public readonly bool $needsManualReview = false, // Flag for unresolved ties
        public readonly array $contributingScores = [], // Optional: Store the scores that made up the combined score
    ) {}
}
