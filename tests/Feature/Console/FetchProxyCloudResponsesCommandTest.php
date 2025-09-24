<?php

use App\Jobs\FetchProxyCloudResponses;
use Illuminate\Support\Facades\Bus;

test('it dispatches fetch proxy cloud responses job', function (): void {
    // Prevent the job from actually running
    Bus::fake();

    // Run the command
    $this->artisan('trmnl:cloud:proxy')->assertSuccessful();

    // Assert that the job was dispatched
    Bus::assertDispatched(FetchProxyCloudResponses::class);
});
