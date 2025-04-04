<?php

namespace AustinW\SelectionProcedures\Calculators;

use AustinW\SelectionProcedures\Contracts\AthleteContract;
use AustinW\SelectionProcedures\Contracts\ProcedureCalculatorContract;
use AustinW\SelectionProcedures\Contracts\ResultContract;
use AustinW\SelectionProcedures\Dto\RankedAthlete;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

class EDPCalculator implements ProcedureCalculatorContract
{
    /**
     * Calculate the ranking for the Elite Development Program (EDP) procedure.
     * Selection is based on qualification scores with specific minimum thresholds
     * and prioritized selection criteria across specific age/level divisions.
     *
     * @param  Collection<int, ResultContract>  $results
     * @return Collection<int, RankedAthlete>
     */
    public function calculateRanking(string $apparatus, string $division, Collection $results, array $config): Collection
    {
        $procedureYear = $config['year'] ?? now()->year;
        $minQualificationScore = $this->getMinQualificationScore($apparatus, $division, $config);

        // Maximum athletes per team varies by discipline
        $maxTeamSize = match (strtolower($apparatus)) {
            'trampoline' => $config['rules']['team_size']['trampoline'] ?? 16,
            'tumbling' => $config['rules']['team_size']['tumbling'] ?? 12,
            'double-mini' => $config['rules']['team_size']['double-mini'] ?? 12,
            default => 12, // Default fallback
        };

        // Gender is needed because double-mini has gender-specific score thresholds
        $athleteGender = $division === 'male' ? 'male' : 'female';

        // 1. Filter results by apparatus and division (like gender or 'all')
        $filteredResults = $results->filter(fn (ResultContract $result) => strtolower($result->getApparatus()) === strtolower($apparatus)
            && $result->getDivision() === $division
        );

        // 2. Group results by athlete ID
        $resultsByAthlete = $filteredResults->groupBy(fn (ResultContract $result) => $result->getAthlete()->getId());

        // 3. Collect all eligible athletes and their best scores
        $athleteCalculations = new Collection;

        foreach ($resultsByAthlete as $athleteId => $athleteResults) {
            /** @var AthleteContract $athlete */
            $athlete = $athleteResults->first()->getAthlete();

            // Check age eligibility
            try {
                $birthDate = CarbonImmutable::parse($athlete->getDateOfBirth());
                $age = $procedureYear - $birthDate->year;

                // Skip if not age eligible based on division rules
                if (! $this->isAgeEligible($age, $athlete->getLevel(), $config)) {
                    continue;
                }
            } catch (\Throwable $e) {
                error_log('Error processing age eligibility for athlete ID '.($athleteId ?? 'unknown').': '.$e->getMessage());

                continue; // Skip athletes with invalid date of birth
            }

            // Group results by event
            $resultsByEvent = $athleteResults->groupBy(fn (ResultContract $r) => $r->getEventIdentifier());

            // Calculate best qualification score across events
            $bestQualificationScore = 0;
            $bestQualificationEvent = null;

            foreach ($resultsByEvent as $eventId => $eventResults) {
                // For each event, take highest qualification score (sum of passes/routines in a single competition)
                $bestEventScore = $eventResults->max(fn (ResultContract $r) => $r->getQualificationScore());

                if ($bestEventScore > $bestQualificationScore) {
                    $bestQualificationScore = $bestEventScore;
                    $bestQualificationEvent = $eventId;
                }
            }

            // Store calculation including division/level info for categorizing later
            $level = $athlete->getLevel();

            $athleteCalculations->push([
                'athlete' => $athlete,
                'id' => $athleteId,
                'qualificationScore' => $bestQualificationScore,
                'level' => $level,
                'age' => $age,
                'meetsMinimum' => $bestQualificationScore >= $minQualificationScore,
                'ageGroup' => $this->determineAgeGroup($age),
                'category' => $this->determineCategory($level, $age),
                'gender' => $athlete->getGender(),
            ]);
        }

        // 4. Sort eligible athletes by score within their categories
        $rankedAthletes = new Collection;
        $athletes13_14_YouthElite = $athleteCalculations
            ->filter(fn ($data) => $data['category'] === 'youth_elite_13_14' && $data['meetsMinimum'])
            ->sortByDesc('qualificationScore')
            ->values();

        $athletes11_12_YouthElite = $athleteCalculations
            ->filter(fn ($data) => $data['category'] === 'youth_elite_11_12' && $data['meetsMinimum'])
            ->sortByDesc('qualificationScore')
            ->values();

        $allEligibleAthletes = $athleteCalculations
            ->filter(fn ($data) => $data['meetsMinimum'])
            ->sortByDesc('qualificationScore')
            ->values();

        // 5. Apply selection criteria based on discipline rules

        // Track selected athlete IDs to avoid duplicates
        $selectedAthleteIds = [];

        // Criterion 1: First, select top 4 from Youth Elite 13-14
        $selectedYouthElite13_14 = $athletes13_14_YouthElite->take(4);
        foreach ($selectedYouthElite13_14 as $data) {
            $rankedAthletes->push($data);
            $selectedAthleteIds[] = $data['id'];
        }

        // Criterion 2: Next, select top 4 from Youth Elite 11-12
        $selectedYouthElite11_12 = $athletes11_12_YouthElite->take(4);
        foreach ($selectedYouthElite11_12 as $data) {
            $rankedAthletes->push($data);
            $selectedAthleteIds[] = $data['id'];
        }

        // Criterion 3: Next, select additional athletes from all eligible divisions
        // excluding already selected athletes
        $remainingAthletes = $allEligibleAthletes
            ->filter(fn ($data) => ! in_array($data['id'], $selectedAthleteIds))
            ->values();

        // Determine how many athletes to take from remaining pool (depends on discipline)
        $remainingSelectionCount = match (strtolower($apparatus)) {
            'trampoline' => 8, // For trampoline, take 8 more
            default => 4, // For tumbling & double-mini, take 4 more
        };

        $selectedRemainingAthletes = $remainingAthletes->take($remainingSelectionCount);
        foreach ($selectedRemainingAthletes as $data) {
            $rankedAthletes->push($data);
            $selectedAthleteIds[] = $data['id'];
        }

        // Criterion 4: Check if each division has at least 2 athletes and add if needed
        $categoryCounts = $rankedAthletes->groupBy('category')->map->count();
        $eligibleCategories = ['youth_elite_13_14', 'youth_elite_11_12', 'level_10_13_14', 'level_10_11_12'];

        foreach ($eligibleCategories as $category) {
            $currentCount = $categoryCounts[$category] ?? 0;

            if ($currentCount < 2) {
                // Add up to 2 more athletes from this division
                $additionalNeeded = 2 - $currentCount;

                $additionalFromCategory = $athleteCalculations
                    ->filter(fn ($data) => $data['category'] === $category && ! in_array($data['id'], $selectedAthleteIds))
                    ->sortByDesc('qualificationScore')
                    ->take($additionalNeeded)
                    ->values();

                foreach ($additionalFromCategory as $data) {
                    $rankedAthletes->push($data);
                    $selectedAthleteIds[] = $data['id'];
                }
            }
        }

        // Criterion 5: If still not at max team size, add next best remaining athletes
        $moreNeeded = $maxTeamSize - count($selectedAthleteIds);

        if ($moreNeeded > 0) {
            $remainingTopScorers = $athleteCalculations
                ->filter(fn ($data) => ! in_array($data['id'], $selectedAthleteIds))
                ->sortByDesc('qualificationScore')
                ->take($moreNeeded)
                ->values();

            foreach ($remainingTopScorers as $data) {
                $rankedAthletes->push($data);
                $selectedAthleteIds[] = $data['id'];
            }
        }

        // 6. Rank final list by qualification score
        $rankedAthletes = $rankedAthletes->sortByDesc('qualificationScore')->values();

        // 7. Create RankedAthlete DTOs
        $finalRankedList = new Collection;
        $currentRank = 1;
        $lastScore = -1.0;

        foreach ($rankedAthletes as $index => $data) {
            // Determine rank - increment only if score is lower than previous
            if ($index > 0 && ! $this->floatCompare($data['qualificationScore'], $lastScore)) {
                $currentRank = $index + 1;
            }

            $needsManualReview = false;

            // Check for ties
            if (isset($rankedAthletes[$index + 1]) &&
                $this->floatCompare($data['qualificationScore'], $rankedAthletes[$index + 1]['qualificationScore'])) {
                $needsManualReview = true;
            }

            if ($index > 0 &&
                $this->floatCompare($data['qualificationScore'], $rankedAthletes[$index - 1]['qualificationScore'])) {
                $needsManualReview = true;
            }

            $finalRankedList->push(new RankedAthlete(
                athlete: $data['athlete'],
                combinedScore: $data['qualificationScore'],
                rank: $currentRank,
                meetsPreferentialThreshold: false, // Not applicable for EDP
                meetsMinimumThreshold: $data['meetsMinimum'],
                tieBreakerInfo: null,
                needsManualReview: $needsManualReview,
                contributingScores: [
                    'qualification_score' => $data['qualificationScore'],
                    'category' => $data['category'],
                ],
            ));

            $lastScore = $data['qualificationScore'];
        }

        return $finalRankedList;
    }

