<?php

use AustinW\SelectionProcedures\Contracts\AthleteContract;
use AustinW\SelectionProcedures\Contracts\ResultContract;
use AustinW\SelectionProcedures\Dto\RankedAthlete;
use AustinW\SelectionProcedures\RankingService;
use AustinW\SelectionProcedures\Tests\Mocks\MockAthlete;
use AustinW\SelectionProcedures\Tests\Mocks\MockResult;

// Helper function (consider moving to a shared test helper file later)
function createWagcResult(AthleteContract $athlete, string $event, float $qual, string $apparatus, string $division, string $level = 'youth_elite'): ResultContract
{
    // WAGC results don't use final scores for ranking
    return new MockResult($athlete, $event, $apparatus, $division, $qual, null, $level);
}

it('correctly ranks athletes for wagc 13-14 boys tumbling', function () {
    // --- Test Data Setup ---
    $procedureKey = '2025_wagc';
    $apparatus = 'tumbling';
    $division = '13-14';

    // Athletes (Born in 2011/2012 to be 13/14 in 2025)
    $athlete1 = new MockAthlete(201, '2011-01-01', 'male'); // Eligible, High score
    $athlete2 = new MockAthlete(202, '2012-05-10', 'male'); // Eligible, Mid score
    $athlete3 = new MockAthlete(203, '2011-11-15', 'male'); // Eligible, Low score
    $athlete4 = new MockAthlete(204, '2010-03-20', 'male'); // Ineligible (Too old - 15)
    $athlete5 = new MockAthlete(205, '2013-01-01', 'male'); // Ineligible (Too young - 12)

    // Results (Using config: top 2 qual scores)
    // Thresholds (13-14 Boys Tumbling): Pref=43.80, Min=42.10
    $results = collect([
        // Athlete 1 (Eligible, Meets Pref)
        // Quals: 44.0, 43.9, 43.5 -> Top 2: 44.0 + 43.9 = 87.9
        // Meets Pref (44.0 >= 43.80), Meets Min (44.0 >= 42.10)
        createWagcResult($athlete1, 'event1', 44.0, $apparatus, $division),
        createWagcResult($athlete1, 'event2', 43.9, $apparatus, $division),
        createWagcResult($athlete1, 'event3', 43.5, $apparatus, $division),

        // Athlete 2 (Eligible, Meets Min only)
        // Quals: 43.0, 42.5 -> Top 2: 43.0 + 42.5 = 85.5
        // No score >= Pref (43.80), Meets Min (43.0 >= 42.10)
        createWagcResult($athlete2, 'event1', 43.0, $apparatus, $division),
        createWagcResult($athlete2, 'event2', 42.5, $apparatus, $division),

        // Athlete 3 (Eligible, Does not meet Min)
        // Quals: 42.0, 41.5 -> Top 2: 42.0 + 41.5 = 83.5
        // No score >= Pref (43.80), No score >= Min (42.10)
        createWagcResult($athlete3, 'event1', 42.0, $apparatus, $division),
        createWagcResult($athlete3, 'event2', 41.5, $apparatus, $division),

        // Athlete 4 (Ineligible - Age)
        createWagcResult($athlete4, 'event1', 45.0, $apparatus, $division),
        createWagcResult($athlete4, 'event2', 45.0, $apparatus, $division),

        // Athlete 5 (Ineligible - Age)
        createWagcResult($athlete5, 'event1', 45.0, $apparatus, $division),
        createWagcResult($athlete5, 'event2', 45.0, $apparatus, $division),

        // Irrelevant result (wrong division)
        createWagcResult($athlete1, 'event1', 45.0, $apparatus, '17-21'),
        // Irrelevant result (wrong apparatus)
        createWagcResult($athlete1, 'event1', 55.0, 'trampoline', $division),
    ]);

    // --- Service Invocation ---
    $rankingService = new RankingService;
    $rankedList = $rankingService->rank($procedureKey, $apparatus, $division, $results);

    // --- Assertions ---
    expect($rankedList)->toBeInstanceOf(\Illuminate\Support\Collection::class);
    expect($rankedList)->toHaveCount(3); // Only 3 eligible athletes

    // Athlete 1 - Rank 1
    /** @var RankedAthlete $rank1 */
    $rank1 = $rankedList->firstWhere('athlete', $athlete1);
    expect($rank1)->not->toBeNull()
        ->rank->toBe(1)
        ->combinedScore->toBe(87.9)
        ->meetsPreferentialThreshold->toBeTrue()
        ->meetsMinimumThreshold->toBeTrue()
        ->needsManualReview->toBeFalse();
    expect($rank1->contributingScores['qualification'])->toEqual([44.0, 43.9]);
    expect($rank1->contributingScores['final'])->toBeEmpty();

    // Athlete 2 - Rank 2
    /** @var RankedAthlete $rank2 */
    $rank2 = $rankedList->firstWhere('athlete', $athlete2);
    expect($rank2)->not->toBeNull()
        ->rank->toBe(2)
        ->combinedScore->toBe(85.5)
        ->meetsPreferentialThreshold->toBeFalse()
        ->meetsMinimumThreshold->toBeTrue()
        ->needsManualReview->toBeFalse();
    expect($rank2->contributingScores['qualification'])->toEqual([43.0, 42.5]);

    // Athlete 3 - Rank 3
    /** @var RankedAthlete $rank3 */
    $rank3 = $rankedList->firstWhere('athlete', $athlete3);
    expect($rank3)->not->toBeNull()
        ->rank->toBe(3)
        ->combinedScore->toBe(83.5)
        ->meetsPreferentialThreshold->toBeFalse()
        ->meetsMinimumThreshold->toBeFalse()
        ->needsManualReview->toBeFalse();
    expect($rank3->contributingScores['qualification'])->toEqual([42.0, 41.5]);

});

