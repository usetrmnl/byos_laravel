<?php

use App\Settings\UpdateSettings;

test('it has default value for prereleases', function (): void {
    $settings = app(UpdateSettings::class);

    expect($settings->prereleases)->toBeFalse();
});

test('it can update prereleases', function (): void {
    $settings = app(UpdateSettings::class);
    $settings->prereleases = true;
    $settings->save();

    $settings->refresh();

    expect($settings->prereleases)->toBeTrue();
});

test('it persists prereleases across instances', function (): void {
    $settings1 = app(UpdateSettings::class);
    $settings1->prereleases = true;
    $settings1->save();

    $settings2 = app(UpdateSettings::class);

    expect($settings2->prereleases)->toBeTrue();
});
