<?php
return [
    'db' => [
        'host' => 'localhost',
        'name' => 'payment_planner',
        'user' => 'your_username',
        'pass' => 'your_password',
        'charset' => 'utf8mb4'
    ],
    'app' => [
        'name' => 'Payment Planner',
        'url' => 'http://localhost',
        'env' => 'development',
        'debug' => true,
        'timezone' => 'UTC'
    ],
    'security' => [
        'session_lifetime' => 7200, // 2 hours
        'password_algo' => PASSWORD_BCRYPT,
        'password_options' => [
            'cost' => 12
        ]
    ]
]; 