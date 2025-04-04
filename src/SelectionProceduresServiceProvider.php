<?php

namespace AustinW\SelectionProcedures;

use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;
// use AustinW\SelectionProcedures\Commands\SelectionProceduresCommand;

class SelectionProceduresServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        /*
         * This class is a Package Service Provider
         *
         * More info: https://github.com/spatie/laravel-package-tools
         */
        $package
            ->name('selection-procedures')
            ->hasConfigFile()
            ->hasViews()
            ->hasMigration('create_selection-procedures_table');
            // ->hasCommand(SelectionProceduresCommand::class);
    }
} 