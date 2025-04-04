# Selection Procedures for Laravel

[![Latest Version on Packagist](https://img.shields.io/packagist/v/austinw/selection-procedures.svg?style=flat-square)](https://packagist.org/packages/austinw/selection-procedures)
[![GitHub Tests Action Status](https://img.shields.io/github/actions/workflow/status/austinw/selection-procedures/run-tests.yml?branch=main&label=tests&style=flat-square)](https://github.com/austinw/selection-procedures/actions?query=workflow%3Arun-tests+branch%3Amain)
[![GitHub Code Style Action Status](https://img.shields.io/github/actions/workflow/status/austinw/selection-procedures/fix-php-code-style-issues.yml?branch=main&label=code%20style&style=flat-square)](https://github.com/austinw/selection-procedures/actions?query=workflow%3A"Fix+PHP+code+style+issues"+branch%3Amain)
[![Total Downloads](https://img.shields.io/packagist/dt/austinw/selection-procedures.svg?style=flat-square)](https://packagist.org/packages/austinw/selection-procedures)

This package provides a flexible and powerful system for implementing selection procedures in Laravel applications. It enables developers to create, manage, and execute sophisticated selection processes with ease.

## Overview

Selection Procedures is designed to help manage athlete ranking and selection processes for various sporting competitions. The package includes:

- Flexible configuration system for defining selection criteria
- Multiple pre-built calculation strategies for different types of competitions
- Support for various apparatus and divisions
- Comprehensive ranking algorithms
- Easy integration with existing Laravel applications

## Installation

You can install the package via composer:

```bash
composer require austinw/selection-procedures
```

After installation, publish the configuration file:

```bash
php artisan vendor:publish --tag="selection-procedures-config"
```

## Usage

### Basic Example

```php
use AustinW\SelectionProcedures\RankingService;
use Illuminate\Support\Collection;

// Get your results collection (must implement ResultContract)
$results = new Collection([/* your result objects */]);

// Inject or resolve the ranking service
$rankingService = app(RankingService::class);

// Get ranked athletes
$rankedAthletes = $rankingService->rank(
    'world_championships', // procedure key as defined in config
    'trampoline',         // apparatus
    'senior_elite',       // division
    $results              // collection of results
);

// Process ranked athletes
foreach ($rankedAthletes as $rankedAthlete) {
    echo $rankedAthlete->getAthlete()->getName() . ': ' . $rankedAthlete->getTotalPoints();
}
```

### Implementing Contracts

Your athlete and result classes should implement the provided interfaces:

```php
use AustinW\SelectionProcedures\Contracts\AthleteContract;
use AustinW\SelectionProcedures\Contracts\ResultContract;

class Athlete implements AthleteContract
{
    // Implement required methods
    public function getId(): string
    {
        // Return unique athlete identifier
    }

    public function getName(): string
    {
        // Return athlete name
    }
}

class Result implements ResultContract
{
    // Implement required methods
    public function getAthlete(): AthleteContract
    {
        // Return the athlete object
    }

    public function getCompetitionId(): string
    {
        // Return unique competition identifier
    }

    public function getScore(): float
    {
        // Return the score
    }

    // Additional required methods...
}
```

## Available Calculators

The package comes with several pre-built calculators for different competition types:

- `WorldChampionshipsCalculator`: Selection procedures for World Championships
- `WorldGamesCalculator`: Selection procedures for World Games
- `WagcCalculator`: Selection procedures for World Age Group Competitions
- `JuniorPanAmCalculator`: Selection procedures for Junior Pan American Games
- `EDPCalculator`: Elite Development Program selection procedures
- `JumpStartCalculator`: Jump Start program selection procedures

Each calculator implements specialized ranking algorithms based on the requirements of the specific competition.

## Extending the Package

### Creating Custom Calculators

You can create your own calculator by implementing the `ProcedureCalculatorContract` interface:

```php
use AustinW\SelectionProcedures\Contracts\ProcedureCalculatorContract;

class MyCustomCalculator implements ProcedureCalculatorContract
{
    public function calculateRanking(string $apparatus, string $division, Collection $results, array $config): Collection
    {
        // Your custom ranking logic here

        return new Collection([/* ranked athletes */]);
    }
}
```

Then register your calculator in the config file:

```php
// config/selection-procedures.php
'procedures' => [
    'my_custom_procedure' => [
        'name' => 'My Custom Selection Procedure',
        'calculator' => \App\Calculators\MyCustomCalculator::class,
        'divisions' => [
            'senior_elite' => [
                // Division-specific configuration
            ],
        ],
    ],
],
```

## Testing

```bash
composer test
```

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## Contributing

Please see [CONTRIBUTING](.github/CONTRIBUTING.md) for details.

## Security Vulnerabilities

Please review [our security policy](../../security/policy) on how to report security vulnerabilities.

## Credits

- [Austin White](https://github.com/austinw)
- [All Contributors](../../contributors)

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
