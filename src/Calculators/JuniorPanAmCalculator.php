<?php

namespace AustinW\SelectionProcedures\Calculators;

use AustinW\SelectionProcedures\Contracts\AthleteContract;
use AustinW\SelectionProcedures\Contracts\ProcedureCalculatorContract;
use AustinW\SelectionProcedures\Contracts\ResultContract;
use AustinW\SelectionProcedures\Dto\RankedAthlete;
use Illuminate\Support\Collection;

class JuniorPanAmCalculator implements ProcedureCalculatorContract
{
    /**
     * Calculate the ranking for the Junior Pan American Games selection procedure.
     * Selection is based on a Combined Score calculated as the sum of the two highest
     * Qualification Scores plus the highest Finals score from the three Evaluative Events.
     *
     * @param  Collection<int, ResultContract>  $results
     * @return Collection<int, RankedAthlete>
     */
    public function calculateRanking(string $apparatus, string $division, Collection $results, array $config): Collection
    {
        $procedureYear = $config['year'] ?? now()->year;

        // For Junior Pan Am, we only select 2 athletes per gender plus 1 alternate
        $maxTeamSize = 2;

        // 1. Filter results by apparatus and division (gender)
        $filteredResults = $results->filter(fn (ResultContract $result) => strtolower($result->getApparatus()) === strtolower($apparatus)
            && $result->getDivision() === $division
        );

        // 2. Group results by athlete ID
        $resultsByAthlete = $filteredResults->groupBy(fn (ResultContract $result) => $result->getAthlete()->getId());

        // 3. Collect all eligible athletes and calculate their Combined Scores
        $athleteCalculations = new Collection;

        foreach ($resultsByAthlete as $athleteId => $athleteResults) {
            /** @var AthleteContract $athlete */
            $athlete = $athleteResults->first()->getAthlete();

            // Check age eligibility - assume this is handled by level/division filtering
            // Skip athletes who are also assigned to World Games (would need to be passed in config)
            if (isset($config['ineligible_athletes']) && in_array($athleteId, $config['ineligible_athletes'])) {
                continue;
            }

            // Group results by event
            $resultsByEvent = $athleteResults->groupBy(fn (ResultContract $r) => $r->getEventIdentifier());

            // Get qualification and final scores for each event
            $qualificationScores = [];
            $finalScores = [];

            foreach ($resultsByEvent as $eventId => $eventResults) {
                // Get best qualification score for this event (should be only one per event)
                $bestQualScore = $eventResults->max(fn (ResultContract $r) => $r->getQualificationScore());
                $qualificationScores[$eventId] = $bestQualScore;

                // Get final score for this event (if available)
                $finalScore = $eventResults
                    ->filter(fn (ResultContract $r) => $r->getFinalScore() !== null)
                    ->max(fn (ResultContract $r) => $r->getFinalScore());

                if ($finalScore !== null) {
                    $finalScores[$eventId] = $finalScore;
                }
            }

            // Skip athletes with insufficient results (need at least 2 qualification scores)
            if (count($qualificationScores) < 2) {
                continue;
            }

            // Calculate Combined Score = top 2 qualification scores + top 1 final score
            arsort($qualificationScores);
            arsort($finalScores);

            // Get top 2 qualification scores
            $topQualScores = array_slice($qualificationScores, 0, 2, true);
            $sumTopQualScores = array_sum($topQualScores);

            // Get highest final score (if available)
            $topFinalScore = ! empty($finalScores) ? reset($finalScores) : 0;
            $topFinalEventId = ! empty($finalScores) ? key($finalScores) : null;

            // Calculate combined score
            $combinedScore = $sumTopQualScores + $topFinalScore;

            // Store all athlete calculations
            $athleteCalculations->push([
                'athlete' => $athlete,
                'id' => $athleteId,
                'combinedScore' => $combinedScore,
                'qualificationScores' => $qualificationScores,
                'finalScores' => $finalScores,
                'topQualScores' => $topQualScores,
                'highestQualScore' => ! empty($topQualScores) ? max($topQualScores) : 0,
                'highestFinalScore' => $topFinalScore,
                'highestFinalEvent' => $topFinalEventId,
                'gender' => $athlete->getGender(),
            ]);
        }

        // 4. Rank athletes by Combined Score
        $rankedAthletes = $athleteCalculations->sortByDesc('combinedScore')->values();

        // 5. Create RankedAthlete DTOs with appropriate rankings and tie-breaking
        $finalRankedList = new Collection;
        $currentRank = 1;
        $lastScore = -1.0;  // Initialize with a float value instead of null

        foreach ($rankedAthletes as $index => $data) {
            // Only increment rank if score is different from previous
            if ($index > 0 && ! $this->floatCompare($data['combinedScore'], $lastScore)) {
                $currentRank = $index + 1;
            }

            $needsManualReview = false;
            $tieBreakerInfo = null;

            // Check for ties and apply tie-breaking rules
            if ($index > 0 && $this->floatCompare($data['combinedScore'], $lastScore)) {
                // Get previous athlete data for tie-breaking
                $prevData = $rankedAthletes[$index - 1];

                // Tie-breaker #1: Higher individual Qualification Score
                if ($data['highestQualScore'] > $prevData['highestQualScore']) {
                    // Current athlete wins tie-breaker
                    $tieBreakerInfo = "Won tie-breaker with higher qualification score: {$data['highestQualScore']} vs {$prevData['highestQualScore']}";
                } elseif ($data['highestQualScore'] < $prevData['highestQualScore']) {
                    // Previous athlete already won tie-breaker
                    $tieBreakerInfo = "Lost tie-breaker with lower qualification score: {$data['highestQualScore']} vs {$prevData['highestQualScore']}";
                    // Keep same rank as previous athlete
                    $currentRank = $rankedAthletes[$index - 1]['rank'] ?? $currentRank;
                } else {
                    // Tie persists after qualification score comparison - needs manual review
                    $needsManualReview = true;
                    $tieBreakerInfo = 'Tie persists after comparing qualification scores';
                }
            }

            // Check if the next athlete has the same score (potential tie)
            if (isset($rankedAthletes[$index + 1]) &&
                $this->floatCompare($data['combinedScore'], $rankedAthletes[$index + 1]['combinedScore'])) {
                // Mark as potential tie with next athlete
                $needsManualReview = true;
            }

            // Track the rank for this athlete (needed for tie handling)
            $rankedAthletes[$index]['rank'] = $currentRank;

            // Create the RankedAthlete DTO
            $finalRankedList->push(new RankedAthlete(
                athlete: $data['athlete'],
                combinedScore: $data['combinedScore'],
                rank: $currentRank,
                meetsPreferentialThreshold: false, // Not applicable for Jr Pan Am
                meetsMinimumThreshold: true, // No minimum threshold defined for Jr Pan Am
                tieBreakerInfo: $tieBreakerInfo,
                needsManualReview: $needsManualReview,
                contributingScores: [
                    'qualification_scores' => $data['qualificationScores'],
                    'top_qualification_scores' => $data['topQualScores'],
                    'final_scores' => $data['finalScores'],
                    'highest_qualification_score' => $data['highestQualScore'],
                    'highest_final_score' => $data['highestFinalScore'],
                ],
            ));

            $lastScore = $data['combinedScore'];
        }

        return $finalRankedList;
    }

    /**
     * Compare two floating point numbers with an epsilon to account for floating point precision issues
     */
    private function floatCompare(float $a, float $b, float $epsilon = 0.00001): bool
    {
        return abs($a - $b) < $epsilon;
    }
}
