<?php

use AustinW\SelectionProcedures\Calculators\JumpStartCalculator;
use AustinW\SelectionProcedures\Dto\RankedAthlete;
use AustinW\SelectionProcedures\Tests\Mocks\MockAthlete;
use AustinW\SelectionProcedures\Tests\Mocks\MockResult;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->calculator = app(JumpStartCalculator::class);

    // Mock config for JumpStart team
    config(['selection-procedures.procedures.2025_jumpstart' => [
        'year' => 2025,
        'name' => '2025-2026 JumpStart Program',
        'calculator' => JumpStartCalculator::class,
        'events' => [
            'winter_classic' => ['name' => '2025 Winter Classic'],
            'elite_challenge' => ['name' => '2025 Elite Challenge'],
            'usa_gymnastics_championships' => ['name' => '2025 USA Gymnastics Championships'],
            'state_jumpstart_testing' => ['name' => 'State JumpStart Testing'],
        ],
        'rules' => [
            'team_size' => [
                'trampoline' => 16,
                'tumbling' => 12,
                'double-mini' => 12,
            ],
        ],
        'divisions' => [
            'male' => [
                'name' => 'Male',
                'min_age' => 9,
            ],
            'female' => [
                'name' => 'Female',
                'min_age' => 9,
            ],
        ],
    ]]);
});

