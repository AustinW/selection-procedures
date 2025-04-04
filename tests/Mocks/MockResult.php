<?php

namespace AustinW\SelectionProcedures\Tests\Mocks;

use AustinW\SelectionProcedures\Contracts\AthleteContract;
use AustinW\SelectionProcedures\Contracts\ResultContract;

class MockResult implements ResultContract
{
    public function __construct(
        public AthleteContract $athlete,
        public string $eventIdentifier,
        public string $apparatus,
        public string $division,
        public float $qualificationScore,
        public ?float $finalScore = null,
        public string $level = 'senior_elite' // Default level for simplicity
    ) {}

    public function getAthlete(): AthleteContract
    {
        return $this->athlete;
    }

    public function getEventIdentifier(): string
    {
        return $this->eventIdentifier;
    }

    public function getApparatus(): string
    {
        return $this->apparatus;
    }

    public function getDivision(): string
    {
        return $this->division;
    }

    public function getQualificationScore(): float
    {
        return $this->qualificationScore;
    }

    public function getFinalScore(): ?float
    {
        return $this->finalScore;
    }

    public function getLevel(): string
    {
        return $this->level;
    }
}
