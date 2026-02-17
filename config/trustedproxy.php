<?php

return [
    // Comma‑separated list from .env, e.g. "10.0.0.0/8,172.16.0.0/12" or '*'
    'proxies' => ($proxies = env('TRUSTED_PROXIES', '')) === '*' ? '*' : array_filter(explode(',', $proxies)),
];
