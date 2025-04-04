<?php

namespace AustinW\SelectionProcedures\Tests;

use AustinW\SelectionProcedures\SelectionProceduresServiceProvider;
use Illuminate\Database\Eloquent\Factories\Factory;
use Orchestra\Testbench\TestCase as Orchestra;

class TestCase extends Orchestra
{
    /**
     * Override application base path.
     *
     * @return string
     */
    protected function getBasePath()
    {
        // Adjust this path if your workbench directory is located elsewhere
        return __DIR__.'/../workbench';
    }

    protected function setUp(): void
    {
        parent::setUp();

        Factory::guessFactoryNamesUsing(
            fn (string $modelName) => 'AustinW\\SelectionProcedures\\Database\\Factories\\'.class_basename($modelName).'Factory'
        );
    }

    protected function getPackageProviders($app)
    {
        return [
            SelectionProceduresServiceProvider::class,
        ];
    }

    public function getEnvironmentSetUp($app)
    {
        config()->set('database.default', 'testing');

        /*
        $migration = include __DIR__.'/../database/migrations/create_selection-procedures_table.php.stub';
        $migration->up();
        */
    }

    /**
     * Load package config.
     *
     * @param  \Illuminate\Foundation\Application  $app
     * @return void
     */
    protected function resolveApplicationConfiguration($app)
    {
        parent::resolveApplicationConfiguration($app);

        // Merges the package configuration
        $app['config']->set('selection-procedures', require __DIR__.'/../config/selection-procedures.php');
    }
}
