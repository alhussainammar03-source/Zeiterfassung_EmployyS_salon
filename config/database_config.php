<?php

require_once __DIR__ . '/../includes/Env.php';

Env::load(__DIR__ . '/../.env');

return [
    'host' => Env::get('DB_HOST', 'localhost'),
    'port' => Env::get('DB_PORT', '3306'),
    'database' => Env::get('DB_DATABASE', 'bellabeauty_db'),
    'charset' => Env::get('DB_CHARSET', 'utf8mb4'),
    'username' => Env::get('DB_USERNAME', 'root'),
    'password' => Env::get('DB_PASSWORD', ''),
];
