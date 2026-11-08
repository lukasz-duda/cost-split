<?php
// Skopiuj ten plik jako config.php i uzupełnij dane z panelu hostingu.
defined('COST_SPLIT') || exit;

return [
    // Token, którym loguje się administrator. Użyj długiego, losowego ciągu znaków.
    'admin_token' => 'ZMIEN-MNIE-na-dlugi-losowy-token',

    'db_host' => 'localhost',
    'db_name' => 'costsplit',
    'db_user' => 'costsplit',
    'db_password' => '',

    // Adres aplikacji używany w linkach dostępowych, np. https://example.pl/koszty/
    // Pusty = wykrywany automatycznie.
    'app_url' => '',
];
