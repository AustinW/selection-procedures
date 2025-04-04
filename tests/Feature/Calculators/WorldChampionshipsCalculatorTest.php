<?php

use AustinW\SelectionProcedures\Contracts\AthleteContract;
use AustinW\SelectionProcedures\Contracts\ResultContract;
use AustinW\SelectionProcedures\Dto\RankedAthlete;
use AustinW\SelectionProcedures\RankingService;
use AustinW\SelectionProcedures\Tests\Mocks\MockAthlete;
use AustinW\SelectionProcedures\Tests\Mocks\MockResult;


// Helper function to create results easily
function createResult(AthleteContract $athlete, string $event, float $qual, ?float $final = null, string $apparatus = 'trampoline', string $division = 'senior_elite'): ResultContract
{
    return new MockResult($athlete, $event, $apparatus, $division, $qual, $final);
}

it('correctly ranks athletes for world championships trampoline senior elite men', function () {
    // --- Test Data Setup ---
    $procedureKey = '2025_world_championships';
    $apparatus = 'trampoline';
    $division = 'senior_elite';

    // Athletes (DOB ensures they are >= 17 in 2025)
    $athlete1 = new MockAthlete(1, '2000-01-01', 'male'); // Eligible
    $athlete2 = new MockAthlete(2, '2001-05-10', 'male'); // Eligible
    $athlete3 = new MockAthlete(3, '2002-11-15', 'male'); // Eligible
    $athlete4 = new MockAthlete(4, '1999-03-20', 'male'); // Eligible
    $athlete5 = new MockAthlete(5, '2010-01-01', 'male'); // Ineligible (Too young)
    $athlete6 = new MockAthlete(6, '1998-07-07', 'male'); // Ineligible (Only 1 event)

    // Results (Using config: top 2 qual + top 1 final, min 2 events)
    // Thresholds: Pref=57.500, Min=54.500
    $results = collect([
        // Athlete 1 (Eligible, High Score, Meets Pref)
        // Quals: 58.1, 57.6, 57.0 | Finals: 59.0, 58.5
        // Top 2 Qual: 58.1 + 57.6 = 115.7 | Top 1 Final: 59.0
        // Combined: 115.7 + 59.0 = 174.7
        createResult($athlete1, '2025_winter_classic', 58.1, 59.0),
        createResult($athlete1, '2025_elite_challenge', 57.6, 58.5),
        createResult($athlete1, '2025_usag_champs', 57.0, null), // No final score here

        // Athlete 2 (Eligible, Mid Score, Meets Min, Tie on Highest Qual)
        // Quals: 58.1, 55.0 | Finals: 56.0
        // Top 2 Qual: 58.1 + 55.0 = 113.1 | Top 1 Final: 56.0
        // Combined: 113.1 + 56.0 = 169.1
        createResult($athlete2, '2025_winter_classic', 58.1, 56.0), // Highest Qual 58.1
        createResult($athlete2, '2025_elite_challenge', 55.0, null),

        // Athlete 3 (Eligible, Mid Score, Meets Min, Tie on Highest Qual)
        // Quals: 57.9, 55.2 | Finals: 56.0
        // Top 2 Qual: 57.9 + 55.2 = 113.1 | Top 1 Final: 56.0
        // Combined: 113.1 + 56.0 = 169.1
        createResult($athlete3, '2025_winter_classic', 57.9, null), // Highest Qual 57.9
        createResult($athlete3, '2025_usag_champs', 55.2, 56.0),

        // Athlete 4 (Eligible, Lower Score, Doesn't Meet Min)
        // Quals: 53.0, 52.5 | Finals: 54.0
        // Top 2 Qual: 53.0 + 52.5 = 105.5 | Top 1 Final: 54.0
        // Combined: 105.5 + 54.0 = 159.5
        createResult($athlete4, '2025_elite_challenge', 53.0, 54.0),
        createResult($athlete4, '2025_usag_champs', 52.5, null),

        // Athlete 5 (Ineligible - Age)
        createResult($athlete5, '2025_winter_classic', 50.0, 50.0),
        createResult($athlete5, '2025_elite_challenge', 51.0, 51.0),

        // Athlete 6 (Ineligible - Events)
        createResult($athlete6, '2025_winter_classic', 60.0, 60.0),

        // Irrelevant result (wrong division)
        createResult($athlete1, '2025_winter_classic', 90.0, 90.0, $apparatus, 'junior'),
         // Irrelevant result (wrong apparatus)
        createResult($athlete1, '2025_winter_classic', 70.0, 70.0, 'tumbling', $division),
    ]);

    // --- Service Invocation ---
    $rankingService = new RankingService();
    $rankedList = $rankingService->rank($procedureKey, $apparatus, $division, $results);

    // --- Assertions ---
    expect($rankedList)->toBeInstanceOf(\Illuminate\Support\Collection::class);
    expect($rankedList)->toHaveCount(4); // Only 4 eligible athletes

    // Athlete 1 - Rank 1
    /** @var RankedAthlete $rank1 */
    $rank1 = $rankedList->firstWhere('athlete', $athlete1);
    expect($rank1)->not->toBeNull()
        ->rank->toBe(1)
        ->combinedScore->toBe(174.7)
        ->meetsPreferentialThreshold->toBeTrue()
        ->meetsMinimumThreshold->toBeTrue() // Since Pref > Min
        ->needsManualReview->toBeFalse();
    expect($rank1->contributingScores['qualification'])->toEqual([58.1, 57.6]);
    expect($rank1->contributingScores['final'])->toEqual([59.0]);

    // Athlete 2 - Rank 2 (Tie-breaker: Higher Highest Qual)
    /** @var RankedAthlete $rank2 */
    $rank2 = $rankedList->firstWhere('athlete', $athlete2);
     expect($rank2)->not->toBeNull()
        ->rank->toBe(2)
        ->combinedScore->toBe(169.1)
        ->meetsPreferentialThreshold->toBeTrue()
        ->meetsMinimumThreshold->toBeTrue()
        ->needsManualReview->toBeFalse(); // No tie with next rank
    expect($rank2->contributingScores['qualification'])->toEqual([58.1, 55.0]);
    expect($rank2->contributingScores['final'])->toEqual([56.0]);

    // Athlete 3 - Rank 3
    /** @var RankedAthlete $rank3 */
    $rank3 = $rankedList->firstWhere('athlete', $athlete3);
    expect($rank3)->not->toBeNull()
        ->rank->toBe(3)
        ->combinedScore->toBe(169.1)
        ->meetsPreferentialThreshold->toBeTrue()
        ->meetsMinimumThreshold->toBeTrue()
        ->needsManualReview->toBeFalse();
    expect($rank3->contributingScores['qualification'])->toEqual([57.9, 55.2]);
    expect($rank3->contributingScores['final'])->toEqual([56.0]);

    // Athlete 4 - Rank 4
    /** @var RankedAthlete $rank4 */
    $rank4 = $rankedList->firstWhere('athlete', $athlete4);
    expect($rank4)->not->toBeNull()
        ->rank->toBe(4)
        ->combinedScore->toBe(159.5)
        ->meetsPreferentialThreshold->toBeTrue()
        ->meetsMinimumThreshold->toBeFalse()
        ->needsManualReview->toBeFalse();
});

