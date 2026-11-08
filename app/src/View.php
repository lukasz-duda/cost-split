<?php

declare(strict_types=1);

defined('COST_SPLIT') || exit;

final class View
{
    public static function render(string $template, array $vars): void
    {
        $content = self::capture($template, $vars);
        echo self::capture('layout', $vars + ['content' => $content]);
    }

    public static function capture(string $template, array $vars): string
    {
        extract($vars, EXTR_SKIP);
        ob_start();
        require __DIR__ . "/../templates/{$template}.php";
        return (string) ob_get_clean();
    }
}

function h(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** Element na komunikat błędu pola, wypełniany przez serwer lub przez validation.js. */
function field_error(array $errors, string $field): string
{
    return '<small class="error" data-error-for="' . h($field) . '">' . h($errors[$field] ?? '') . '</small>';
}

/** Adres strony aplikacji (od katalogu głównego serwera), np. url('/admin') -> "/koszty/admin". */
function url(string $path): string
{
    return Routing::url(Routing::basePath($_SERVER['SCRIPT_NAME'] ?? '/index.php'), $path);
}

function csrf_field(): string
{
    return '<input type="hidden" name="_csrf" value="' . h(App::csrfToken()) . '">';
}
