<?php
return [
    // === Database ===
    // 'db' => [
    //     'host'     => 'localhost',
    //     'dbname'   => 'short_links',
    //     'username' => 'root',
    //     'password' => '',
    //     'charset'  => 'utf8mb4',
    // ],
    'db' => [
        'host'     => 'backend_db',
        'dbname'   => 'short_links',
        'username' => 'backend_user',
        'password' => 'backend_pass',
        'charset'  => 'utf8mb4',
    ],
    // === JWT ===
    /*   'jwt' => [
        'secret'    => 'your_strong_secret_key_here',
        'algo'      => 'HS256',
        'issuer'    => 'your-app-name',
        'expires_in_seconds' => 3600 * 24 * 30, // 30 days
    ], */

    // === App Settings ===
    'app' => [
        'base_url' => 'https://your-domain.com',  // used for generating short URLs, optional
        'default_timezone' => 'UTC',
    ],
];
