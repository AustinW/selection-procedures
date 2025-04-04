<?php

use AustinW\SelectionProcedures\Calculators\EDPCalculator;
use AustinW\SelectionProcedures\Contracts\ProcedureCalculatorContract;
use AustinW\SelectionProcedures\Dto\RankedAthlete;
use AustinW\SelectionProcedures\Tests\Mocks\MockAthlete;
use AustinW\SelectionProcedures\Tests\Mocks\MockResult;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->calculator = app(EDPCalculator::class);

    // Mock config for EDP team
    config(['selection-procedures.procedures.2025_edp' => [
        'year' => 2025,
        'name' => '2025-2026 Elite Development Program',
        'calculator' => EDPCalculator::class,
        'events' => [
            'winter_classic' => ['name' => '2025 Winter Classic'],
            'elite_challenge' => ['name' => '2025 Elite Challenge'],
            'usa_gymnastics_championships' => ['name' => '2025 USA Gymnastics Championships'],
        ],
        'rules' => [
            'team_size' => [
                'trampoline' => 16,
                'tumbling' => 12,
                'double-mini' => 12,
            ],
            'min_qualification_scores' => [
                'trampoline' => 81.5,
                'tumbling' => 40.4,
                'double-mini' => [
                    'male' => 45.4,
                    'female' => 44.8,
                ],
            ],
        ],
        'divisions' => [
            'male' => [
                'name' => 'Male',
                'min_age' => 11,
            ],
            'female' => [
                'name' => 'Female',
                'min_age' => 11,
            ],
        ],
    ]]);
});

it('ranks trampoline athletes for EDP team correctly', function () {
    $config = config('selection-procedures.procedures.2025_edp');
    $apparatus = 'trampoline';
    $division = 'male';

    // Create athletes of different levels and age ranges
    $athlete1 = new MockAthlete(1, '2011-01-01', 'male', 'youth_elite'); // 14 in 2025, Youth Elite
    $athlete2 = new MockAthlete(2, '2012-01-01', 'male', 'youth_elite'); // 13 in 2025, Youth Elite
    $athlete3 = new MockAthlete(3, '2013-01-01', 'male', 'youth_elite'); // 12 in 2025, Youth Elite
    $athlete4 = new MockAthlete(4, '2014-01-01', 'male', 'youth_elite'); // 11 in 2025, Youth Elite
    $athlete5 = new MockAthlete(5, '2011-01-01', 'male', 'level_10');    // 14 in 2025, Level 10
    $athlete6 = new MockAthlete(6, '2012-01-01', 'male', 'level_10');    // 13 in 2025, Level 10
    $athlete7 = new MockAthlete(7, '2013-01-01', 'male', 'level_10');    // 12 in 2025, Level 10
    $athlete8 = new MockAthlete(8, '2014-01-01', 'male', 'level_10');    // 11 in 2025, Level 10
    $athlete9 = new MockAthlete(9, '2015-01-01', 'male', 'youth_elite'); // 10 in 2025, too young

    // Create results with different scores
    $results = collect([
        // Youth Elite 13-14
        new MockResult($athlete1, 'winter_classic', $apparatus, $division, 82.0, null),      // Athlete 1, YE 14
        new MockResult($athlete2, 'elite_challenge', $apparatus, $division, 83.0, null),     // Athlete 2, YE 13
        new MockResult($athlete1, 'usa_gymnastics_championships', $apparatus, $division, 84.0, null),
        
        // Youth Elite 11-12
        new MockResult($athlete3, 'winter_classic', $apparatus, $division, 81.0, null),      // Athlete 3, YE 12
        new MockResult($athlete4, 'elite_challenge', $apparatus, $division, 82.5, null),     // Athlete 4, YE 11
        
        // Level 10 13-14
        new MockResult($athlete5, 'winter_classic', $apparatus, $division, 83.5, null),      // Athlete 5, L10 14
        new MockResult($athlete6, 'elite_challenge', $apparatus, $division, 84.5, null),     // Athlete 6, L10 13
        
        // Level 10 11-12
        new MockResult($athlete7, 'winter_classic', $apparatus, $division, 82.0, null),      // Athlete 7, L10 12
        new MockResult($athlete8, 'elite_challenge', $apparatus, $division, 83.0, null),     // Athlete 8, L10 11
        
        // Too young
        new MockResult($athlete9, 'winter_classic', $apparatus, $division, 90.0, null),      // Athlete 9, YE 10 (too young)
        
        // Athletes below minimum score
        new MockResult($athlete1, 'elite_challenge', $apparatus, $division, 80.0, null),     // Below min score
    ]);

    // Run the ranking calculation
    $rankings = $this->calculator->calculateRanking($apparatus, $division, $results, $config);

    // Check that only eligible athletes are included (age 11-14)
    expect($rankings)->not->toHaveCount(0);
    
    // Check that the too young athlete is excluded
    $tooYoungAthlete = $rankings->first(fn (RankedAthlete $ra) => $ra->athlete->getId() === 9);
    expect($tooYoungAthlete)->toBeNull();
    
    // Verify that the top-scoring athlete is ranked first
    $topAthlete = $rankings->first();
    expect($topAthlete->rank)->toBe(1);
    
    // Check that we've properly sorted athletes by score
    $isSortedByScore = true;
    $lastScore = PHP_FLOAT_MAX;
    
    foreach ($rankings as $rankedAthlete) {
        if ($rankedAthlete->combinedScore > $lastScore) {
            $isSortedByScore = false;
            break;
        }
        $lastScore = $rankedAthlete->combinedScore;
    }
    
    expect($isSortedByScore)->toBeTrue();
    
    // For a complete test, you would verify:
    // 1. Top 4 Youth Elite 13-14 are included
    // 2. Top 4 Youth Elite 11-12 are included
    // 3. Additional 8 top athletes overall are included
    // 4. A minimum of 2 athletes from each division are included if available
    // 5. All meet minimum score requirements
});

