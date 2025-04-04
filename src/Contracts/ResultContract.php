<?php

namespace AustinW\SelectionProcedures\Contracts;

interface ResultContract
{
    /**
     * Get the Athlete associated with this result.
     */
    public function getAthlete(): AthleteContract;

    /**
     * Get the identifier for the event where this result was achieved.
     * This should match one of the event keys defined in the procedure config.
     */
    public function getEventIdentifier(): string;

    /**
     * Get the apparatus for this result (e.g., 'trampoline', 'double-mini', 'tumbling').
     */
    public function getApparatus(): string;

    /**
     * Get the division for this result (e.g., 'senior_elite', '13-14', '17-21').
     */
    public function getDivision(): string;

    /**
     * Get the Qualification score for this result.
     * Definition varies by procedure/apparatus (e.g., sum of routines, single routine).
     */
    public function getQualificationScore(): float;

    /**
     * Get the Final score for this result.
     * May be null if the procedure doesn't use final scores or athlete didn't make finals.
     * Definition varies by procedure/apparatus.
     */
    public function getFinalScore(): ?float;

    /**
     * Get the competition level for this result (e.g., 'senior_elite', 'junior', 'youth_elite').
     */
    public function getLevel(): string;
} 