<?php

require_once __DIR__ . '/../includes/Env.php';

$envFile = __DIR__ . '/../.env';

// Lokal: .env laden
// Railway: Variablen kommen direkt aus der Umgebung
if (file_exists($envFile)) {
    Env::load($envFile);
}

return [
    'host' => getenv('DB_HOST') ?: Env::get('DB_HOST', 'localhost'),
    'port' => getenv('DB_PORT') ?: Env::get('DB_PORT', '3306'),
    'database' => getenv('DB_DATABASE') ?: Env::get('DB_DATABASE', 'bellabeauty_db'),
    'charset' => getenv('DB_CHARSET') ?: Env::get('DB_CHARSET', 'utf8mb4'),
    'username' => getenv('DB_USERNAME') ?: Env::get('DB_USERNAME', 'root'),
    'password' => getenv('DB_PASSWORD') ?: Env::get('DB_PASSWORD', ''),
];
