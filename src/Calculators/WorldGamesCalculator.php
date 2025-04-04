<?php

namespace AustinW\SelectionProcedures\Calculators;

use AustinW\SelectionProcedures\Contracts\AthleteContract;
use AustinW\SelectionProcedures\Contracts\ProcedureCalculatorContract;
use AustinW\SelectionProcedures\Contracts\ResultContract;
use AustinW\SelectionProcedures\Dto\RankedAthlete;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

class WorldGamesCalculator implements ProcedureCalculatorContract
{
    /**
     * Calculate the ranking for the World Games procedure.
     * Selection is based on the sum of the highest two scores (Qual or Final)
     * across the specified selection events. No thresholds apply.
     *
     * @param string $apparatus
     * @param string $division
     * @param Collection<int, ResultContract> $results
     * @param array $config
     *
     * @return Collection<int, RankedAthlete>
     */
    public function calculateRanking(string $apparatus, string $division, Collection $results, array $config): Collection
    {
        $procedureYear = $config['year'] ?? now()->year;
        $minEvents = $config['rules']['min_events_attended'] ?? 1;
        $minAge = $config['divisions'][$division]['min_age'] ?? null;
        $combinedScoreCount = $config['rules']['combined_score_count'] ?? 2; // How many top scores to sum

        // 1. Filter results by apparatus and division
        $filteredResults = $results->filter(fn (ResultContract $result) =>
            strtolower($result->getApparatus()) === strtolower($apparatus)
            && $result->getDivision() === $division
        );

        // 2. Group results by athlete ID
        $resultsByAthlete = $filteredResults->groupBy(fn (ResultContract $result) => $result->getAthlete()->getId());

        $athleteCalculations = $resultsByAthlete->map(function (Collection $athleteResults, $athleteId) use ($minEvents, $minAge, $procedureYear, $combinedScoreCount) {
            /** @var AthleteContract $athlete */
            $athlete = $athleteResults->first()->getAthlete();

            // 3. Validate athlete eligibility
            // Age check
            if ($minAge !== null) {
                try {
                    $birthDate = CarbonImmutable::parse($athlete->getDateOfBirth());
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
            if ($athleteResults->unique(fn (ResultContract $r) => $r->getEventIdentifier())->count() < $minEvents) {
                return null; // Not eligible due to insufficient events
            }

            // 4. Calculate Combined Score (Highest N scores overall)
            $allScores = $athleteResults->flatMap(fn (ResultContract $r) => [
                $r->getQualificationScore(),
                $r->getFinalScore()
            ])->filter(fn ($score) => $score !== null && $score > 0)->sortDesc(); // Combine, filter nulls/zeros, sort

            $topScores = $allScores->take($combinedScoreCount);
            $combinedScore = $topScores->sum();
            $contributingScores = $topScores->values()->all();

            // Check if enough scores were available
             if ($topScores->count() < $combinedScoreCount) {
                 // Optionally handle athletes without enough scores, e.g., rank them lower or make ineligible
                 // For now, they will rank based on the sum of available scores.
                 // You might want to add a flag or specific handling here based on rules.
             }

            return [
                'athlete' => $athlete,
                'combinedScore' => $combinedScore,
                'contributingScores' => $contributingScores,
            ];
        })->filter(); // Remove nulls (ineligible athletes)

        // 5. Rank athletes based purely on Combined Score (desc)
        $rankedAthletesData = $athleteCalculations->sortByDesc('combinedScore')->values(); // Reset keys

        // 6. Assign ranks and create RankedAthlete DTOs
        $finalRankedList = new Collection();
        $currentRank = 1;
        $processedCount = 0;
        $lastScore = -1.0;

        foreach ($rankedAthletesData as $index => $data) {
            $processedCount++;
            // Determine rank - increment only if score is lower than previous
            if ($index > 0 && $data['combinedScore'] < $lastScore) {
                $currentRank = $processedCount;
            }

            $needsManualReview = false;
            // Check for ties based solely on combined score
            if (isset($rankedAthletesData[$index + 1])) {
                $nextData = $rankedAthletesData[$index + 1];
                if ($this->floatCompare($data['combinedScore'], $nextData['combinedScore'])) {
                    $needsManualReview = true;
                }
            }
            if ($index > 0) {
                $prevData = $rankedAthletesData[$index - 1];
                 if ($this->floatCompare($data['combinedScore'], $prevData['combinedScore'])) {
                    $needsManualReview = true;
                }
            }

            $finalRankedList->push(new RankedAthlete(
                athlete: $data['athlete'],
                combinedScore: $data['combinedScore'],
                rank: $currentRank,
                meetsPreferentialThreshold: false, // World Games has no thresholds
                meetsMinimumThreshold: false,      // World Games has no thresholds
                tieBreakerInfo: null,             // No defined tie-breaker other than score
                needsManualReview: $needsManualReview,
                contributingScores: ['overall_top_scores' => $data['contributingScores']],
            ));

            $lastScore = $data['combinedScore'];
        }

        // 7. Return collection of RankedAthlete DTOs
        return $finalRankedList;
    }
    
    /**
     * Compare two floating point numbers with an epsilon to account for floating point precision issues
     *
     * @param float $a First number to compare
     * @param float $b Second number to compare
     * @param float $epsilon Precision (defaults to a small value that works for gymnastics scores)
     * @return bool Whether the two numbers are equal within epsilon precision
     */
    private function floatCompare(float $a, float $b, float $epsilon = 0.00001): bool
    {
        return abs($a - $b) < $epsilon;
    }
} 