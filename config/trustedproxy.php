<?php

return [
    // Nginx / Cloudflare gibi ters proxy arkasında: proxy IP'leri virgülle veya tümü için "*"
    'proxies' => env('TRUSTED_PROXIES'),
];
