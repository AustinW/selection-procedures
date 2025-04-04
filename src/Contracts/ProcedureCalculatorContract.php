<?php

namespace AustinW\SelectionProcedures\Contracts;

use Illuminate\Support\Collection;

interface ProcedureCalculatorContract
{
    /**
     * Calculate the ranking for a specific procedure, apparatus, and division.
     *
     * @param  string  $apparatus  The apparatus (e.g., 'trampoline').
     * @param  string  $division  The division (e.g., 'senior_elite').
     * @param  Collection<int, ResultContract>  $results  Collection of results for all relevant athletes.
     * @param  array  $config  The specific configuration array for this procedure from config/selection-procedures.php.
     * @return Collection<int, \AustinW\SelectionProcedures\Dto\RankedAthlete> A collection of ranked athletes.
     */
    public function calculateRanking(string $apparatus, string $division, Collection $results, array $config): Collection;
}
