<?php

declare(strict_types=1);

use Database\Seeders\ExampleRecipesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('example recipes seeder command calls seeder with correct user id', function () {
    $seeder = Mockery::mock(ExampleRecipesSeeder::class);
    $seeder->shouldReceive('run')
        ->once()
        ->with('123');

    $this->app->instance(ExampleRecipesSeeder::class, $seeder);

    $this->artisan('recipes:seed', ['user_id' => '123'])
        ->assertExitCode(0);
});

test('example recipes seeder command has correct signature', function () {
    $command = $this->app->make(App\Console\Commands\ExampleRecipesSeederCommand::class);

    expect($command->getName())->toBe('recipes:seed');
    expect($command->getDescription())->toBe('Seed example recipes');
});

test('example recipes seeder command prompts for missing input', function () {
    $seeder = Mockery::mock(ExampleRecipesSeeder::class);
    $seeder->shouldReceive('run')
        ->once()
        ->with('456');

    $this->app->instance(ExampleRecipesSeeder::class, $seeder);

    $this->artisan('recipes:seed')
        ->expectsQuestion('What is the user_id?', '456')
        ->assertExitCode(0);
});
