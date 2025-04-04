<?php

namespace AustinW\SelectionProcedures\Calculators;

use AustinW\SelectionProcedures\Contracts\AthleteContract;
use AustinW\SelectionProcedures\Contracts\ProcedureCalculatorContract;
use AustinW\SelectionProcedures\Contracts\ResultContract;
use AustinW\SelectionProcedures\Dto\RankedAthlete;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

class WagcCalculator implements ProcedureCalculatorContract
{
    public function calculateRanking(string $apparatus, string $division, Collection $results, array $config): Collection
    {
        $procedureYear = $config['year'] ?? now()->year;
        
        // WAGC specific config
        $divisionConfig = $config['divisions'][$division] ?? [];
        $minAge = $divisionConfig['min_age'] ?? null;
        $maxAge = $divisionConfig['max_age'] ?? null;
        $requiredLevel = $divisionConfig['level'] ?? null; // e.g., 'senior_elite' for 17-21
        $qualCount = $config['rules']['combined_score_qual_count'] ?? 0;

        // 1. Filter results by apparatus and division
        $filteredResults = $results->filter(fn (ResultContract $result) =>
            strtolower($result->getApparatus()) === strtolower($apparatus)
            && $result->getDivision() === $division
        );

        // 2. Group results by athlete ID
        $resultsByAthlete = $filteredResults->groupBy(fn (ResultContract $result) => $result->getAthlete()->getId());

        // 3. Map results by athlete, checking eligibility within the map
        $athleteCalculations = $resultsByAthlete->map(function (Collection $athleteResults, $athleteId) use ($minAge, $maxAge, $procedureYear, $requiredLevel, $qualCount, $config, $apparatus, $division) {

            if ($athleteResults->isEmpty()) {
                return null;
            }
            /** @var AthleteContract $athlete */
            $athlete = $athleteResults->first()->getAthlete();

            // Validate athlete eligibility
            if ($minAge !== null || $maxAge !== null) {
                try {
                    $birthDate = CarbonImmutable::parse($athlete->getDateOfBirth());
                    // Correct age calculation: Age attained by Dec 31st
                    $age = $procedureYear - $birthDate->year;
                    $isBelowMin = ($minAge !== null && $age < $minAge);
                    $isAboveMax = ($maxAge !== null && $age > $maxAge); // Original correct check
                    if ($isBelowMin || $isAboveMax) {
                        return null; // Ineligible
                    }
                } catch (\Throwable $e) {
                     error_log("Error processing age eligibility for athlete ID " . ($athleteId ?? 'unknown') . ": " . $e->getMessage());
                     return null; // Treat errors as ineligible
                }
            }

            // Check for required level if specified in config
            if ($requiredLevel) {
                // Check if *any* result for this athlete in this division matches the required level
                $hasRequiredLevel = $athleteResults->contains(fn(ResultContract $r) => strtolower($r->getLevel()) === strtolower($requiredLevel));
                if (!$hasRequiredLevel) {
                    return null; // Ineligible because level requirement not met
                }
            }

            // Calculate Combined Score etc. for eligible athletes
            try {
                $gender = strtolower($athlete->getGender());
                $topQualScores = $athleteResults->map->getQualificationScore()->sortDesc()->take($qualCount);
                $combinedScore = $topQualScores->sum();

                $thresholds = $config['divisions'][$division]['thresholds'][$apparatus] ?? null;
                $preferentialThreshold = $thresholds['preferential'][$gender] ?? PHP_FLOAT_MAX;
                $minimumThreshold = $thresholds['minimum'][$gender] ?? PHP_FLOAT_MAX;

                $meetsPreferential = $athleteResults->contains(fn (ResultContract $r) =>
                    float_compare($r->getQualificationScore(), '>=', $preferentialThreshold)
                );
                $meetsMinimum = $athleteResults->contains(fn (ResultContract $r) =>
                    float_compare($r->getQualificationScore(), '>=', $minimumThreshold)
                );
                $highestQualScore = $athleteResults->map->getQualificationScore()->max() ?? 0.0;

                return [
                    'athlete' => $athlete,
                    'combinedScore' => $combinedScore,
                    'meetsPreferentialThreshold' => $meetsPreferential,
                    'meetsMinimumThreshold' => $meetsMinimum,
                    'highestQualScore' => $highestQualScore,
                    'contributingScores' => [
                        'qualification' => $topQualScores->values()->all(),
                        'final' => [],
                    ],
                ];
            } catch (\Throwable $e) {
                error_log("Error processing calculation for athlete ID " . ($athleteId ?? 'unknown') . ": " . $e->getMessage());
                return null; // Treat errors as ineligible
            }
        })->filter(fn ($data) => $data !== null); // Filter out nulls (ineligible or errors) AFTER mapping

        // 7 & 8. Rank athletes based on Combined Score (desc) and tie-breaker (highest qual score desc)
        $rankedAthletesData = $athleteCalculations->sortByDesc(function ($data) {
            // Sort primarily by combined score, secondarily by highest qualification score
            return sprintf('%08.3f-%08.3f', $data['combinedScore'], $data['highestQualScore']);
        })->values(); // Reset keys after sorting

        // 9. Assign ranks and create RankedAthlete DTOs
        $finalRankedList = new Collection();
        $currentRank = 1;
        $processedCount = 0;
        $lastScore = -1.0;
        $lastHighestQual = -1.0;

        foreach ($rankedAthletesData as $index => $data) {
            $processedCount++;
            // Determine rank - increment only if score is lower than previous
            if ($index > 0 && ($data['combinedScore'] < $lastScore || $data['highestQualScore'] < $lastHighestQual)) {
                $currentRank = $processedCount;
            }

            $needsManualReview = false;
            // Check for ties with the *next* athlete
            if (isset($rankedAthletesData[$index + 1])) {
                $nextData = $rankedAthletesData[$index + 1];
                if ($data['combinedScore'] === $nextData['combinedScore'] && $data['highestQualScore'] === $nextData['highestQualScore']) {
                    $needsManualReview = true;
                }
            }
             // Also check against the *previous* athlete to mark the second person in a tie
            if ($index > 0) {
                $prevData = $rankedAthletesData[$index - 1];
                if ($data['combinedScore'] === $prevData['combinedScore'] && $data['highestQualScore'] === $prevData['highestQualScore']) {
                    $needsManualReview = true;
                }
            }

            $finalRankedList->push(new RankedAthlete(
                athlete: $data['athlete'],
                combinedScore: $data['combinedScore'],
                rank: $currentRank,
                meetsPreferentialThreshold: $data['meetsPreferentialThreshold'],
                meetsMinimumThreshold: $data['meetsMinimumThreshold'],
                tieBreakerInfo: sprintf("Highest Qual: %.3f", $data['highestQualScore']),
                needsManualReview: $needsManualReview,
                contributingScores: $data['contributingScores'],
            ));

            $lastScore = $data['combinedScore'];
            $lastHighestQual = $data['highestQualScore'];
        }

        // 10. Return collection of RankedAthlete DTOs
        return $finalRankedList;
    }
} 