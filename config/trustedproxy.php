<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Trusted proxies
    |--------------------------------------------------------------------------
    |
    | When the site runs behind Cloudflare, a load balancer or GitHub
    | Codespaces, set TRUSTED_PROXIES so that visitor IP addresses and HTTPS
    | links are detected correctly: "*" trusts the proxy in front of the
    | server, or list proxy addresses separated by commas. Left empty, only a
    | proxy on this same computer is trusted (nginx, or the tunnel of
    | "php artisan share"): nobody else can reach the server from there.
    |
    */

    'proxies' => env('TRUSTED_PROXIES') ?: '127.0.0.1,::1',

];
