<?php

namespace AustinW\SelectionProcedures;

use AustinW\SelectionProcedures\Contracts\ProcedureCalculatorContract;
use AustinW\SelectionProcedures\Contracts\ResultContract;
use Illuminate\Support\Collection;
use InvalidArgumentException;
use RuntimeException;

class RankingService
{
    /**
     * Ranks athletes based on the specified procedure, apparatus, and division.
     *
     * @param  string  $procedureKey  Identifier for the selection procedure (e.g., '2025_world_championships').
     * @param  string  $apparatus  The apparatus (e.g., 'trampoline').
     * @param  string  $division  The division (e.g., 'senior_elite').
     * @param  Collection<int, ResultContract>  $results  Collection of results for relevant athletes.
     * @return Collection<int, \AustinW\SelectionProcedures\Dto\RankedAthlete> A collection of ranked athletes.
     *
     * @throws InvalidArgumentException If the procedure key, apparatus, or division is invalid.
     * @throws RuntimeException If the configured calculator class is invalid or missing.
     */
    public function rank(string $procedureKey, string $apparatus, string $division, Collection $results): Collection
    {
        $configKey = 'selection-procedures.procedures.'.$procedureKey;
        $config = config($configKey);

        if (! $config) {
            throw new InvalidArgumentException("Invalid selection procedure key provided: {$procedureKey}");
        }

        if (! isset($config['divisions'][$division])) {
            throw new InvalidArgumentException("Invalid division '{$division}' for procedure '{$procedureKey}'.");
        }

        // Basic apparatus check - can be expanded if needed
        $validApparatus = ['trampoline', 'tumbling', 'double-mini'];
        if (! in_array(strtolower($apparatus), $validApparatus)) {
            throw new InvalidArgumentException("Invalid apparatus provided: {$apparatus}");
        }

        $calculatorClass = $config['calculator'] ?? null;

        if (! $calculatorClass || ! class_exists($calculatorClass)) {
            throw new RuntimeException("Invalid or missing calculator configured for procedure: {$procedureKey}");
        }

        // Use global app() helper
        $calculator = app($calculatorClass);

        if (! ($calculator instanceof ProcedureCalculatorContract)) {
            throw new RuntimeException("Calculator class '{$calculatorClass}' must implement ProcedureCalculatorContract.");
        }

        // Pass the specific procedure's config slice to the calculator
        return $calculator->calculateRanking($apparatus, $division, $results, $config);
    }
}
