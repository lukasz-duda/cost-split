<?php

declare(strict_types=1);

defined('COST_SPLIT') || exit;

/**
 * Reguły walidacji. Odpowiednik po stronie klienta: assets/validation.js.
 */
final class Validation
{
    public const int FULL_SHARE_BP = 10000;
    public const int MAX_AMOUNT = 1_000_000_000;

    /** Zwraca kwotę w groszach lub null, gdy format jest niepoprawny. */
    public static function parseMoney(string $value): ?int
    {
        $value = str_replace([' ', "\u{00A0}"], '', trim($value));
        if (!preg_match('/^(\d{1,10})(?:[.,](\d{1,2}))?$/', $value, $m)) {
            return null;
        }
        return (int) $m[1] * 100 + (int) str_pad($m[2] ?? '', 2, '0');
    }

    /** Zwraca udział w setnych częściach procenta lub null, gdy format jest niepoprawny. */
    public static function parsePercent(string $value): ?int
    {
        $value = str_replace([' ', '%'], '', trim($value));
        if (!preg_match('/^(\d{1,3})(?:[.,](\d{1,2}))?$/', $value, $m)) {
            return null;
        }
        $bp = (int) $m[1] * 100 + (int) str_pad($m[2] ?? '', 2, '0');
        return $bp <= self::FULL_SHARE_BP ? $bp : null;
    }

    public static function amountError(string $value): ?string
    {
        $amount = self::parseMoney($value);
        if ($amount === null) {
            return 'Podaj kwotę w PLN, np. 12,50.';
        }
        if ($amount <= 0) {
            return 'Kwota musi być większa od zera.';
        }
        if ($amount > self::MAX_AMOUNT) {
            return 'Kwota jest zbyt duża.';
        }
        return null;
    }

    public static function percentError(string $value): ?string
    {
        return self::parsePercent($value) === null
            ? 'Podaj udział od 0 do 100%, maksymalnie 2 miejsca po przecinku.'
            : null;
    }

    public static function requiredError(string $value, int $maxLength): ?string
    {
        $value = trim($value);
        if ($value === '') {
            return 'Pole jest wymagane.';
        }
        if (mb_strlen($value) > $maxLength) {
            return "Maksymalnie {$maxLength} znaków.";
        }
        return null;
    }

    /** Reguła biznesowa: suma udziałów jest równa 100%. */
    public static function sharesSumError(array $sharesBp): ?string
    {
        $sum = array_sum($sharesBp);
        return $sum === self::FULL_SHARE_BP
            ? null
            : 'Suma udziałów musi wynosić 100% (obecnie ' . Format::percent($sum) . ').';
    }
}
