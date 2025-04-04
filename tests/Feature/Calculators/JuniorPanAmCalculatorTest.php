<?php

use AustinW\SelectionProcedures\Calculators\JuniorPanAmCalculator;
use AustinW\SelectionProcedures\Contracts\ProcedureCalculatorContract;
use AustinW\SelectionProcedures\Dto\RankedAthlete;
use AustinW\SelectionProcedures\Tests\Mocks\MockAthlete;
use AustinW\SelectionProcedures\Tests\Mocks\MockResult;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->calculator = app(JuniorPanAmCalculator::class);

    // Mock config for Junior Pan Am Games
    config(['selection-procedures.procedures.2025_junior_pan_am' => [
        'year' => 2025,
        'name' => '2025 Junior Pan American Games',
        'calculator' => JuniorPanAmCalculator::class,
        'events' => [
            'winter_classic' => ['name' => '2025 Winter Classic'],
            'elite_challenge' => ['name' => '2025 Elite Challenge'],
            'usa_gymnastics_championships' => ['name' => '2025 USA Gymnastics Championships'],
        ],
        'divisions' => [
            'male' => [
                'name' => 'Male',
                'min_age' => 13,
                'max_age' => 21, // Assuming junior age range
            ],
            'female' => [
                'name' => 'Female',
                'min_age' => 13,
                'max_age' => 21, // Assuming junior age range
            ],
        ],
        // Athletes assigned to World Games are ineligible
        'ineligible_athletes' => [9, 10],
    ]]);
});

