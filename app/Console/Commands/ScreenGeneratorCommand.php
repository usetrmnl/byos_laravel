<?php

namespace App\Console\Commands;

use App\Jobs\GenerateScreenJob;
use Illuminate\Console\Command;
use Throwable;

class ScreenGeneratorCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'trmnl:screen:generate {deviceId=1} {view=trmnl}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate a screen for a terminal device';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $deviceId = $this->argument('deviceId');
        $view = $this->argument('view');

        try {
            $markup = view($view)->render();
        } catch (Throwable $e) {
            $this->error('Failed to render view: '.$e->getMessage());

            return 1;
        }
        GenerateScreenJob::dispatchSync($deviceId, null, $markup);

        $this->info('Screen generation job finished.');

        return 0;
    }
}
