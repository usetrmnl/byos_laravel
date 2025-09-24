<?php

declare(strict_types=1);

use App\Models\Device;
use App\Models\Firmware;
use App\Models\User;

test('firmware update command has correct signature', function (): void {
    $this->artisan('trmnl:firmware:update --help')
        ->assertExitCode(0);
});

test('firmware update command can be called', function (): void {
    $user = User::factory()->create();
    $device = Device::factory()->create(['user_id' => $user->id]);
    $firmware = Firmware::factory()->create(['version_tag' => '1.0.0']);

    $this->artisan('trmnl:firmware:update')
        ->expectsQuestion('Check for new firmware?', 'no')
        ->expectsQuestion('Update to which version?', $firmware->id)
        ->expectsQuestion('Which devices should be updated?', ["_$device->id"])
        ->assertExitCode(0);

    $device->refresh();
    expect($device->update_firmware_id)->toBe($firmware->id);
});

test('firmware update command updates all devices when all is selected', function (): void {
    $user = User::factory()->create();
    $device1 = Device::factory()->create(['user_id' => $user->id]);
    $device2 = Device::factory()->create(['user_id' => $user->id]);
    $firmware = Firmware::factory()->create(['version_tag' => '1.0.0']);

    $this->artisan('trmnl:firmware:update')
        ->expectsQuestion('Check for new firmware?', 'no')
        ->expectsQuestion('Update to which version?', $firmware->id)
        ->expectsQuestion('Which devices should be updated?', ['all'])
        ->assertExitCode(0);

    $device1->refresh();
    $device2->refresh();
    expect($device1->update_firmware_id)->toBe($firmware->id);
    expect($device2->update_firmware_id)->toBe($firmware->id);
});

test('firmware update command aborts when no devices selected', function (): void {
    $firmware = Firmware::factory()->create(['version_tag' => '1.0.0']);

    $this->artisan('trmnl:firmware:update')
        ->expectsQuestion('Check for new firmware?', 'no')
        ->expectsQuestion('Update to which version?', $firmware->id)
        ->expectsQuestion('Which devices should be updated?', [])
        ->expectsOutput('No devices selected. Aborting.')
        ->assertExitCode(0);
});

test('firmware update command calls firmware check when check is selected', function (): void {
    $user = User::factory()->create();
    $device = Device::factory()->create(['user_id' => $user->id]);
    $firmware = Firmware::factory()->create(['version_tag' => '1.0.0']);

    $this->artisan('trmnl:firmware:update')
        ->expectsQuestion('Check for new firmware?', 'check')
        ->expectsQuestion('Update to which version?', $firmware->id)
        ->expectsQuestion('Which devices should be updated?', ["_$device->id"])
        ->assertExitCode(0);

    $device->refresh();
    expect($device->update_firmware_id)->toBe($firmware->id);
});

test('firmware update command calls firmware check with download when download is selected', function (): void {
    $user = User::factory()->create();
    $device = Device::factory()->create(['user_id' => $user->id]);
    $firmware = Firmware::factory()->create(['version_tag' => '1.0.0']);

    $this->artisan('trmnl:firmware:update')
        ->expectsQuestion('Check for new firmware?', 'download')
        ->expectsQuestion('Update to which version?', $firmware->id)
        ->expectsQuestion('Which devices should be updated?', ["_$device->id"])
        ->assertExitCode(0);

    $device->refresh();
    expect($device->update_firmware_id)->toBe($firmware->id);
});
