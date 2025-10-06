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

test('ordinalize filter formats date with ordinal day', function (): void {
    $filter = new Date();

    expect($filter->ordinalize('2025-10-02', '%A, %B <<ordinal_day>>, %Y'))
        ->toBe('Thursday, October 2nd, 2025');
});

test('ordinalize filter handles datetime string with timezone', function (): void {
    $filter = new Date();

    expect($filter->ordinalize('2025-12-31 16:50:38 -0400', '%A, %b <<ordinal_day>>'))
        ->toBe('Wednesday, Dec 31st');
});

test('ordinalize filter handles different ordinal suffixes', function (): void {
    $filter = new Date();

    // 1st
    expect($filter->ordinalize('2025-01-01', '<<ordinal_day>>'))
        ->toBe('1st');
    
    // 2nd
    expect($filter->ordinalize('2025-01-02', '<<ordinal_day>>'))
        ->toBe('2nd');
    
    // 3rd
    expect($filter->ordinalize('2025-01-03', '<<ordinal_day>>'))
        ->toBe('3rd');
    
    // 4th
    expect($filter->ordinalize('2025-01-04', '<<ordinal_day>>'))
        ->toBe('4th');
    
    // 11th (special case)
    expect($filter->ordinalize('2025-01-11', '<<ordinal_day>>'))
        ->toBe('11th');
    
    // 12th (special case)
    expect($filter->ordinalize('2025-01-12', '<<ordinal_day>>'))
        ->toBe('12th');
    
    // 13th (special case)
    expect($filter->ordinalize('2025-01-13', '<<ordinal_day>>'))
        ->toBe('13th');
    
    // 21st
    expect($filter->ordinalize('2025-01-21', '<<ordinal_day>>'))
        ->toBe('21st');
    
    // 22nd
    expect($filter->ordinalize('2025-01-22', '<<ordinal_day>>'))
        ->toBe('22nd');
    
    // 23rd
    expect($filter->ordinalize('2025-01-23', '<<ordinal_day>>'))
        ->toBe('23rd');
    
    // 24th
    expect($filter->ordinalize('2025-01-24', '<<ordinal_day>>'))
        ->toBe('24th');
});

