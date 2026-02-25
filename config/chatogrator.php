<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Webhook Route Prefix
    |--------------------------------------------------------------------------
    |
    | The URL prefix for incoming webhook routes. Each adapter will be
    | accessible at: POST {prefix}/{adapter}
    |
    */

    'route_prefix' => 'webhooks/chat',

    /*
    |--------------------------------------------------------------------------
    | Webhook Middleware
    |--------------------------------------------------------------------------
    |
    | Middleware applied to webhook routes. Webhooks are verified by each
    | adapter's own signature verification, so auth middleware is typically
    | not needed here.
    |
    */

    'middleware' => [],

];