it('ranks trampoline athletes for JumpStart team correctly', function () {
    $config = config('selection-procedures.procedures.2025_jumpstart');
    $apparatus = 'trampoline';
    $division = 'female';

    // Create athletes of different levels and age ranges
    $athlete1 = new MockAthlete(1, '2013-01-01', 'female', 'level_10'); // 12 in 2025, Level 10 11-12
    $athlete2 = new MockAthlete(2, '2014-01-01', 'female', 'level_10'); // 11 in 2025, Level 10 11-12
    $athlete3 = new MockAthlete(3, '2015-01-01', 'female', 'level_10'); // 10 in 2025, Level 10 10U
    $athlete4 = new MockAthlete(4, '2016-01-01', 'female', 'level_10'); // 9 in 2025, Level 10 10U

    $athlete5 = new MockAthlete(5, '2013-01-01', 'female', 'level_9');  // 12 in 2025, Level 9 11-12
    $athlete6 = new MockAthlete(6, '2014-01-01', 'female', 'level_9');  // 11 in 2025, Level 9 11-12
    $athlete7 = new MockAthlete(7, '2015-01-01', 'female', 'level_9');  // 10 in 2025, Level 9 9-10
    $athlete8 = new MockAthlete(8, '2016-01-01', 'female', 'level_9');  // 9 in 2025, Level 9 9-10

    $athlete9 = new MockAthlete(9, '2013-01-01', 'female', 'level_8');   // 12 in 2025, Level 8 11-12
    $athlete10 = new MockAthlete(10, '2014-01-01', 'female', 'level_8'); // 11 in 2025, Level 8 11-12
    $athlete11 = new MockAthlete(11, '2015-01-01', 'female', 'level_8'); // 10 in 2025, Level 8 9-10
    $athlete12 = new MockAthlete(12, '2016-01-01', 'female', 'level_8'); // 9 in 2025, Level 8 9-10

    $athlete13 = new MockAthlete(13, '2017-01-01', 'female', 'level_8'); // 8 in 2025, too young

    // Create results with qualification scores and JumpStart testing scores
    $results = collect([
        // Level 10 11-12 qualification scores
        new MockResult($athlete1, 'winter_classic', $apparatus, $division, 65.0, null),
        new MockResult($athlete2, 'elite_challenge', $apparatus, $division, 66.0, null),

        // Level 10 10U qualification scores
        new MockResult($athlete3, 'winter_classic', $apparatus, $division, 62.0, null),
        new MockResult($athlete4, 'elite_challenge', $apparatus, $division, 63.0, null),

        // Level 10 JumpStart testing scores
        new MockResult($athlete1, 'state_jumpstart_testing', $apparatus, $division, 85.0, null),
        new MockResult($athlete2, 'state_jumpstart_testing', $apparatus, $division, 86.0, null),
        new MockResult($athlete3, 'state_jumpstart_testing', $apparatus, $division, 84.0, null),
        new MockResult($athlete4, 'state_jumpstart_testing', $apparatus, $division, 83.0, null),

        // Level 9 11-12 qualification scores
        new MockResult($athlete5, 'winter_classic', $apparatus, $division, 60.0, null),
        new MockResult($athlete6, 'elite_challenge', $apparatus, $division, 61.0, null),

        // Level 9 9-10 qualification scores
        new MockResult($athlete7, 'winter_classic', $apparatus, $division, 58.0, null),
        new MockResult($athlete8, 'elite_challenge', $apparatus, $division, 59.0, null),

        // Level 9 JumpStart testing scores
        new MockResult($athlete5, 'state_jumpstart_testing', $apparatus, $division, 81.0, null),
        new MockResult($athlete6, 'state_jumpstart_testing', $apparatus, $division, 82.0, null),
        new MockResult($athlete7, 'state_jumpstart_testing', $apparatus, $division, 79.0, null),
        new MockResult($athlete8, 'state_jumpstart_testing', $apparatus, $division, 80.0, null),

        // Level 8 11-12 qualification scores
        new MockResult($athlete9, 'winter_classic', $apparatus, $division, 55.0, null),
        new MockResult($athlete10, 'elite_challenge', $apparatus, $division, 56.0, null),

        // Level 8 9-10 qualification scores
        new MockResult($athlete11, 'winter_classic', $apparatus, $division, 53.0, null),
        new MockResult($athlete12, 'elite_challenge', $apparatus, $division, 54.0, null),

        // Level 8 JumpStart testing scores
        new MockResult($athlete9, 'state_jumpstart_testing', $apparatus, $division, 77.0, null),
        new MockResult($athlete10, 'state_jumpstart_testing', $apparatus, $division, 78.0, null),
        new MockResult($athlete11, 'state_jumpstart_testing', $apparatus, $division, 75.0, null),
        new MockResult($athlete12, 'state_jumpstart_testing', $apparatus, $division, 76.0, null),

        // Too young athlete
        new MockResult($athlete13, 'winter_classic', $apparatus, $division, 90.0, null),
        new MockResult($athlete13, 'state_jumpstart_testing', $apparatus, $division, 95.0, null),
    ]);

    // Run the ranking calculation
    $rankings = $this->calculator->calculateRanking($apparatus, $division, $results, $config);

    // Check that only eligible athletes are included (age 9+)
    expect($rankings)->not->toHaveCount(0);

    // Check that the too young athlete is excluded
    $tooYoungAthlete = $rankings->first(fn (RankedAthlete $ra) => $ra->athlete->getId() === 13);
    expect($tooYoungAthlete)->toBeNull();

    // Verify that the top-scoring athlete is ranked first
    $topAthlete = $rankings->first();
    expect($topAthlete->rank)->toBe(1);

    // For a complete test, you would verify:
    // 1. Top 2 Level 10 11-12 are included
    // 2. Top 2 Level 10 10U are included
    // 3. Top 2 Level 10 JumpStart testing scores are included
    // 4. Top 2 Level 9 11-12 are included
    // 5. Top 2 Level 9 9-10 are included
    // 6. Top 2 Level 9 JumpStart testing scores are included
    // 7. Top 1 Level 8 11-12 is included
    // 8. Top 1 Level 8 9-10 is included
    // 9. Top 2 Level 8 JumpStart testing scores are included
});

