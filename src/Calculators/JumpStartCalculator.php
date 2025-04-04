<?php

namespace AustinW\SelectionProcedures\Calculators;

use AustinW\SelectionProcedures\Contracts\AthleteContract;
use AustinW\SelectionProcedures\Contracts\ProcedureCalculatorContract;
use AustinW\SelectionProcedures\Contracts\ResultContract;
use AustinW\SelectionProcedures\Dto\RankedAthlete;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

class JumpStartCalculator implements ProcedureCalculatorContract
{
    /**
     * Calculate the ranking for the JumpStart team procedure.
     * Selection is based on qualification scores and JumpStart testing scores
     * with prioritized selection across specific age/level divisions.
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
        
        // Maximum athletes per team varies by discipline
        $maxTeamSize = match (strtolower($apparatus)) {
            'trampoline' => $config['rules']['team_size']['trampoline'] ?? 16,
            'tumbling' => $config['rules']['team_size']['tumbling'] ?? 12,
            'double-mini' => $config['rules']['team_size']['double-mini'] ?? 12,
            default => 12, // Default fallback
        };
        
        // 1. Filter results by apparatus and division
        $filteredResults = $results->filter(fn (ResultContract $result) =>
            strtolower($result->getApparatus()) === strtolower($apparatus)
            && $result->getDivision() === $division
        );

        // 2. Group results by athlete ID
        $resultsByAthlete = $filteredResults->groupBy(fn (ResultContract $result) => $result->getAthlete()->getId());

        // 3. Collect all athletes and calculate their best scores
        $athleteCalculations = new Collection();
        
        foreach ($resultsByAthlete as $athleteId => $athleteResults) {
            /** @var AthleteContract $athlete */
            $athlete = $athleteResults->first()->getAthlete();
            
            // Check age eligibility
            try {
                $birthDate = CarbonImmutable::parse($athlete->getDateOfBirth());
                $age = $procedureYear - $birthDate->year;
                
                // Skip if not age eligible based on division rules
                if (!$this->isAgeEligible($age, $config)) {
                    continue;
                }
            } catch (\Throwable $e) {
                error_log("Error processing age eligibility for athlete ID " . ($athleteId ?? 'unknown') . ": " . $e->getMessage());
                continue; // Skip athletes with invalid date of birth
            }
            
            // Group results by event
            $resultsByEvent = $athleteResults->groupBy(fn (ResultContract $r) => $r->getEventIdentifier());
            
            // Calculate best qualification score and best JumpStart testing score
            $bestQualificationScore = 0;
            $bestJumpStartScore = 0;
            
            foreach ($resultsByEvent as $eventId => $eventResults) {
                // For regular qualification events
                if (strpos(strtolower($eventId), 'jumpstart') === false) {
                    $bestScore = $eventResults->max(fn (ResultContract $r) => $r->getQualificationScore());
                    $bestQualificationScore = max($bestQualificationScore, $bestScore);
                } 
                // For JumpStart testing events
                else {
                    $bestScore = $eventResults->max(fn (ResultContract $r) => $r->getQualificationScore());
                    $bestJumpStartScore = max($bestJumpStartScore, $bestScore);
                }
            }
            
            // Get athlete level and determine category
            $level = $this->getAthleteLevel($athlete); // Note: Need to implement this function
            $ageGroup = $this->determineAgeGroup($age);
            $category = $this->determineCategory($level, $ageGroup);
            