it('handles ties requiring manual review for world championships', function () {
    $procedureKey = '2025_world_championships';
    $apparatus = 'trampoline';
    $division = 'senior_elite';

    $athlete1 = new MockAthlete(1, '2000-01-01', 'male');
    $athlete2 = new MockAthlete(2, '2001-05-10', 'male');

    // Scores designed to tie exactly on Combined Score AND Highest Qual Score
    $results = collect([
        // Athlete 1: Quals: 58.0, 57.0 | Final: 55.0 => Combined: 115.0 + 55.0 = 170.0, Highest Qual = 58.0
        createResult($athlete1, 'event1', 58.0, 55.0),
        createResult($athlete1, 'event2', 57.0, null),
        // Athlete 2: Quals: 58.0, 57.0 | Final: 55.0 => Combined: 115.0 + 55.0 = 170.0, Highest Qual = 58.0
        createResult($athlete2, 'event1', 57.0, null),
        createResult($athlete2, 'event2', 58.0, 55.0),
    ]);

    // Instantiate directly instead of using app()
    $rankingService = new RankingService();
    $rankedList = $rankingService->rank($procedureKey, $apparatus, $division, $results);

    expect($rankedList)->toHaveCount(2);

    /** @var RankedAthlete $rank1 */
    $rank1 = $rankedList->firstWhere('athlete', $athlete1);
    /** @var RankedAthlete $rank2 */
    $rank2 = $rankedList->firstWhere('athlete', $athlete2);

    expect($rank1)->rank->toBe(1);
    expect($rank1)->needsManualReview->toBeTrue();

    expect($rank2)->rank->toBe(1); // Same rank in a tie
    expect($rank2)->needsManualReview->toBeTrue();
});

