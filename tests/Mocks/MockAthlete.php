<?php

namespace AustinW\SelectionProcedures\Tests\Mocks;

use AustinW\SelectionProcedures\Contracts\AthleteContract;
use Carbon\CarbonImmutable;
use DateTimeInterface;

class MockAthlete implements AthleteContract
{
    public function __construct(
        public int|string $id,
        public string $dob,
        public string $gender,
        public string $level = 'youth_elite' // Default to youth_elite for backward compatibility
    ) {}

    public function getId(): int|string
    {
        return $this->id;
    }

    public function getDateOfBirth(): DateTimeInterface
    {
        return CarbonImmutable::parse($this->dob);
    }

    public function getGender(): string
    {
        return $this->gender;
    }

    public function getLevel(): string
    {
        return $this->level;
    }
}
