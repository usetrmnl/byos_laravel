<?php

use App\Liquid\Filters\Date;
use Carbon\Carbon;

test('days_ago filter returns correct date', function (): void {
    $filter = new Date();
    $threeDaysAgo = Carbon::now()->subDays(3)->toDateString();

    expect($filter->days_ago(3))->toBe($threeDaysAgo);
});

test('days_ago filter handles string input', function (): void {
    $filter = new Date();
    $fiveDaysAgo = Carbon::now()->subDays(5)->toDateString();

    expect($filter->days_ago('5'))->toBe($fiveDaysAgo);
});

test('days_ago filter with zero days returns today', function (): void {
    $filter = new Date();
    $today = Carbon::now()->toDateString();

    expect($filter->days_ago(0))->toBe($today);
});

test('days_ago filter with large number works correctly', function (): void {
    $filter = new Date();
    $hundredDaysAgo = Carbon::now()->subDays(100)->toDateString();

    expect($filter->days_ago(100))->toBe($hundredDaysAgo);
});
