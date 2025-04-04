<?php

namespace AustinW\SelectionProcedures\Contracts;

use DateTimeInterface;

interface AthleteContract
{
    /**
     * Get the unique identifier for the athlete.
     */
    public function getId(): int|string;

    /**
     * Get the athlete's date of birth.
     */
    public function getDateOfBirth(): DateTimeInterface;

    /**
     * Get the athlete's gender ('male' or 'female').
     */
    public function getGender(): string;

    /**
     * Get the athlete's competition level (e.g., 'youth_elite', 'level_10', 'level_9', etc.).
     * 
     * @return string
     */
    public function getLevel(): string;
} 