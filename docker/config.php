<?php
// Konfiguracja dla Docker Compose – wartości ze zmiennych środowiskowych.
defined('COST_SPLIT') || exit;

return [
    'admin_token' => (string) getenv('ADMIN_TOKEN'),
    'db_host' => getenv('DB_HOST') ?: 'db',
    'db_name' => getenv('DB_NAME') ?: 'costsplit',
    'db_user' => getenv('DB_USER') ?: 'costsplit',
    'db_password' => (string) getenv('DB_PASSWORD'),
    'app_url' => (string) getenv('APP_URL'),
];
