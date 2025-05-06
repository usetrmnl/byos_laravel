<?php

return [
    /**
     * Define the allocated memory available to SidecarBrowsershot in megabytes. (Defaults to 2GB)
     * We suggest to allocate at least 513 MB of memory to push Chrome/Puppeteer out of "low-spec" mode.
     *
     * @see https://hammerstone.dev/sidecar/docs/main/functions/customization#memory
     * @see https://github.blog/2021-06-22-framework-building-open-graph-images/
     */
    'memory' => env('SIDECAR_BROWSERSHOT_MEMORY', 2048),

    /**
     * The default ephemeral storage available to SidecarBrowsershot, in megabytes. (Defaults to 512MB)
     *
     * @see https://hammerstone.dev/sidecar/docs/main/functions/customization#storage
     */
    'storage' => env('SIDECAR_BROWSERSHOT_STORAGE', 512),

    /**
     * The default timeout to use for SidecarBrowsershot, in seconds. (Defaults to 300)
     *
     * @see https://hammerstone.dev/sidecar/docs/main/functions/customization#timeout
     */
    'timeout' => env('SIDECAR_BROWSERSHOT_TIMEOUT', 300),

    /**
     * Define the number of warming instances to boot.
     *
     * @see https://hammerstone.dev/sidecar/docs/main/functions/warming
     */
    'warming' => env('SIDECAR_BROWSERSHOT_WARMING_INSTANCES', 0),

    /**
     * AWS Layers to use by the Lambda function.
     * Defaults to "shelfio/chrome-aws-lambda-layer" and "sidecar-browsershot-layer" in your respective AWS region.
     *
     * If you customize this, you must include both "sidecar-browsershot-layer" and "shelfio/chrome-aws-lambda-layer"
     * in your list, as the config overrides the default values.
     * (See BrowsershotFunction@layers for more details)
     *
     * @see https://github.com/shelfio/chrome-aws-lambda-layer
     * @see https://github.com/stefanzweifel/sidecar-browsershot-layer
     */
    'layers' => [
        // "arn:aws:lambda:us-east-1:821527532446:layer:sidecar-browsershot-layer:2",
        // "arn:aws:lambda:us-east-1:764866452798:layer:chrome-aws-lambda:42",
    ],

    /**
     * Path to local directory containing fonts to be installed in the Lambda function.
     * During deployment, BorwsershotLambda will scan this directory for
     * any files and will bundle them into the Lambda function.
     */
    'fonts' => resource_path('sidecar-browsershot/fonts'),
];