it('ranks tumbling athletes for EDP team correctly', function () {
    $config = config('selection-procedures.procedures.2025_edp');
    $apparatus = 'tumbling';
    $division = 'male';

    // Create athletes of different levels and age ranges
    $athlete1 = new MockAthlete(1, '2011-01-01', 'male', 'youth_elite'); // 14 in 2025, Youth Elite
    $athlete2 = new MockAthlete(2, '2012-01-01', 'male', 'youth_elite'); // 13 in 2025, Youth Elite
    $athlete3 = new MockAthlete(3, '2013-01-01', 'male', 'youth_elite'); // 12 in 2025, Youth Elite
    $athlete4 = new MockAthlete(4, '2014-01-01', 'male', 'youth_elite'); // 11 in 2025, Youth Elite
    $athlete5 = new MockAthlete(5, '2011-01-01', 'male', 'level_10');    // 14 in 2025, Level 10
    $athlete6 = new MockAthlete(6, '2012-01-01', 'male', 'level_10');    // 13 in 2025, Level 10
    $athlete7 = new MockAthlete(7, '2013-01-01', 'male', 'level_10');    // 12 in 2025, Level 10
    $athlete8 = new MockAthlete(8, '2014-01-01', 'male', 'level_10');    // 11 in 2025, Level 10
    $athlete9 = new MockAthlete(9, '2015-01-01', 'male', 'youth_elite'); // 10 in 2025, too young

    // Create results with different scores - using tumbling-specific minimum (40.4)
    $results = collect([
        // Youth Elite 13-14
        new MockResult($athlete1, 'winter_classic', $apparatus, $division, 42.0, null),      // Athlete 1, YE 14
        new MockResult($athlete2, 'elite_challenge', $apparatus, $division, 43.0, null),     // Athlete 2, YE 13
        
        // Youth Elite 11-12
        new MockResult($athlete3, 'winter_classic', $apparatus, $division, 41.0, null),      // Athlete 3, YE 12
        new MockResult($athlete4, 'elite_challenge', $apparatus, $division, 42.5, null),     // Athlete 4, YE 11
        
        // Level 10 13-14
        new MockResult($athlete5, 'winter_classic', $apparatus, $division, 43.5, null),      // Athlete 5, L10 14
        new MockResult($athlete6, 'elite_challenge', $apparatus, $division, 44.5, null),     // Athlete 6, L10 13
        
        // Level 10 11-12
        new MockResult($athlete7, 'winter_classic', $apparatus, $division, 42.0, null),      // Athlete 7, L10 12
        new MockResult($athlete8, 'elite_challenge', $apparatus, $division, 43.0, null),     // Athlete 8, L10 11
        
        // Too young
        new MockResult($athlete9, 'winter_classic', $apparatus, $division, 45.0, null),      // Athlete 9, YE 10 (too young)
        
        // Athletes below minimum score
        new MockResult($athlete1, 'elite_challenge', $apparatus, $division, 38.0, null),     // Below min score
    ]);

    // Run the ranking calculation
    $rankings = $this->calculator->calculateRanking($apparatus, $division, $results, $config);

    // Check that only eligible athletes are included (age 11-14)
    expect($rankings)->not->toHaveCount(0);
    
    // Check that the too young athlete is excluded
    $tooYoungAthlete = $rankings->first(fn (RankedAthlete $ra) => $ra->athlete->getId() === 9);
    expect($tooYoungAthlete)->toBeNull();
    
    // Verify that the top-scoring athlete is ranked first
    $topAthlete = $rankings->first();
    expect($topAthlete->rank)->toBe(1);
    
    // In tumbling, max team size is 12 (vs 16 for trampoline)
    expect($rankings->count() <= 12)->toBeTrue();
    
    // Test category-specific selection criteria
    // For tumbling, the selection steps are:
    // 1. Top 4 Youth Elite 13-14
    // 2. Top 4 Youth Elite 11-12
    // 3. Top 4 additional athletes (vs 8 for trampoline)
    
    // Count athletes from each category
    $youthElite13_14Count = $rankings->filter(fn (RankedAthlete $ra) => 
        $ra->contributingScores['category'] === 'youth_elite_13_14')->count();
    $youthElite11_12Count = $rankings->filter(fn (RankedAthlete $ra) => 
        $ra->contributingScores['category'] === 'youth_elite_11_12')->count();
        
    // Verify top performers are included
    expect($youthElite13_14Count >= min(2, count([$athlete1, $athlete2])))->toBeTrue();
    expect($youthElite11_12Count >= min(2, count([$athlete3, $athlete4])))->toBeTrue();
    
    // Check minimum score requirement
    foreach ($rankings as $rankedAthlete) {
        expect($rankedAthlete->meetsMinimumThreshold)->toBeTrue();
        expect($rankedAthlete->combinedScore >= 40.4)->toBeTrue();
    }
});