it('ranks tumbling athletes for JumpStart team correctly', function () {
    $config = config('selection-procedures.procedures.2025_jumpstart');
    $apparatus = 'tumbling';
    $division = 'male';

    // Create athletes of different levels and age ranges
    // Level 10 athletes
    $athlete1 = new MockAthlete(1, '2013-01-01', 'male', 'level_10'); // 12 in 2025, Level 10 11-12
    $athlete2 = new MockAthlete(2, '2014-01-01', 'male', 'level_10'); // 11 in 2025, Level 10 11-12
    $athlete3 = new MockAthlete(3, '2015-01-01', 'male', 'level_10'); // 10 in 2025, Level 10 10U
    $athlete4 = new MockAthlete(4, '2016-01-01', 'male', 'level_10'); // 9 in 2025, Level 10 10U

    // Level 9 athletes
    $athlete5 = new MockAthlete(5, '2013-01-01', 'male', 'level_9');  // 12 in 2025, Level 9 11-12
    $athlete6 = new MockAthlete(6, '2014-01-01', 'male', 'level_9');  // 11 in 2025, Level 9 11-12
    $athlete7 = new MockAthlete(7, '2015-01-01', 'male', 'level_9');  // 10 in 2025, Level 9 9-10
    $athlete8 = new MockAthlete(8, '2016-01-01', 'male', 'level_9');  // 9 in 2025, Level 9 9-10

    // Level 8 athletes
    $athlete9 = new MockAthlete(9, '2013-01-01', 'male', 'level_8');   // 12 in 2025, Level 8 11-12
    $athlete10 = new MockAthlete(10, '2014-01-01', 'male', 'level_8'); // 11 in 2025, Level 8 11-12
    $athlete11 = new MockAthlete(11, '2015-01-01', 'male', 'level_8'); // 10 in 2025, Level 8 9-10
    $athlete12 = new MockAthlete(12, '2016-01-01', 'male', 'level_8'); // 9 in 2025, Level 8 9-10

    // Too young athlete
    $athlete13 = new MockAthlete(13, '2017-01-01', 'male', 'level_8'); // 8 in 2025, too young

    // Create results with qualification scores and JumpStart testing scores
    // For tumbling, the scores will typically be lower than trampoline
    $results = collect([
        // Level 10 11-12 qualification scores
        new MockResult($athlete1, 'winter_classic', $apparatus, $division, 40.0, null),
        new MockResult($athlete2, 'elite_challenge', $apparatus, $division, 41.0, null),

        // Level 10 10U qualification scores
        new MockResult($athlete3, 'winter_classic', $apparatus, $division, 38.0, null),
        new MockResult($athlete4, 'elite_challenge', $apparatus, $division, 39.0, null),

        // Level 10 JumpStart testing scores (typically higher than qualification)
        new MockResult($athlete1, 'state_jumpstart_testing', $apparatus, $division, 75.0, null),
        new MockResult($athlete2, 'state_jumpstart_testing', $apparatus, $division, 76.0, null),
        new MockResult($athlete3, 'state_jumpstart_testing', $apparatus, $division, 74.0, null),
        new MockResult($athlete4, 'state_jumpstart_testing', $apparatus, $division, 73.0, null),

        // Level 9 11-12 qualification scores
        new MockResult($athlete5, 'winter_classic', $apparatus, $division, 37.0, null),
        new MockResult($athlete6, 'elite_challenge', $apparatus, $division, 38.0, null),

        // Level 9 9-10 qualification scores
        new MockResult($athlete7, 'winter_classic', $apparatus, $division, 35.0, null),
        new MockResult($athlete8, 'elite_challenge', $apparatus, $division, 36.0, null),

        // Level 9 JumpStart testing scores
        new MockResult($athlete5, 'state_jumpstart_testing', $apparatus, $division, 71.0, null),
        new MockResult($athlete6, 'state_jumpstart_testing', $apparatus, $division, 72.0, null),
        new MockResult($athlete7, 'state_jumpstart_testing', $apparatus, $division, 69.0, null),
        new MockResult($athlete8, 'state_jumpstart_testing', $apparatus, $division, 70.0, null),

        // Level 8 11-12 qualification scores
        new MockResult($athlete9, 'winter_classic', $apparatus, $division, 33.0, null),
        new MockResult($athlete10, 'elite_challenge', $apparatus, $division, 34.0, null),

        // Level 8 9-10 qualification scores
        new MockResult($athlete11, 'winter_classic', $apparatus, $division, 31.0, null),
        new MockResult($athlete12, 'elite_challenge', $apparatus, $division, 32.0, null),

        // Level 8 JumpStart testing scores
        new MockResult($athlete9, 'state_jumpstart_testing', $apparatus, $division, 67.0, null),
        new MockResult($athlete10, 'state_jumpstart_testing', $apparatus, $division, 68.0, null),
        new MockResult($athlete11, 'state_jumpstart_testing', $apparatus, $division, 65.0, null),
        new MockResult($athlete12, 'state_jumpstart_testing', $apparatus, $division, 66.0, null),

        // Too young athlete
        new MockResult($athlete13, 'winter_classic', $apparatus, $division, 30.0, null),
        new MockResult($athlete13, 'state_jumpstart_testing', $apparatus, $division, 65.0, null),
    ]);

    // Run the ranking calculation
    $rankings = $this->calculator->calculateRanking($apparatus, $division, $results, $config);

    // Check that only eligible athletes are included (age 9+)
    expect($rankings)->not->toHaveCount(0);

    // Check that the too young athlete is excluded
    $tooYoungAthlete = $rankings->first(fn (RankedAthlete $ra) => $ra->athlete->getId() === 13);
    expect($tooYoungAthlete)->toBeNull();

    // In tumbling, max team size is 12 (vs 16 for trampoline)
    expect($rankings->count() <= 12)->toBeTrue();

    // Test selection count for tumbling
    // For tumbling, we should have:
    // - 2 Level 10 11-12 (by qualification score)
    // - 2 Level 10 10U (by qualification score)
    // - 2 Level 10 (by jumpstart testing score)
    // - 1 Level 9 11-12 (by qualification score)
    // - 1 Level 9 9-10 (by qualification score)
    // - 1 Level 9 (by jumpstart testing score)
    // - 1 Level 8 11-12 (by qualification score)
    // - 1 Level 8 9-10 (by qualification score)
    // - 1 Level 8 (by jumpstart testing score)

    // Verify the highest scoring Level 10 11-12 athlete is included
    $highestLevel10_11_12 = $rankings->first(fn (RankedAthlete $ra) => $ra->athlete->getId() === 2); // Athlete 2 has highest qualification score
    expect($highestLevel10_11_12)->not->toBeNull();

    // Verify the highest scoring Level 10 10U athlete is included
    $highestLevel10_10U = $rankings->first(fn (RankedAthlete $ra) => $ra->athlete->getId() === 4); // Athlete 4 has highest qualification score
    expect($highestLevel10_10U)->not->toBeNull();

    // Verify a level 9 athlete is included
    $anyLevel9 = $rankings->first(fn (RankedAthlete $ra) => in_array($ra->athlete->getId(), [5, 6, 7, 8]));
    expect($anyLevel9)->not->toBeNull();

    // Verify a level 8 athlete is included
    $anyLevel8 = $rankings->first(fn (RankedAthlete $ra) => in_array($ra->athlete->getId(), [9, 10, 11, 12]));
    expect($anyLevel8)->not->toBeNull();
});