it('correctly ranks athletes for world championships trampoline senior elite women', function () {
    // --- Test Data Setup ---
    $procedureKey = '2025_world_championships';
    $apparatus = 'trampoline';
    $division = 'senior_elite';

    // Athletes
    $athlete1 = new MockAthlete(10, '2000-02-02', 'female'); // Eligible, High score
    $athlete2 = new MockAthlete(11, '2001-06-11', 'female'); // Eligible, Mid score
    $athlete3 = new MockAthlete(12, '2003-01-01', 'female'); // Eligible, Low score

    // Thresholds: Pref=52.500, Min=50.500
    $results = collect([
        // Athlete 1 (Eligible, Meets Pref)
        // Quals: 53.0, 52.6 | Finals: 54.0
        // Top 2 Qual: 53.0 + 52.6 = 105.6 | Top 1 Final: 54.0
        // Combined: 105.6 + 54.0 = 159.6
        createResult($athlete1, 'event1', 53.0, 54.0),
        createResult($athlete1, 'event2', 52.6, null),

        // Athlete 2 (Eligible, Meets Min only via Combined Score)
        // Quals: 51.0, 50.4 | Finals: 51.5
        // Top 2 Qual: 51.0 + 50.4 = 101.4 | Top 1 Final: 51.5
        // Combined: 101.4 + 51.5 = 152.9
        createResult($athlete2, 'event1', 51.0, 51.5),
        createResult($athlete2, 'event2', 50.4, null), // Highest score 51.5 < Pref 52.5, but Combined 152.9 >= Pref 52.5

        // Athlete 3 (Eligible, Does not meet Min)
        // Quals: 50.0, 49.5 | Finals: 50.1
        // Top 2 Qual: 50.0 + 49.5 = 99.5 | Top 1 Final: 50.1
        // Combined: 99.5 + 50.1 = 149.6
        createResult($athlete3, 'event1', 50.0, 50.1), // Highest score 50.1 < Min 50.5
        createResult($athlete3, 'event2', 49.5, null),
    ]);

    $rankingService = new RankingService();
    $rankedList = $rankingService->rank($procedureKey, $apparatus, $division, $results);

    expect($rankedList)->toHaveCount(3);

    // Athlete 1 - Rank 1
    $rank1 = $rankedList->firstWhere('athlete', $athlete1);
    expect($rank1)->rank->toBe(1)
        ->combinedScore->toBe(159.6)
        ->meetsPreferentialThreshold->toBeTrue()
        ->meetsMinimumThreshold->toBeTrue();

    // Athlete 2 - Rank 2
    $rank2 = $rankedList->firstWhere('athlete', $athlete2);
    expect($rank2)->rank->toBe(2)
        ->combinedScore->toBe(152.9)
        ->meetsPreferentialThreshold->toBeTrue() // Combined score meets preferential
        ->meetsMinimumThreshold->toBeTrue();

    // Athlete 3 - Rank 3
    $rank3 = $rankedList->firstWhere('athlete', $athlete3);
    expect($rank3)->rank->toBe(3)
        ->combinedScore->toBe(149.6)
        ->meetsPreferentialThreshold->toBeTrue() // Corrected assertion: 149.6 >= 52.5
        ->meetsMinimumThreshold->toBeFalse(); // No single score >= 50.5
});

