<?php

use App\Jobs\FetchProxyCloudResponses;
use App\Jobs\FirmwarePollJob;

Schedule::job(FetchProxyCloudResponses::class, [])->cron(
    config('services.trmnl.proxy_refresh_cron') ? config('services.trmnl.proxy_refresh_cron') :
        sprintf('*/%s * * * *', intval(config('services.trmnl.proxy_refresh_minutes', 15)))
);

Schedule::job(FirmwarePollJob::class)->daily();
