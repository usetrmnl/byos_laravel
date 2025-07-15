<?php

use App\Liquid\Filters\Uniqueness;

test('append_random appends a random string with 4 characters', function () {
    $filter = new Uniqueness();
    $result = $filter->append_random('chart-');

    // Check that the result starts with the prefix
    expect($result)->toStartWith('chart-');
    // Check that the result is longer than just the prefix (has random part)
    expect(mb_strlen($result))->toBe(mb_strlen('chart-') + 4);
});