it('correctly ranks athletes for wagc 17-21 women double-mini', function () {
    $procedureKey = '2025_wagc';
    $apparatus = 'double-mini';
    $division = '17-21';
    $requiredLevel = 'senior_elite'; // Explicitly define for clarity

    // Athletes (Born between 2004-2008 to be 17-21 in 2025)
    $athleteA = new MockAthlete(301, '2004-02-02', 'female'); // Eligible
    $athleteB = new MockAthlete(302, '2008-12-31', 'female'); // Eligible (just 17)
    $athleteC = new MockAthlete(303, '2003-01-01', 'female'); // Ineligible (Too old - 22)
    $athleteD = new MockAthlete(304, '2005-05-05', 'female'); // Eligible age, but wrong level

    // Results (Using config: top 2 qual scores)
    // Thresholds (17-21 Women DM): Pref=49.00, Min=47.00
    $results = collect([
        // Athlete A (Eligible, Meets Pref, Correct Level)
        createWagcResult($athleteA, 'event1', 49.5, $apparatus, $division, $requiredLevel),
        createWagcResult($athleteA, 'event2', 48.0, $apparatus, $division, $requiredLevel),
        createWagcResult($athleteA, 'event3', 47.5, $apparatus, $division, $requiredLevel),

        // Athlete B (Eligible, Meets Min only, Correct Level)
        createWagcResult($athleteB, 'event1', 47.2, $apparatus, $division, $requiredLevel),
        createWagcResult($athleteB, 'event2', 47.1, $apparatus, $division, $requiredLevel),

        // Athlete C (Ineligible - Age)
        createWagcResult($athleteC, 'event1', 50.0, $apparatus, $division, $requiredLevel),
        createWagcResult($athleteC, 'event2', 50.0, $apparatus, $division, $requiredLevel),

        // Athlete D (Ineligible - Level)
        createWagcResult($athleteD, 'event1', 50.0, $apparatus, $division, 'junior'), // Wrong level
        createWagcResult($athleteD, 'event2', 50.0, $apparatus, $division, 'junior'),
    ]);

    $rankingService = new RankingService;
    $rankedList = $rankingService->rank($procedureKey, $apparatus, $division, $results);

    expect($rankedList)->toHaveCount(2); // Only A and B are eligible

    // Athlete A - Rank 1
    $rankA = $rankedList->firstWhere('athlete', $athleteA);
    expect($rankA)->rank->toBe(1)
        ->combinedScore->toBe(97.5)
        ->meetsPreferentialThreshold->toBeTrue()
        ->meetsMinimumThreshold->toBeTrue();

    // Athlete B - Rank 2
    $rankB = $rankedList->firstWhere('athlete', $athleteB);
    expect($rankB)->rank->toBe(2);
    // Use tolerance check again for float comparison
    expect(abs($rankB->combinedScore - 94.3))->toBeLessThan(0.00001);
    expect($rankB)
        ->meetsPreferentialThreshold->toBeFalse()
        ->meetsMinimumThreshold->toBeTrue();

});
