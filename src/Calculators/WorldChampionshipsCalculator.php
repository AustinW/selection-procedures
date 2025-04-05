<?php

namespace AustinW\SelectionProcedures\Calculators;

use AustinW\SelectionProcedures\Contracts\AthleteContract;
use AustinW\SelectionProcedures\Contracts\ProcedureCalculatorContract;
use AustinW\SelectionProcedures\Contracts\ResultContract;
use AustinW\SelectionProcedures\Dto\RankedAthlete;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

class WorldChampionshipsCalculator implements ProcedureCalculatorContract
{
    /**
     * Calculate the ranking for the World Championships procedure.
     *
     * @param string $apparatus
     * @param string $division
     * @param Collection $results
     * @param array $config
     *
     * @return Collection
     */
    public function calculateRanking(string $apparatus, string $division, Collection $results, array $config): Collection
    {
        $procedureYear = $config['year'] ?? now()->year;
        $minEvents = $config['rules']['min_events_attended'] ?? 1;
        $minAge = $config['divisions'][$division]['min_age'] ?? null;
        $qualCount = $config['rules']['combined_score_qual_count'] ?? 0;
        $finalCount = $config['rules']['combined_score_final_count'] ?? 0;

        // 1. Filter results by apparatus and division
        $filteredResults = $results->filter(function (ResultContract $result) use ($division, $apparatus) {
            return strtolower($result->getApparatus()) === strtolower($apparatus)
                && strtoupper($result->getDivision()) === strtoupper($division);
        });

        // 2. Group results by athlete ID
        $resultsByAthlete = $filteredResults->groupBy(fn (ResultContract $result) => $result->getAthlete()->getId());

        $athleteCalculations = $resultsByAthlete->map(function (Collection $athleteResults, $athleteId) use ($minEvents, $minAge, $procedureYear, $qualCount, $finalCount, $config, $apparatus, $division) {
            /** @var AthleteContract $athlete */
            $athlete = $athleteResults->first()->getAthlete();
            $gender = strtolower($athlete->getGender());

            // 3. Validate athlete eligibility
            // Age check (attained by Dec 31st of procedure year)
            if ($minAge !== null) {
                try {
                    $birthDate = CarbonImmutable::parse($athlete->getDateOfBirth());
                    // Correct age calculation: Age attained by Dec 31st
                    $age = $procedureYear - $birthDate->year;
                    if ($age < $minAge) {
                        return null; // Not eligible due to age
                    }
                } catch (\Throwable $e) {
                    error_log("Error processing age eligibility for athlete ID " . ($athleteId ?? 'unknown') . ": " . $e->getMessage());
                    return null; // Treat errors as ineligible
                }
            }

            // Minimum events attended check
            // This is ignored for now as we want to include all athletes who have competed
            // if ($athleteResults->unique(fn (ResultContract $r) => $r->getEventIdentifier())->count() < $minEvents) {
            //     return null; // Not eligible due to insufficient events
            // }

            // 4. Calculate Combined Score
            $topQualScores = $athleteResults->map->getQualificationScore()->sortDesc()->take($qualCount);
            $topFinalScores = $athleteResults->map->getFinalScore()->filter()->sortDesc()->take($finalCount);

            $combinedScore = $topQualScores->sum() + $topFinalScores->sum();

            // 5. Get thresholds
            $thresholds = $config['divisions'][$division]['thresholds'][$apparatus] ?? null;
            $preferentialThreshold = $thresholds['preferential'][$gender] ?? PHP_FLOAT_MAX;
            $minimumThreshold = $thresholds['minimum'][$gender] ?? PHP_FLOAT_MAX;

            // 6. Determine if athlete meets thresholds
            $meetsPreferential = float_compare($combinedScore, '>=', $preferentialThreshold);
            // Check if *any* score meets the minimum threshold
            $meetsMinimum = $athleteResults->contains(fn (ResultContract $r) =>
                float_compare($r->getQualificationScore(), '>=', $minimumThreshold)
                || ($r->getFinalScore() !== null && float_compare($r->getFinalScore(), '>=', $minimumThreshold))
            );

            // Store highest single qualification score for tie-breaking
            $highestQualScore = $athleteResults->map->getQualificationScore()->max() ?? 0.0;

            return [
                'athlete' => $athlete,
                'combinedScore' => $combinedScore,
                'meetsPreferentialThreshold' => $meetsPreferential,
                'meetsMinimumThreshold' => $meetsMinimum,
                'highestQualScore' => $highestQualScore,
                'contributingScores' => [
                    'qualification' => $topQualScores->values()->all(),
                    'final' => $topFinalScores->values()->all(),
                ],
            ];
        })->filter(); // Remove nulls (ineligible athletes)

        // 7 & 8. Rank athletes based on Combined Score (desc) and tie-breaker (highest qual score desc)
        $rankedAthletesData = $athleteCalculations->sortByDesc(function ($data) {
            // Sort primarily by combined score, secondarily by highest qualification score
            return sprintf('%08.3f-%08.3f', $data['combinedScore'], $data['highestQualScore']);
        })->values(); // Reset keys after sorting

        // 9. Assign ranks and create RankedAthlete DTOs
        $finalRankedList = new Collection();
        $currentRank = 1; // Start rank at 1
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
