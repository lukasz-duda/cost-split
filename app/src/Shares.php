<?php

declare(strict_types=1);

defined('COST_SPLIT') || exit;

/**
 * Udziały osób w grupie. Osoba z udziałem NULL dostaje równą część tego, co zostało do 100%.
 * Odpowiednik po stronie klienta: effectiveShares() w assets/validation.js.
 */
final class Shares
{
    /**
     * @param list<?int> $explicitBp udziały w kolejności osób (wg id); NULL = udział automatyczny
     * @return list<int> efektywne udziały w tej samej kolejności
     */
    public static function effective(array $explicitBp): array
    {
        $auto = count(array_filter($explicitBp, static fn (?int $bp) => $bp === null));
        $rest = max(0, Validation::FULL_SHARE_BP - array_sum(array_filter($explicitBp, static fn (?int $bp) => $bp !== null)));
        $base = $auto > 0 ? intdiv($rest, $auto) : 0;
        $extra = $auto > 0 ? $rest - $base * $auto : 0;

        $result = [];
        foreach ($explicitBp as $bp) {
            if ($bp === null) {
                $bp = $base + ($extra-- > 0 ? 1 : 0);
            }
            $result[] = $bp;
        }
        return $result;
    }
}