it('ranks athlete with no final scores', function () {
    $procedureKey = '2025_world_championships';
    $apparatus = 'trampoline';
    $division = 'senior_elite';

    $athlete1 = new MockAthlete(20, '2000-03-03', 'male');

    // Results (Thresholds: Pref=57.500, Min=54.500)
    $results = collect([
        // Athlete 1 (Eligible, Meets Pref via Qual only)
        // Quals: 58.0, 57.6 | Finals: null
        // Top 2 Qual: 58.0 + 57.6 = 115.6 | Top 1 Final: 0
        // Combined: 115.6 + 0 = 115.6
        createResult($athlete1, 'event1', 58.0, null),
        createResult($athlete1, 'event2', 57.6, null),
    ]);

    $rankingService = new RankingService();
    $rankedList = $rankingService->rank($procedureKey, $apparatus, $division, $results);

    expect($rankedList)->toHaveCount(1);
    $rank1 = $rankedList->first();
    expect($rank1)->rank->toBe(1)
        ->combinedScore->toBe(115.6)
        ->meetsPreferentialThreshold->toBeTrue()
        ->meetsMinimumThreshold->toBeTrue();
    expect($rank1->contributingScores['final'])->toBeEmpty();
});

it('correctly identifies minimum threshold met when preferential is not', function () {
    $procedureKey = '2025_world_championships';
    $apparatus = 'trampoline';
    $division = 'senior_elite';

    $athlete1 = new MockAthlete(30, '2000-04-04', 'male');

    // Results (Thresholds: Pref=57.500, Min=54.500)
    $results = collect([
        // Athlete 1 (Eligible, Combined < Pref, but one score >= Min)
        // Quals: 55.0, 53.0 | Finals: 54.0
        // Top 2 Qual: 55.0 + 53.0 = 108.0 | Top 1 Final: 54.0
        // Combined: 108.0 + 54.0 = 162.0
        createResult($athlete1, 'event1', 55.0, null), // Meets Min
        createResult($athlete1, 'event2', 53.0, 54.0), // Doesn't meet min
    ]);

    $rankingService = new RankingService();
    $rankedList = $rankingService->rank($procedureKey, $apparatus, $division, $results);

    expect($rankedList)->toHaveCount(1);
    $rank1 = $rankedList->first();
    expect($rank1)->rank->toBe(1)
        ->combinedScore->toBe(162.0)
        ->meetsPreferentialThreshold->toBeTrue() // Combined score meets pref
        ->meetsMinimumThreshold->toBeTrue(); // Because one score (55.0) met min
});

it('handles complex tie requiring manual review', function () {
    $procedureKey = '2025_world_championships';
    $apparatus = 'trampoline';
    $division = 'senior_elite';

    $athleteA = new MockAthlete(100, '2000-01-01', 'male');
    $athleteB = new MockAthlete(101, '2001-01-01', 'male');
    $athleteC = new MockAthlete(102, '2002-01-01', 'male');

    $results = collect([
        // Athlete A: Quals 58.0, 57.0 / Final 56.0 -> Combined 171.0 (Highest Qual 58.0)
        createResult($athleteA, 'event1', 58.0, 56.0),
        createResult($athleteA, 'event2', 57.0, null),

        // Athlete B: Quals 58.0, 56.0 / Final 57.0 -> Combined 171.0 (Highest Qual 58.0)
        createResult($athleteB, 'event1', 56.0, 57.0),
        createResult($athleteB, 'event2', 58.0, null),

        // Athlete C: Quals 57.9, 57.1 / Final 56.0 -> Combined 171.0 (Highest Qual 57.9)
        createResult($athleteC, 'event1', 57.9, 56.0),
        createResult($athleteC, 'event2', 57.1, null),
    ]);

    $rankingService = new RankingService();
    $rankedList = $rankingService->rank($procedureKey, $apparatus, $division, $results);

    expect($rankedList)->toHaveCount(3);

    $rankA = $rankedList->firstWhere('athlete', $athleteA);
    $rankB = $rankedList->firstWhere('athlete', $athleteB);
    $rankC = $rankedList->firstWhere('athlete', $athleteC);

    // A and B tie for Rank 1, requiring manual review
    expect($rankA)->rank->toBe(1);
    expect($rankA)->combinedScore->toBe(171.0);
    expect($rankA)->needsManualReview->toBeTrue();

    expect($rankB)->rank->toBe(1); // Tied rank
    expect($rankB)->combinedScore->toBe(171.0);
    expect($rankB)->needsManualReview->toBeTrue();

    // C has same combined score but lower highest qual, so ranks 3rd
    expect($rankC)->rank->toBe(3);
    expect($rankC)->combinedScore->toBe(171.0);
    expect($rankC)->needsManualReview->toBeFalse();
});

