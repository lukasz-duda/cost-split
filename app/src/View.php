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

/** Względny adres strony aplikacji, np. url('/admin') -> "index.php?r=/admin". */
function url(string $path): string
{
    return $path === '/' ? 'index.php' : 'index.php?r=' . $path;
}

function csrf_field(): string
{
    return '<input type="hidden" name="_csrf" value="' . h(App::csrfToken()) . '">';
}
