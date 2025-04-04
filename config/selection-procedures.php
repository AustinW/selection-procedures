<?php

// config for AustinW/SelectionProcedures
return [
    'year' => 2025, // Default or current year, can be overridden

    'procedures' => [
        /*
        |--------------------------------------------------------------------------
        | 2025 World Championships
        |--------------------------------------------------------------------------
        */
        '2025_world_championships' => [
            'name' => '2025 World Championships',
            'calculator' => \AustinW\SelectionProcedures\Calculators\WorldChampionshipsCalculator::class,
            'events' => [
                '2025_winter_classic' => ['name' => '2025 Winter Classic'],
                '2025_elite_challenge' => ['name' => '2025 Elite Challenge'],
                '2025_usag_champs' => ['name' => '2025 USA Gymnastics Championships'],
            ],
            'rules' => [
                'min_events_attended' => 2,
                'combined_score_qual_count' => 2, // Use top 2 qualification scores
                'combined_score_final_count' => 1, // Use top 1 final score
                'primary_selection_count' => 3,
                'team_size_limit' => 4, // Per gender/discipline
            ],
            'divisions' => [
                'senior_elite' => [
                    'name' => 'Senior Elite',
                    'min_age' => 17,
                    'thresholds' => [
                        'trampoline' => [
                            'preferential' => ['male' => 57.500, 'female' => 52.500],
                            'minimum' => ['male' => 54.500, 'female' => 50.500],
                        ],
                        'tumbling' => [
                            'preferential' => ['male' => 50.100, 'female' => 48.800],
                            'minimum' => ['male' => 48.200, 'female' => 46.200],
                        ],
                        'double-mini' => [
                            'preferential' => ['male' => 55.000, 'female' => 50.300],
                            'minimum' => ['male' => 53.000, 'female' => 49.300],
                        ],
                    ],
                ],
            ],
        ],

        /*
        |--------------------------------------------------------------------------
        | 2025 World Age Group Championships
        |--------------------------------------------------------------------------
        */
        '2025_wagc' => [
            'name' => '2025 World Age Group Competitions',
            'calculator' => \AustinW\SelectionProcedures\Calculators\WagcCalculator::class,
            'events' => [
                '2025_winter_classic' => ['name' => '2025 Winter Classic'],
                '2025_elite_challenge' => ['name' => '2025 Elite Challenge'],
                '2025_usag_champs' => ['name' => '2025 USA Gymnastics Championships'],
            ],
            'rules' => [
                'min_events_attended' => null, // No specific minimum attendance rule mentioned, assume participation implies eligibility for scoring
                'combined_score_qual_count' => 2, // Sum of the two highest Qualification Scores
                'combined_score_final_count' => 0, // Final scores not used
                'primary_selection_count' => 3,
            ],
            'divisions' => [
                '13-14' => [
                    'name' => '13-14 Year Olds',
                    'min_age' => 13,
                    'max_age' => 14,
                    'thresholds' => [
                        'trampoline' => [
                            'preferential' => ['male' => 88.00, 'female' => 88.00],
                            'minimum' => ['male' => 85.00, 'female' => 85.00],
                        ],
                        'tumbling' => [
                            'preferential' => ['male' => 43.80, 'female' => 43.80],
                            'minimum' => ['male' => 42.10, 'female' => 42.10],
                        ],
                        'double-mini' => [
                            'preferential' => ['male' => 48.00, 'female' => 47.00],
                            'minimum' => ['male' => 46.00, 'female' => 45.00],
                        ],
                    ],
                ],
                '15-16' => [ // Note: Age group 15-16 exists in thresholds but not explicitly in WAGC eligibility description (mentions 13-14 & 17-21)
                    'name' => '15-16 Year Olds',
                    'min_age' => 15,
                    'max_age' => 16,
                    'thresholds' => [
                        'trampoline' => [
                            'preferential' => ['male' => 93.00, 'female' => 91.00],
                            'minimum' => ['male' => 90.00, 'female' => 88.00],
                        ],
                        'tumbling' => [
                            'preferential' => ['male' => 46.90, 'female' => 44.00],
                            'minimum' => ['male' => 43.20, 'female' => 42.40],
                        ],
                        'double-mini' => [
                            'preferential' => ['male' => 50.00, 'female' => 48.00],
                            'minimum' => ['male' => 48.00, 'female' => 46.00],
                        ],
                    ],
                ],
                '17-21' => [
                    'name' => '17-21 Year Olds',
                    'min_age' => 17,
                    'max_age' => 21,
                    'level' => 'senior_elite', // Specific requirement for this age group
                    'thresholds' => [
                        'trampoline' => [
                            'preferential' => ['male' => 53.00, 'female' => 49.00],
                            'minimum' => ['male' => 51.00, 'female' => 48.00],
                        ],
                        'tumbling' => [
                            'preferential' => ['male' => 46.60, 'female' => 45.10],
                            'minimum' => ['male' => 45.30, 'female' => 43.50],
                        ],
                        'double-mini' => [
                            'preferential' => ['male' => 52.00, 'female' => 49.00],
                            'minimum' => ['male' => 50.00, 'female' => 47.00],
                        ],
                    ],
                ],
            ],
        ],

        /*
        |--------------------------------------------------------------------------
        | 2025 World Games
        |--------------------------------------------------------------------------
        | Selection based on the highest two scores (Qual or Final) across
        | the two selection events. No score thresholds apply.
        */
        '2025_world_games' => [
            'name' => '2025 World Games',
            'calculator' => \AustinW\SelectionProcedures\Calculators\WorldGamesCalculator::class,
            'events' => [
                '2025_winter_classic' => ['name' => '2025 Winter Classic'],
                '2025_elite_challenge' => ['name' => '2025 Elite Challenge'],
                // Assuming USAG Champs is also a selection event, confirm if needed
                // '2025_usag_champs' => ['name' => '2025 USA Gymnastics Championships'],
            ],
            'rules' => [
                'min_events_attended' => 2, // Based on "two Selection Events" wording
                'combined_score_type' => 'highest_overall', // New logic identifier
                'combined_score_count' => 2, // Use top 2 scores regardless of type (Qual/Final)
                'primary_selection_count' => 1, // Selects 1 athlete/pair
                'alternate_selection_count' => 1, // Selects 1 non-traveling alternate
            ],
            'divisions' => [
                'senior_elite' => [
                    'name' => 'Senior Elite',
                    'min_age' => 17, // Assuming standard senior age
                    // No 'thresholds' key as none apply
                ],
                // Potentially add Synchro division if needed
            ],
        ],

        /*
        |--------------------------------------------------------------------------
        | 2025-2026 Elite Development Program (EDP)
        |--------------------------------------------------------------------------
        | Selection based on qualification scores with minimum thresholds and 
        | prioritized selection criteria across specific age/level divisions.
        */
        '2025_edp' => [
            'name' => '2025-2026 Elite Development Program',
            'calculator' => \AustinW\SelectionProcedures\Calculators\EDPCalculator::class,
            'events' => [
                '2025_winter_classic' => ['name' => '2025 Winter Classic'],
                '2025_elite_challenge' => ['name' => '2025 Elite Challenge'],
                '2025_usag_champs' => ['name' => '2025 USA Gymnastics Championships'],
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
                'eligible_levels' => [
                    'youth_elite' => ['11-12', '13-14'],
                    'level_10' => ['11-12', '13-14'],
                ],
            ],
            'divisions' => [
                'male' => [
                    'name' => 'Male',
                    'min_age' => 11,
                    'max_age' => 14,
                ],
                'female' => [
                    'name' => 'Female',
                    'min_age' => 11,
                    'max_age' => 14,
                ],
            ],
        ],

        /*
        |--------------------------------------------------------------------------
        | 2025-2026 JumpStart Program
        |--------------------------------------------------------------------------
        | Selection based on qualification scores and JumpStart testing scores
        | with prioritized selection across specific age/level divisions.
        */
        '2025_jumpstart' => [
            'name' => '2025-2026 JumpStart Program',
            'calculator' => \AustinW\SelectionProcedures\Calculators\JumpStartCalculator::class,
            'events' => [
                '2025_winter_classic' => ['name' => '2025 Winter Classic'],
                '2025_elite_challenge' => ['name' => '2025 Elite Challenge'],
                '2025_usag_champs' => ['name' => '2025 USA Gymnastics Championships'],
                'state_jumpstart_testing' => ['name' => 'State JumpStart Testing'],
            ],
            'rules' => [
                'team_size' => [
                    'trampoline' => 16,
                    'tumbling' => 12,
                    'double-mini' => 12,
                ],
                'eligible_levels' => [
                    'level_10' => ['10u', '11-12'],
                    'level_9' => ['9-10', '11-12'],
                    'level_8' => ['9-10', '11-12'],
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
        ],

        /*
        |--------------------------------------------------------------------------
        | 2025 Junior Pan American Games
        |--------------------------------------------------------------------------
        | Selection is based on a Combined Score calculated as the sum of the two highest 
        | Qualification Scores plus the highest Finals score from the three Evaluative Events.
        | Team consists of 2 males and 2 females (plus 1 non-traveling alternate per gender).
        */
        '2025_junior_pan_am' => [
            'name' => '2025 Junior Pan American Games',
            'calculator' => \AustinW\SelectionProcedures\Calculators\JuniorPanAmCalculator::class,
            'events' => [
                'winter_classic' => ['name' => '2025 Winter Classic'],
                'elite_challenge' => ['name' => '2025 Elite Challenge'],
                'usa_gymnastics_championships' => ['name' => '2025 USA Gymnastics Championships'],
            ],
            'rules' => [
                'combined_score_type' => 'qualification_and_final',
                'combined_score_qual_count' => 2, // Use top 2 qualification scores
                'combined_score_final_count' => 1, // Use top 1 final score
                'team_size' => 2, // 2 athletes per gender, plus 1 alternate
                'min_events_attended' => 2, // Need at least 2 events for sufficient qualification scores
                // Athletes assigned to World Games are not eligible
                'ineligible_criteria' => ['world_games'],
            ],
            'divisions' => [
                'male' => [
                    'name' => 'Male',
                    'min_age' => 13,
                    'max_age' => 21, // Junior age limit (verify this as needed)
                ],
                'female' => [
                    'name' => 'Female',
                    'min_age' => 13,
                    'max_age' => 21, // Junior age limit (verify this as needed)
                ],
            ],
        ],

        // ... Add other procedures here (e.g., Pan American Games, Olympic Games) ...

    ],
]; 