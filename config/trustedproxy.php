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
    | server, or list proxy addresses separated by commas. Leave it empty
    | when visitors reach the server directly.
    |
    */

    'proxies' => env('TRUSTED_PROXIES') ?: null,

];
