<?php

declare(strict_types=1);

defined('COST_SPLIT') || exit;

final class Format
{
    public static function money(int $grosze): string
    {
        $sign = $grosze < 0 ? '-' : '';
        return $sign . number_format(abs($grosze) / 100, 2, ',', "\u{00A0}") . "\u{00A0}zł";
    }

    public static function percent(int $bp): string
    {
        $text = number_format($bp / 100, 2, ',', '');
        return rtrim(rtrim($text, '0'), ',') . '%';
    }

    /** Kwota do pola formularza, np. 123456 -> "1234,56". */
    public static function moneyInput(int $grosze): string
    {
        return number_format($grosze / 100, 2, ',', '');
    }

    /** Wartość procentu do pola formularza, np. 3333 -> "33,33". */
    public static function percentInput(?int $bp): string
    {
        return $bp === null ? '' : rtrim(rtrim(number_format($bp / 100, 2, ',', ''), '0'), ',');
    }
}