it('correctly ranks athletes for world championships tumbling senior elite men', function () {
    $procedureKey = '2025_world_championships';
    $apparatus = 'tumbling';
    $division = 'senior_elite';

    // Athletes
    $athlete1 = new MockAthlete(401, '2000-01-01', 'male'); // Eligible, High Score
    $athlete2 = new MockAthlete(402, '2001-05-10', 'male'); // Eligible, Meets only Min threshold
    $athlete3 = new MockAthlete(403, '2002-11-15', 'male'); // Eligible, Meets neither threshold

    // Results (Top 2 Qual + Top 1 Final)
    // Thresholds (TUM Men): Pref=50.100, Min=48.200
    $results = collect([
        // Athlete 1 (Meets Pref)
        // Quals: 50.5, 50.0 | Finals: 49.0
        // Combined: (50.5 + 50.0) + 49.0 = 149.5
        createResult($athlete1, 'event1', 50.5, 49.0, $apparatus, $division),
        createResult($athlete1, 'event2', 50.0, null, $apparatus, $division),

        // Athlete 2 (Meets Min only - Qual 48.5 >= 48.2)
        // Quals: 48.5, 48.0 | Finals: 47.0
        // Combined: (48.5 + 48.0) + 47.0 = 143.5
        createResult($athlete2, 'event1', 48.5, 47.0, $apparatus, $division),
        createResult($athlete2, 'event2', 48.0, null, $apparatus, $division),

        // Athlete 3 (Meets Neither)
        // Quals: 47.0, 46.5 | Finals: 48.0
        // Combined: (47.0 + 46.5) + 48.0 = 141.5
        createResult($athlete3, 'event1', 47.0, 48.0, $apparatus, $division),
        createResult($athlete3, 'event2', 46.5, null, $apparatus, $division),
    ]);

    $rankingService = new RankingService();
    $rankedList = $rankingService->rank($procedureKey, $apparatus, $division, $results);

    expect($rankedList)->toHaveCount(3);

    // Athlete 1
    $rank1 = $rankedList->firstWhere('athlete', $athlete1);
    expect($rank1)->rank->toBe(1)
        ->combinedScore->toBe(149.5)
        ->meetsPreferentialThreshold->toBeTrue()
        ->meetsMinimumThreshold->toBeTrue();

    // Athlete 2
    $rank2 = $rankedList->firstWhere('athlete', $athlete2);
    expect($rank2)->rank->toBe(2)
        ->combinedScore->toBe(143.5)
        ->meetsPreferentialThreshold->toBeTrue() // Corrected: 143.5 >= 50.1
        ->meetsMinimumThreshold->toBeTrue(); // Met via 48.5 Qual score

    // Athlete 3
    $rank3 = $rankedList->firstWhere('athlete', $athlete3);
    expect($rank3)->rank->toBe(3)
        ->combinedScore->toBe(141.5)
        ->meetsPreferentialThreshold->toBeTrue() // Corrected: 141.5 >= 50.1
        ->meetsMinimumThreshold->toBeFalse(); // Highest score 48.0 < Min 48.2
});