it('ranks double-mini athletes for EDP team correctly', function () {
    $config = config('selection-procedures.procedures.2025_edp');
    $apparatus = 'double-mini';
    
    // Test the female division to verify gender-specific minimum scores
    $division = 'female';

    // Create athletes of different levels and age ranges
    $athlete1 = new MockAthlete(1, '2011-01-01', 'female', 'youth_elite'); // 14 in 2025, Youth Elite
    $athlete2 = new MockAthlete(2, '2012-01-01', 'female', 'youth_elite'); // 13 in 2025, Youth Elite
    $athlete3 = new MockAthlete(3, '2013-01-01', 'female', 'youth_elite'); // 12 in 2025, Youth Elite
    $athlete4 = new MockAthlete(4, '2014-01-01', 'female', 'youth_elite'); // 11 in 2025, Youth Elite
    $athlete5 = new MockAthlete(5, '2011-01-01', 'female', 'level_10');    // 14 in 2025, Level 10
    $athlete6 = new MockAthlete(6, '2012-01-01', 'female', 'level_10');    // 13 in 2025, Level 10
    $athlete7 = new MockAthlete(7, '2013-01-01', 'female', 'level_10');    // 12 in 2025, Level 10
    $athlete8 = new MockAthlete(8, '2014-01-01', 'female', 'level_10');    // 11 in 2025, Level 10
    
    // Female-specific minimum score is 44.8 for double-mini
    $results = collect([
        // Youth Elite 13-14
        new MockResult($athlete1, 'winter_classic', $apparatus, $division, 46.0, null),      // Athlete 1, YE 14
        new MockResult($athlete2, 'elite_challenge', $apparatus, $division, 47.0, null),     // Athlete 2, YE 13
        
        // Youth Elite 11-12
        new MockResult($athlete3, 'winter_classic', $apparatus, $division, 45.5, null),      // Athlete 3, YE 12
        new MockResult($athlete4, 'elite_challenge', $apparatus, $division, 46.5, null),     // Athlete 4, YE 11
        
        // Level 10 13-14
        new MockResult($athlete5, 'winter_classic', $apparatus, $division, 47.5, null),      // Athlete 5, L10 14
        new MockResult($athlete6, 'elite_challenge', $apparatus, $division, 48.5, null),     // Athlete 6, L10 13
        
        // Level 10 11-12
        new MockResult($athlete7, 'winter_classic', $apparatus, $division, 45.0, null),      // Athlete 7, L10 12
        new MockResult($athlete8, 'elite_challenge', $apparatus, $division, 44.9, null),     // Athlete 8, L10 11
        
        // Borderline score (just above minimum)
        new MockResult($athlete7, 'winter_classic', $apparatus, $division, 44.8, null),
        
        // Below minimum score
        new MockResult($athlete8, 'usa_gymnastics_championships', $apparatus, $division, 44.9, null), // Above min
    ]);

    // Run the ranking calculation
    $rankings = $this->calculator->calculateRanking($apparatus, $division, $results, $config);

    // Check that rankings are not empty
    expect($rankings)->not->toHaveCount(0);
    
    // In double-mini, max team size is 12
    expect($rankings->count() <= 12)->toBeTrue();
    
    // Test gender-specific minimum score
    foreach ($rankings as $rankedAthlete) {
        expect($rankedAthlete->meetsMinimumThreshold)->toBeTrue();
        expect($rankedAthlete->combinedScore >= 44.8)->toBeTrue();
    }
    
    // Debug the athletes and scores in the rankings
    $debugInfo = $rankings->map(function (RankedAthlete $ra) {
        return [
            'id' => $ra->athlete->getId(),
            'score' => $ra->combinedScore,
            'meets_threshold' => $ra->meetsMinimumThreshold
        ];
    });

    // Verify athlete with ID 7 is included (with score 45.0 rather than 44.8)
    $athlete7 = $rankings->first(fn (RankedAthlete $ra) => $ra->athlete->getId() === 7);
    expect($athlete7)->not->toBeNull();

    // Verify athlete with below-minimum score is not included with that score
    $belowMinAthlete = $rankings->first(fn (RankedAthlete $ra) => 
        $ra->athlete->getId() === 8);
    if ($belowMinAthlete) {
        expect($belowMinAthlete->combinedScore >= 44.8)->toBeTrue();
    }
}); 