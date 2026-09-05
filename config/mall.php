<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Demonstration account password
    |--------------------------------------------------------------------------
    |
    | The password given to every account created by DemoDataSeeder. It stays
    | "password" for local development, where the application is only reachable
    | from the machine running it.
    |
    | Set DEMO_PASSWORD before seeding any instance that is reachable over the
    | internet: the seeded accounts include a Super Administrator, and the
    | account list is published in the README.
    |
    | The ?: rather than an env() default is deliberate: a DEMO_PASSWORD key
    | that is present but blank yields an empty string, which would otherwise
    | seed every account with no password at all.
    |
    */

    'demo_password' => env('DEMO_PASSWORD') ?: 'password',

];
