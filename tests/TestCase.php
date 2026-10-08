<?php

namespace Tests;

use Database\Seeders\BrandSeeder;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * Les marques proposées font partie des données de référence de l'application.
     */
    protected bool $seed = true;

    protected string $seeder = BrandSeeder::class;
}
