<?php

declare(strict_types=1);

const COST_SPLIT = true;

require __DIR__ . '/src/bootstrap.php';

if (!is_file(__DIR__ . '/config.php')) {
    http_response_code(500);
    exit('Brak pliku config.php – skopiuj config.example.php jako config.php i uzupełnij dane.');
}
$config = require __DIR__ . '/config.php';

session_start([
    'cookie_httponly' => true,
    'cookie_samesite' => 'Lax',
    'use_strict_mode' => true,
]);
header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: no-referrer');
// Stan rozliczeń jest wyliczany przy każdym żądaniu – przeglądarka nie może pokazywać starej wersji strony.
header('Cache-Control: no-store');

// Routing przez parametr ?r=/sciezka – działa na każdym hostingu, bez mod_rewrite.
$path = '/' . trim((string) ($_GET['r'] ?? ''), '/');

try {
    (new App($config))->handle($_SERVER['REQUEST_METHOD'] ?? 'GET', $path);
} catch (Throwable $e) {
    error_log((string) $e);
    http_response_code(500);
    echo 'Wystąpił błąd serwera.';
}
