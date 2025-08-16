<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Jobs\FetchDeviceModelsJob;
use Exception;
use Illuminate\Console\Command;

final class FetchDeviceModelsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'device-models:fetch';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Fetch device models from the TRMNL API and update the database';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('Dispatching FetchDeviceModelsJob...');

        try {
            FetchDeviceModelsJob::dispatchSync();

            $this->info('FetchDeviceModelsJob has been dispatched successfully.');

            return self::SUCCESS;
        } catch (Exception $e) {
            $this->error('Failed to dispatch FetchDeviceModelsJob: '.$e->getMessage());

            return self::FAILURE;
        }
    }
}