it('ranks trampoline athletes for Junior Pan Am Games correctly', function () {
    $config = config('selection-procedures.procedures.2025_junior_pan_am');
    $apparatus = 'trampoline';
    $division = 'male';

    // Create athletes of different ages
    $athlete1 = new MockAthlete(1, '2005-01-01', 'male', 'junior'); // 20 in 2025
    $athlete2 = new MockAthlete(2, '2006-01-01', 'male', 'junior'); // 19 in 2025
    $athlete3 = new MockAthlete(3, '2007-01-01', 'male', 'junior'); // 18 in 2025
    $athlete4 = new MockAthlete(4, '2008-01-01', 'male', 'junior'); // 17 in 2025
    $athlete5 = new MockAthlete(5, '2009-01-01', 'male', 'junior'); // 16 in 2025
    $athlete6 = new MockAthlete(6, '2010-01-01', 'male', 'junior'); // 15 in 2025
    
    // Athletes eligible for World Games (should be excluded)
    $athlete9 = new MockAthlete(9, '2005-01-01', 'male', 'senior'); // World Games athlete
    $athlete10 = new MockAthlete(10, '2006-01-01', 'male', 'senior'); // World Games athlete

    // Create results with qualification scores and finals scores
    $results = collect([
        // Athlete 1 results
        new MockResult($athlete1, 'winter_classic', $apparatus, $division, 85.0, 48.5),
        new MockResult($athlete1, 'elite_challenge', $apparatus, $division, 86.5, 49.0),
        new MockResult($athlete1, 'usa_gymnastics_championships', $apparatus, $division, 87.0, 49.5),
        
        // Athlete 2 results
        new MockResult($athlete2, 'winter_classic', $apparatus, $division, 84.0, 48.0),
        new MockResult($athlete2, 'elite_challenge', $apparatus, $division, 85.5, 48.8),
        new MockResult($athlete2, 'usa_gymnastics_championships', $apparatus, $division, 86.0, 49.2),
        
        // Athlete 3 results - tied combined score with Athlete 2 but lower qualification score
        new MockResult($athlete3, 'winter_classic', $apparatus, $division, 83.7, 48.3),
        new MockResult($athlete3, 'elite_challenge', $apparatus, $division, 85.3, 48.5),
        new MockResult($athlete3, 'usa_gymnastics_championships', $apparatus, $division, 86.5, 49.2),
        
        // Athlete 4 results - only 2 events, missing USA Gymnastics Championships
        new MockResult($athlete4, 'winter_classic', $apparatus, $division, 84.5, 48.6),
        new MockResult($athlete4, 'elite_challenge', $apparatus, $division, 85.8, 49.0),
        
        // Athlete 5 results - only 1 event, should be excluded due to insufficient results
        new MockResult($athlete5, 'winter_classic', $apparatus, $division, 86.0, 49.2),
        
        // Athlete 6 results - complete set of results
        new MockResult($athlete6, 'winter_classic', $apparatus, $division, 83.0, 47.8),
        new MockResult($athlete6, 'elite_challenge', $apparatus, $division, 84.2, 48.3),
        new MockResult($athlete6, 'usa_gymnastics_championships', $apparatus, $division, 85.0, 48.6),
        
        // World Games athletes (should be excluded)
        new MockResult($athlete9, 'winter_classic', $apparatus, $division, 88.0, 50.0),
        new MockResult($athlete9, 'elite_challenge', $apparatus, $division, 89.0, 50.5),
        new MockResult($athlete9, 'usa_gymnastics_championships', $apparatus, $division, 90.0, 51.0),
        
        new MockResult($athlete10, 'winter_classic', $apparatus, $division, 87.5, 49.8),
        new MockResult($athlete10, 'elite_challenge', $apparatus, $division, 88.5, 50.2),
        new MockResult($athlete10, 'usa_gymnastics_championships', $apparatus, $division, 89.5, 50.8),
    ]);

    // Run the ranking calculation
    $rankings = $this->calculator->calculateRanking($apparatus, $division, $results, $config);

    // Check number of ranked athletes (should exclude World Games athletes and those with insufficient results)
    // Athletes 1, 2, 3, 4, and 6 should be included (athlete 5 has insufficient results)
    expect($rankings)->toHaveCount(5);
    
    // Verify that the top-scoring athlete is ranked first
    $topAthlete = $rankings->first();
    expect($topAthlete->athlete->getId())->toBe(1);
    expect($topAthlete->rank)->toBe(1);
    
    // Calculate expected combined scores
    // Athlete 1: 86.5 + 87.0 + 49.5 = 223.0
    // Athlete 2: 85.5 + 86.0 + 49.2 = 220.7
    // Athlete 3: 85.3 + 86.5 + 49.2 = 221.0
    // Athlete 4: 84.5 + 85.8 + 49.0 = 219.3
    // Athlete 6: 84.2 + 85.0 + 48.6 = 217.8
    
    // The rankings should be: 1, 3, 2, 4, 6
    
    // Check athlete 1 is ranked first
    $athlete1Ranked = $rankings->first(fn (RankedAthlete $ra) => $ra->athlete->getId() === 1);
    expect($athlete1Ranked->rank)->toBe(1);
    expect(abs($athlete1Ranked->combinedScore - 223.0) < 0.001)->toBeTrue();
    
    // Check athlete 3 is ranked second
    $athlete3Ranked = $rankings->first(fn (RankedAthlete $ra) => $ra->athlete->getId() === 3);
    expect($athlete3Ranked->rank)->toBe(2);
    expect(abs($athlete3Ranked->combinedScore - 221.0) < 0.001)->toBeTrue();
    
    // Check athlete 2 is ranked third (would be alternate)
    $athlete2Ranked = $rankings->first(fn (RankedAthlete $ra) => $ra->athlete->getId() === 2);
    expect($athlete2Ranked->rank)->toBe(3);
    expect(abs($athlete2Ranked->combinedScore - 220.7) < 0.001)->toBeTrue();
    
    // Verify that World Games athletes are excluded
    $worldGamesAthlete = $rankings->first(fn (RankedAthlete $ra) => in_array($ra->athlete->getId(), [9, 10]));
    expect($worldGamesAthlete)->toBeNull();
    
    // Verify that athletes with insufficient results are excluded
    $insufficientResultsAthlete = $rankings->first(fn (RankedAthlete $ra) => $ra->athlete->getId() === 5);
    expect($insufficientResultsAthlete)->toBeNull();
}); 