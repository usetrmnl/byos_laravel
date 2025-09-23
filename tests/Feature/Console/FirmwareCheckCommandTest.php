<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('firmware check command has correct signature', function () {
    $command = $this->app->make(App\Console\Commands\FirmwareCheckCommand::class);

    expect($command->getName())->toBe('trmnl:firmware:check');
    expect($command->getDescription())->toBe('Checks for the latest firmware and downloads it if flag --download is passed.');
});

test('firmware check command runs without errors', function () {
    $this->artisan('trmnl:firmware:check')
        ->assertExitCode(0);
});

test('firmware check command runs with download flag', function () {
    $this->artisan('trmnl:firmware:check', ['--download' => true])
        ->assertExitCode(0);
});

test('firmware check command can run successfully', function () {
    $this->artisan('trmnl:firmware:check')
        ->assertExitCode(0);
});