            // Store calculation including division/level info
            $athleteCalculations->push([
                'athlete' => $athlete,
                'id' => $athleteId,
                'qualificationScore' => $bestQualificationScore,
                'jumpStartScore' => $bestJumpStartScore,
                'level' => $level,
                'age' => $age,
                'ageGroup' => $ageGroup,
                'category' => $category,
                'gender' => $athlete->getGender(),
            ]);
        }
        
        // 4. Define collections for each category
        $categories = [
            'level_10_11_12' => $athleteCalculations
                ->filter(fn ($data) => $data['category'] === 'level_10_11_12')
                ->sortByDesc('qualificationScore')
                ->values(),
                
            'level_10_10u' => $athleteCalculations
                ->filter(fn ($data) => $data['category'] === 'level_10_10u')
                ->sortByDesc('qualificationScore')
                ->values(),
                
            'level_10_all' => $athleteCalculations
                ->filter(fn ($data) => in_array($data['category'], ['level_10_11_12', 'level_10_10u']))
                ->sortByDesc('jumpStartScore')
                ->values(),
                
            'level_9_11_12' => $athleteCalculations
                ->filter(fn ($data) => $data['category'] === 'level_9_11_12')
                ->sortByDesc('qualificationScore')
                ->values(),
                
            'level_9_9_10' => $athleteCalculations
                ->filter(fn ($data) => $data['category'] === 'level_9_9_10')
                ->sortByDesc('qualificationScore')
                ->values(),
                
            'level_9_all' => $athleteCalculations
                ->filter(fn ($data) => in_array($data['category'], ['level_9_11_12', 'level_9_9_10']))
                ->sortByDesc('jumpStartScore')
                ->values(),
                
            'level_8_11_12' => $athleteCalculations
                ->filter(fn ($data) => $data['category'] === 'level_8_11_12')
                ->sortByDesc('qualificationScore')
                ->values(),
                
            'level_8_9_10' => $athleteCalculations
                ->filter(fn ($data) => $data['category'] === 'level_8_9_10')
                ->sortByDesc('qualificationScore')
                ->values(),
                
            'level_8_all' => $athleteCalculations
                ->filter(fn ($data) => in_array($data['category'], ['level_8_11_12', 'level_8_9_10']))
                ->sortByDesc('jumpStartScore')
                ->values(),
        ];
        
        // 5. Apply selection criteria based on discipline rules
        
        // Track selected athlete IDs to avoid duplicates
        $selectedAthleteIds = [];
        $rankedAthletes = new Collection();
        
        // Selection criteria differs slightly based on discipline
        $selectionPlan = $this->getSelectionPlan($apparatus);
        
        foreach ($selectionPlan as $step) {
            $category = $step['category'];
            $count = $step['count'];
            $scoreType = $step['scoreType'];
            
            $athletesInCategory = $categories[$category] ?? collect();
            
            // Filter out already-selected athletes
            $eligibleAthletes = $athletesInCategory
                ->filter(fn ($data) => !in_array($data['id'], $selectedAthleteIds))
                ->values();
                
            // Select top N based on the appropriate score type
            if ($scoreType === 'qualification') {
                $eligibleAthletes = $eligibleAthletes->sortByDesc('qualificationScore')->values();
            } else {
                $eligibleAthletes = $eligibleAthletes->sortByDesc('jumpStartScore')->values();
            }
            
            // Take the specified number of athletes
            $selectedFromCategory = $eligibleAthletes->take($count);
            
            // Add to selected list
            foreach ($selectedFromCategory as $data) {
                $rankedAthletes->push($data);
                $selectedAthleteIds[] = $data['id'];
            }
        }
        
        // If not enough athletes selected, fill with remaining top scorers
        $moreNeeded = $maxTeamSize - count($selectedAthleteIds);
        
        if ($moreNeeded > 0) {
            // Rank all remaining athletes by qualification score
            $remainingAthletes = $athleteCalculations
                ->filter(fn ($data) => !in_array($data['id'], $selectedAthleteIds))
                ->sortByDesc('qualificationScore')
                ->take($moreNeeded)
                ->values();
                
            foreach ($remainingAthletes as $data) {
                $rankedAthletes->push($data);
                $selectedAthleteIds[] = $data['id'];
            }
        }
        
        // 6. Rank the final list based on qualification score
        $rankedAthletes = $rankedAthletes->sortByDesc('qualificationScore')->values();
        
        // 7. Create RankedAthlete DTOs
        $finalRankedList = new Collection();
        $currentRank = 1;
        $lastScore = -1.0;
        
        foreach ($rankedAthletes as $index => $data) {
            // Determine rank - increment only if score is lower than previous
            if ($index > 0 && !$this->floatCompare($data['qualificationScore'], $lastScore)) {
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
                meetsPreferentialThreshold: false, // Not applicable for JumpStart
                meetsMinimumThreshold: true, // No minimum thresholds defined for JumpStart
                tieBreakerInfo: null,
                needsManualReview: $needsManualReview,
                contributingScores: [
                    'qualification_score' => $data['qualificationScore'],
                    'jumpstart_score' => $data['jumpStartScore'],
                    'category' => $data['category']
                ],
            ));
            
            $lastScore = $data['qualificationScore'];
        }
        
        return $finalRankedList;
    }
    
    /**
     * Get the athlete's level (e.g., level_10, level_9, etc.)
     * Note: Adapt this based on how athlete levels are stored in your system
     */
    private function getAthleteLevel(AthleteContract $athlete): string
    {
        // In a real implementation, this would get the level from the athlete object
        // For now, assuming it's available directly
        return $athlete->getLevel() ?? 'unknown';
    }
    
    /**
     * Determine if athlete's age makes them eligible for JumpStart
     */
    private function isAgeEligible(int $age, array $config): bool
    {
        // For JumpStart, minimum age is 9 for 10U, 11 for other divisions
        return match (true) {
            $age >= 11 && $age <= 12 => true, // 11-12 divisions
            $age >= 9 && $age <= 10 => true,  // 9-10 and 10U divisions
            default => false
        };
    }
    
    /**
     * Determine the age group of an athlete for JumpStart
     */
    private function determineAgeGroup(int $age): string
    {
        if ($age >= 11 && $age <= 12) {
            return '11_12';
        } elseif ($age >= 9 && $age <= 10) {
            return '9_10';
        } elseif ($age <= 10) {
            return '10u';  // 10 and under
        } else {
            return 'other';
        }
    }
    
    /**
     * Determine the specific category for an athlete based on level and age
     */
    private function determineCategory(string $level, string $ageGroup): string
    {
        // For JumpStart specific categories
        return strtolower($level) . '_' . $ageGroup;
    }
    
    /**
     * Define the selection plan based on the apparatus
     */
    private function getSelectionPlan(string $apparatus): array
    {
        // The selection plan differs slightly between disciplines in terms of numbers selected
        if (strtolower($apparatus) === 'trampoline') {
            // For trampoline: 16 athletes total
            return [
                // Level 10 selections
                ['category' => 'level_10_11_12', 'count' => 2, 'scoreType' => 'qualification'],
                ['category' => 'level_10_10u', 'count' => 2, 'scoreType' => 'qualification'],
                ['category' => 'level_10_all', 'count' => 2, 'scoreType' => 'jumpstart'],
                
                // Level 9 selections
                ['category' => 'level_9_11_12', 'count' => 2, 'scoreType' => 'qualification'],
                ['category' => 'level_9_9_10', 'count' => 2, 'scoreType' => 'qualification'],
                ['category' => 'level_9_all', 'count' => 2, 'scoreType' => 'jumpstart'],
                
                // Level 8 selections
                ['category' => 'level_8_11_12', 'count' => 1, 'scoreType' => 'qualification'],
                ['category' => 'level_8_9_10', 'count' => 1, 'scoreType' => 'qualification'],
                ['category' => 'level_8_all', 'count' => 2, 'scoreType' => 'jumpstart'],
            ];
        } else {
            // For tumbling and double-mini: 12 athletes total
            return [
                // Level 10 selections
                ['category' => 'level_10_11_12', 'count' => 2, 'scoreType' => 'qualification'],
                ['category' => 'level_10_10u', 'count' => 2, 'scoreType' => 'qualification'],
                ['category' => 'level_10_all', 'count' => 2, 'scoreType' => 'jumpstart'],
                
                // Level 9 selections
                ['category' => 'level_9_11_12', 'count' => 1, 'scoreType' => 'qualification'],
                ['category' => 'level_9_9_10', 'count' => 1, 'scoreType' => 'qualification'],
                ['category' => 'level_9_all', 'count' => 1, 'scoreType' => 'jumpstart'],
                
                // Level 8 selections
                ['category' => 'level_8_11_12', 'count' => 1, 'scoreType' => 'qualification'],
                ['category' => 'level_8_9_10', 'count' => 1, 'scoreType' => 'qualification'],
                ['category' => 'level_8_all', 'count' => 1, 'scoreType' => 'jumpstart'],
            ];
        }
    }
    
    /**
     * Compare two floating point numbers with an epsilon to account for floating point precision issues
     */
    private function floatCompare(float $a, float $b, float $epsilon = 0.00001): bool
    {
        return abs($a - $b) < $epsilon;
    }
} 