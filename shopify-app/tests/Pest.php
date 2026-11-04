<?php

/*
|--------------------------------------------------------------------------
| Pest configuration
|--------------------------------------------------------------------------
|
| Feature tests boot the Laravel application with a fresh in-memory SQLite
| database (see phpunit.xml). Existing PHPUnit test classes keep working
| alongside Pest test files.
|
*/

uses(Tests\TestCase::class, Illuminate\Foundation\Testing\RefreshDatabase::class)->in('Feature');