it('correctly ranks athletes for world championships double-mini senior elite women', function () {
    $procedureKey = '2025_world_championships';
    $apparatus = 'double-mini';
    $division = 'senior_elite';

    // Athletes
    $athlete1 = new MockAthlete(501, '2000-01-01', 'female'); // Eligible, High Score
    $athlete2 = new MockAthlete(502, '2001-05-10', 'female'); // Eligible, Meets only Min threshold
    $athlete3 = new MockAthlete(503, '2002-11-15', 'female'); // Eligible, Meets only Min via Final
    $athlete4 = new MockAthlete(504, '1999-03-20', 'female'); // Eligible, Meets neither

    // Results (Top 2 Qual + Top 1 Final)
    // Thresholds (DM Women): Pref=50.300, Min=49.300
    $results = collect([
        // Athlete 1 (Meets Pref)
        // Quals: 50.5, 50.4 | Finals: 51.0
        // Combined: (50.5 + 50.4) + 51.0 = 151.9
        createResult($athlete1, 'event1', 50.5, 51.0, $apparatus, $division),
        createResult($athlete1, 'event2', 50.4, null, $apparatus, $division),

        // Athlete 2 (Meets Min only - Qual 49.5 >= 49.3)
        // Quals: 49.5, 49.0 | Finals: 48.0
        // Combined: (49.5 + 49.0) + 48.0 = 146.5
        createResult($athlete2, 'event1', 49.5, 48.0, $apparatus, $division),
        createResult($athlete2, 'event2', 49.0, null, $apparatus, $division),

        // Athlete 3 (Meets Min only - Final 49.8 >= 49.3)
        // Quals: 48.8, 48.5 | Finals: 49.8
        // Combined: (48.8 + 48.5) + 49.8 = 147.1
        createResult($athlete3, 'event1', 48.8, null, $apparatus, $division),
        createResult($athlete3, 'event2', 48.5, 49.8, $apparatus, $division),

         // Athlete 4 (Meets Neither)
        // Quals: 49.0, 48.0 | Finals: 49.1
        // Combined: (49.0 + 48.0) + 49.1 = 146.1
        createResult($athlete4, 'event1', 49.0, 49.1, $apparatus, $division),
        createResult($athlete4, 'event2', 48.0, null, $apparatus, $division),
    ]);

    $rankingService = new RankingService();
    $rankedList = $rankingService->rank($procedureKey, $apparatus, $division, $results);

    expect($rankedList)->toHaveCount(4);

    // Athlete 1 (Rank 1)
    $rank1 = $rankedList->firstWhere('athlete', $athlete1);
    expect($rank1)->rank->toBe(1)
        ->combinedScore->toBe(151.9)
        ->meetsPreferentialThreshold->toBeTrue()
        ->meetsMinimumThreshold->toBeTrue();

    // Athlete 3 (Rank 2 - higher combined score)
    $rank3 = $rankedList->firstWhere('athlete', $athlete3);
    expect($rank3)->rank->toBe(2)
        ->combinedScore->toBe(147.1)
        ->meetsPreferentialThreshold->toBeTrue() // Corrected: 147.1 >= 50.3
        ->meetsMinimumThreshold->toBeTrue(); // Met via 49.8 Final score

    // Athlete 2 (Rank 3)
    $rank2 = $rankedList->firstWhere('athlete', $athlete2);
    expect($rank2)->rank->toBe(3)
        ->combinedScore->toBe(146.5)
        ->meetsPreferentialThreshold->toBeTrue() // Corrected: 146.5 >= 50.3
        ->meetsMinimumThreshold->toBeTrue(); // Met via 49.5 Qual score

    // Athlete 4 (Rank 4)
    $rank4 = $rankedList->firstWhere('athlete', $athlete4);
    expect($rank4)->rank->toBe(4)
        ->combinedScore->toBe(146.1)
        ->meetsPreferentialThreshold->toBeTrue() // Corrected: 146.1 >= 50.3
        ->meetsMinimumThreshold->toBeFalse(); // Highest score 49.1 < Min 49.3
}); 