    /**
     * Determine minimum qualification score based on apparatus and gender
     */
    private function getMinQualificationScore(string $apparatus, string $division, array $config): float
    {
        // Get gender from division
        $gender = $division === 'male' ? 'male' : 'female';

        // Default minimums if not specified in config
        return match (strtolower($apparatus)) {
            'trampoline' => $config['rules']['min_qualification_scores']['trampoline'] ?? 81.5,
            'tumbling' => $config['rules']['min_qualification_scores']['tumbling'] ?? 40.4,
            'double-mini' => match ($gender) {
                'male' => $config['rules']['min_qualification_scores']['double-mini']['male'] ?? 45.4,
                default => $config['rules']['min_qualification_scores']['double-mini']['female'] ?? 44.8,
            },
            default => 0.0, // No minimum by default
        };
    }

    /**
     * Determine if athlete's age makes them eligible for EDP
     */
    private function isAgeEligible(int $age, string $level, array $config): bool
    {
        // For EDP, we need athletes in specific divisions/age groups
        return match ($level) {
            'youth_elite' => ($age >= 11 && $age <= 14),
            'level_10' => ($age >= 11 && $age <= 14),
            default => false
        };
    }

    /**
     * Determine the age group of an athlete
     */
    private function determineAgeGroup(int $age): string
    {
        if ($age >= 13 && $age <= 14) {
            return '13_14';
        } elseif ($age >= 11 && $age <= 12) {
            return '11_12';
        } else {
            return 'other';
        }
    }

    /**
     * Determine the specific category for an athlete based on level and age
     */
    private function determineCategory(string $level, int $age): string
    {
        if ($level === 'youth_elite') {
            if ($age >= 13 && $age <= 14) {
                return 'youth_elite_13_14';
            } elseif ($age >= 11 && $age <= 12) {
                return 'youth_elite_11_12';
            }
        } elseif ($level === 'level_10') {
            if ($age >= 13 && $age <= 14) {
                return 'level_10_13_14';
            } elseif ($age >= 11 && $age <= 12) {
                return 'level_10_11_12';
            }
        }

        return 'other';
    }

    /**
     * Compare two floating point numbers with an epsilon to account for floating point precision issues
     */
    private function floatCompare(float $a, float $b, float $epsilon = 0.00001): bool
    {
        return abs($a - $b) < $epsilon;
    }
}