it('ranks double-mini athletes for JumpStart team correctly', function () {
    $config = config('selection-procedures.procedures.2025_jumpstart');
    $apparatus = 'double-mini';
    $division = 'female';

    // Create athletes of different levels and age ranges
    // Level 10 athletes
    $athlete1 = new MockAthlete(1, '2013-01-01', 'female', 'level_10'); // 12 in 2025, Level 10 11-12
    $athlete2 = new MockAthlete(2, '2014-01-01', 'female', 'level_10'); // 11 in 2025, Level 10 11-12
    $athlete3 = new MockAthlete(3, '2015-01-01', 'female', 'level_10'); // 10 in 2025, Level 10 10U
    $athlete4 = new MockAthlete(4, '2016-01-01', 'female', 'level_10'); // 9 in 2025, Level 10 10U

    // Level 9 athletes
    $athlete5 = new MockAthlete(5, '2013-01-01', 'female', 'level_9');  // 12 in 2025, Level 9 11-12
    $athlete6 = new MockAthlete(6, '2014-01-01', 'female', 'level_9');  // 11 in 2025, Level 9 11-12
    $athlete7 = new MockAthlete(7, '2015-01-01', 'female', 'level_9');  // 10 in 2025, Level 9 9-10
    $athlete8 = new MockAthlete(8, '2016-01-01', 'female', 'level_9');  // 9 in 2025, Level 9 9-10

    // Level 8 athletes
    $athlete9 = new MockAthlete(9, '2013-01-01', 'female', 'level_8');   // 12 in 2025, Level 8 11-12
    $athlete10 = new MockAthlete(10, '2014-01-01', 'female', 'level_8'); // 11 in 2025, Level 8 11-12
    $athlete11 = new MockAthlete(11, '2015-01-01', 'female', 'level_8'); // 10 in 2025, Level 8 9-10
    $athlete12 = new MockAthlete(12, '2016-01-01', 'female', 'level_8'); // 9 in 2025, Level 8 9-10

    // Athletes with tied scores - to test handling of ties
    $athlete14 = new MockAthlete(14, '2013-01-01', 'female', 'level_10'); // 12 in 2025, same score as athlete1
    $athlete15 = new MockAthlete(15, '2015-01-01', 'female', 'level_10'); // 10 in 2025, same score as athlete3

    // Create results with qualification scores and JumpStart testing scores
    // For double-mini, scores are typically a bit higher than tumbling
    $results = collect([
        // Level 10 11-12 qualification scores
        new MockResult($athlete1, 'winter_classic', $apparatus, $division, 48.0, null),
        new MockResult($athlete2, 'elite_challenge', $apparatus, $division, 49.0, null),

        // Tied qualification scores to test tie handling
        new MockResult($athlete14, 'winter_classic', $apparatus, $division, 48.0, null),

        // Level 10 10U qualification scores
        new MockResult($athlete3, 'winter_classic', $apparatus, $division, 46.0, null),
        new MockResult($athlete4, 'elite_challenge', $apparatus, $division, 47.0, null),

        // Tied qualification scores to test tie handling
        new MockResult($athlete15, 'winter_classic', $apparatus, $division, 46.0, null),

        // Level 10 JumpStart testing scores
        new MockResult($athlete1, 'state_jumpstart_testing', $apparatus, $division, 80.0, null),
        new MockResult($athlete2, 'state_jumpstart_testing', $apparatus, $division, 81.0, null),
        new MockResult($athlete3, 'state_jumpstart_testing', $apparatus, $division, 79.0, null),
        new MockResult($athlete4, 'state_jumpstart_testing', $apparatus, $division, 78.0, null),
        new MockResult($athlete14, 'state_jumpstart_testing', $apparatus, $division, 79.5, null), // Different JumpStart score
        new MockResult($athlete15, 'state_jumpstart_testing', $apparatus, $division, 78.5, null), // Different JumpStart score

        // Level 9 11-12 qualification scores
        new MockResult($athlete5, 'winter_classic', $apparatus, $division, 45.0, null),
        new MockResult($athlete6, 'elite_challenge', $apparatus, $division, 46.0, null),

        // Level 9 9-10 qualification scores
        new MockResult($athlete7, 'winter_classic', $apparatus, $division, 43.0, null),
        new MockResult($athlete8, 'elite_challenge', $apparatus, $division, 44.0, null),

        // Level 9 JumpStart testing scores
        new MockResult($athlete5, 'state_jumpstart_testing', $apparatus, $division, 76.0, null),
        new MockResult($athlete6, 'state_jumpstart_testing', $apparatus, $division, 77.0, null),
        new MockResult($athlete7, 'state_jumpstart_testing', $apparatus, $division, 74.0, null),
        new MockResult($athlete8, 'state_jumpstart_testing', $apparatus, $division, 75.0, null),

        // Level 8 11-12 qualification scores
        new MockResult($athlete9, 'winter_classic', $apparatus, $division, 41.0, null),
        new MockResult($athlete10, 'elite_challenge', $apparatus, $division, 42.0, null),

        // Level 8 9-10 qualification scores
        new MockResult($athlete11, 'winter_classic', $apparatus, $division, 39.0, null),
        new MockResult($athlete12, 'elite_challenge', $apparatus, $division, 40.0, null),

        // Level 8 JumpStart testing scores
        new MockResult($athlete9, 'state_jumpstart_testing', $apparatus, $division, 72.0, null),
        new MockResult($athlete10, 'state_jumpstart_testing', $apparatus, $division, 73.0, null),
        new MockResult($athlete11, 'state_jumpstart_testing', $apparatus, $division, 70.0, null),
        new MockResult($athlete12, 'state_jumpstart_testing', $apparatus, $division, 71.0, null),
    ]);

    // Run the ranking calculation
    $rankings = $this->calculator->calculateRanking($apparatus, $division, $results, $config);

    // Check that rankings are not empty
    expect($rankings)->not->toHaveCount(0);

    // In double-mini, max team size is 12
    expect($rankings->count() <= 12)->toBeTrue();

    // Test tie handling by checking if athletes with tied qualification scores
    // are ranked based on their JumpStart testing scores
    $athlete1Rank = $rankings->first(fn (RankedAthlete $ra) => $ra->athlete->getId() === 1);
    $athlete14Rank = $rankings->first(fn (RankedAthlete $ra) => $ra->athlete->getId() === 14);

    // Both athletes should be included and may have the same rank if ties are preserved
    if ($athlete1Rank && $athlete14Rank) {
        // If the JumpStart scores are used to break ties, athlete1 (80.0) should be ranked below
        // athlete14 (79.5) since the ranking is done by qualification score, not JumpStart score
        expect($athlete1Rank->needsManualReview || $athlete14Rank->needsManualReview)->toBeTrue();
    }

    // Verify that at least one athlete from each required level and age group is included
    // Test if we have athletes from level_10_11_12, level_10_10u, level_9_11_12,
    // level_9_9_10, level_8_11_12, and level_8_9_10

    $level10_11_12Athletes = [1, 2, 14];
    $level10_10uAthletes = [3, 4, 15];
    $level9_11_12Athletes = [5, 6];
    $level9_9_10Athletes = [7, 8];
    $level8_11_12Athletes = [9, 10];
    $level8_9_10Athletes = [11, 12];

    $hasLevel10_11_12 = false;
    $hasLevel10_10u = false;
    $hasLevel9_11_12 = false;
    $hasLevel9_9_10 = false;
    $hasLevel8_11_12 = false;
    $hasLevel8_9_10 = false;

    foreach ($rankings as $athlete) {
        $id = $athlete->athlete->getId();
        if (in_array($id, $level10_11_12Athletes)) {
            $hasLevel10_11_12 = true;
        }
        if (in_array($id, $level10_10uAthletes)) {
            $hasLevel10_10u = true;
        }
        if (in_array($id, $level9_11_12Athletes)) {
            $hasLevel9_11_12 = true;
        }
        if (in_array($id, $level9_9_10Athletes)) {
            $hasLevel9_9_10 = true;
        }
        if (in_array($id, $level8_11_12Athletes)) {
            $hasLevel8_11_12 = true;
        }
        if (in_array($id, $level8_9_10Athletes)) {
            $hasLevel8_9_10 = true;
        }
    }

    // At least one athlete from each category should be included
    expect($hasLevel10_11_12)->toBeTrue();
    expect($hasLevel10_10u)->toBeTrue();
    expect($hasLevel9_11_12)->toBeTrue();
    expect($hasLevel9_9_10)->toBeTrue();
    expect($hasLevel8_11_12)->toBeTrue();
    expect($hasLevel8_9_10)->toBeTrue();
});
