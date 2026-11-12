<?php

return [

    /*
    | First Administrator for a live (production) site, used by `php artisan bayanihan:install`.
    | Set these as environment variables on the host; never commit real values.
    | They live in config (not read with env() in the command) so they still work when config is cached.
    */
    'admin' => [
        'name' => env('ADMIN_NAME', 'Administrator'),
        'email' => env('ADMIN_EMAIL'),
        'password' => env('ADMIN_PASSWORD'),
    ],

];
