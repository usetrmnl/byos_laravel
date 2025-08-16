<?php

declare(strict_types=1);

use App\Jobs\FetchDeviceModelsJob;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

test('command dispatches fetch device models job', function () {
    Queue::fake();

    $this->artisan('device-models:fetch')
        ->expectsOutput('Dispatching FetchDeviceModelsJob...')
        ->expectsOutput('FetchDeviceModelsJob has been dispatched successfully.')
        ->assertExitCode(0);

    Queue::assertPushed(FetchDeviceModelsJob::class);
});
