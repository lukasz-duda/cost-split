<?php

declare(strict_types=1);

defined('COST_SPLIT') || exit;

/**
 * Ścieżki aplikacji przy włączonym mod_rewrite (patrz app/.htaccess):
 * każde żądanie bez pliku trafia do index.php, a ścieżka trasy to adres URL bez katalogu aplikacji.
 */
final class Routing
{
    /** Katalog aplikacji w adresie URL bez końcowego ukośnika, np. "/koszty" lub "" dla katalogu głównego. */
    public static function basePath(string $scriptName): string
    {
        return rtrim(str_replace('\\', '/', dirname($scriptName)), '/');
    }

    /**
     * Ścieżka trasy, np. "/admin/groups/3". Starsze linki w postaci index.php?r=/p/TOKEN nadal działają.
     */
    public static function path(string $requestUri, string $scriptName, ?string $legacy = null): string
    {
        if ($legacy !== null && $legacy !== '') {
            return '/' . trim($legacy, '/');
        }
        $uri = rawurldecode((string) parse_url($requestUri, PHP_URL_PATH));
        if ($uri === $scriptName) {
            return '/';
        }
        $base = self::basePath($scriptName);
        if ($base !== '' && ($uri === $base || str_starts_with($uri, $base . '/'))) {
            $uri = substr($uri, strlen($base));
        }
        return '/' . trim($uri, '/');
    }

    /** Adres strony aplikacji zaczynający się od ukośnika, np. url('/admin') -> "/koszty/admin". */
    public static function url(string $basePath, string $path): string
    {
        return $basePath . $path;
    }
}
