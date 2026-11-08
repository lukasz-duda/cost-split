<?php

declare(strict_types=1);

defined('COST_SPLIT') || exit;

/**
 * Rozliczenie grupy wyliczane na bieżąco z zakupów, udziałów i potwierdzonych zwrotów.
 *
 * Każda osoba pokrywa swój udział w sumie wszystkich zakupów. Różnica między tym, co zapłaciła,
 * a tym, co powinna pokryć (z uwzględnieniem zwrotów), to saldo osoby. Pozostałe zwroty
 * są planowane tak, by liczba przelewów była jak najmniejsza.
 */
final class Settlement
{
    /** Do tej liczby osób z niezerowym saldem plan jest optymalny; powyżej – zachłanny (maks. n−1 przelewów). */
    private const int EXACT_LIMIT = 14;

    /**
     * @param array<int, int> $sharesBp       id osoby => udział (10000 = 100%)
     * @param array<int, int> $purchaseTotals id osoby => suma jej zakupów w groszach
     * @param list<array{from: int, to: int, amount: int}> $repayments potwierdzone zwroty
     * @return list<array{debtor: int, creditor: int, owed: int, returned: int, remaining: int}>
     *         owed = returned + remaining; wiersze z remaining > 0 to minimalny plan przelewów
     */
    public static function compute(array $sharesBp, array $purchaseTotals, array $repayments): array
    {
        $paid = [];
        foreach ($repayments as $r) {
            if (isset($sharesBp[$r['from']], $sharesBp[$r['to']]) && $r['from'] !== $r['to']) {
                $paid[$r['from']][$r['to']] = ($paid[$r['from']][$r['to']] ?? 0) + $r['amount'];
            }
        }
        $returned = static fn (int $from, int $to): int => ($paid[$from][$to] ?? 0) - ($paid[$to][$from] ?? 0);

        $rows = [];
        $planned = [];
        foreach (self::minimalTransfers(self::balances($sharesBp, $purchaseTotals, $repayments)) as [$debtor, $creditor, $amount]) {
            $back = $returned($debtor, $creditor);
            $rows[] = ['debtor' => $debtor, 'creditor' => $creditor, 'owed' => $amount + $back, 'returned' => $back, 'remaining' => $amount];
            $planned[min($debtor, $creditor)][max($debtor, $creditor)] = true;
        }

        // Pary rozliczone już zwrotami, bez dalszych przelewów w planie.
        $ids = array_keys($sharesBp);
        sort($ids);
        foreach ($ids as $i => $a) {
            foreach (array_slice($ids, $i + 1) as $b) {
                $net = $returned($a, $b);
                if ($net !== 0 && !isset($planned[$a][$b])) {
                    [$debtor, $creditor] = $net > 0 ? [$a, $b] : [$b, $a];
                    $rows[] = ['debtor' => $debtor, 'creditor' => $creditor, 'owed' => abs($net), 'returned' => abs($net), 'remaining' => 0];
                }
            }
        }

        usort($rows, static fn (array $x, array $y) => [$y['remaining'] > 0, $x['debtor'], $x['creditor']]
            <=> [$x['remaining'] > 0, $y['debtor'], $y['creditor']]);
        return $rows;
    }

    /**
     * Saldo osoby: dodatnie – powinna otrzymać, ujemne – powinna oddać. Suma sald zawsze wynosi 0.
     *
     * @return array<int, int> id osoby => saldo w groszach
     */
    public static function balances(array $sharesBp, array $purchaseTotals, array $repayments): array
    {
        $balances = array_fill_keys(array_keys($sharesBp), 0);
        $total = 0;
        foreach ($purchaseTotals as $id => $amount) {
            if (isset($balances[$id])) {
                $balances[$id] += $amount;
                $total += $amount;
            }
        }
        foreach (self::allocate($total, $sharesBp) as $id => $cost) {
            $balances[$id] -= $cost;
        }
        foreach ($repayments as $r) {
            if (isset($balances[$r['from']], $balances[$r['to']])) {
                $balances[$r['from']] += $r['amount'];
                $balances[$r['to']] -= $r['amount'];
            }
        }
        return $balances;
    }

    /**
     * Dzieli kwotę proporcjonalnie do udziałów metodą największych reszt, więc części sumują się dokładnie.
     *
     * @param array<int, int> $sharesBp
     * @return array<int, int>
     */
    private static function allocate(int $total, array $sharesBp): array
    {
        $sum = array_sum($sharesBp);
        if ($sum <= 0 || $total === 0) {
            return array_map(static fn () => 0, $sharesBp);
        }
        $parts = [];
        $remainders = [];
        foreach ($sharesBp as $id => $bp) {
            $parts[$id] = intdiv($total * $bp, $sum);
            $remainders[$id] = $total * $bp % $sum;
        }
        uksort($remainders, static fn (int $a, int $b) => [$remainders[$b], $a] <=> [$remainders[$a], $b]);
        $left = $total - array_sum($parts);
        foreach (array_keys($remainders) as $id) {
            if ($left-- <= 0) {
                break;
            }
            $parts[$id]++;
        }
        return $parts;
    }

    /**
     * Minimalna liczba przelewów wyrównujących salda. Osoby dzielone są na jak najwięcej
     * rozłącznych grup o sumie sald 0 – grupa k osób potrzebuje k−1 przelewów.
     *
     * @param array<int, int> $balances
     * @return list<array{0: int, 1: int, 2: int}> [od kogo, dla kogo, kwota]
     */
    public static function minimalTransfers(array $balances): array
    {
        $balances = array_filter($balances, static fn (int $b) => $b !== 0);
        ksort($balances);
        $ids = array_keys($balances);
        $n = count($ids);
        if ($n === 0) {
            return [];
        }
        if ($n > self::EXACT_LIMIT || array_sum($balances) !== 0) {
            return self::greedy($balances);
        }

        // $best[maska] – największa liczba grup o zerowej sumie, na które można podzielić osoby z maski.
        $full = (1 << $n) - 1;
        $sum = array_fill(0, $full + 1, 0);
        $best = array_fill(0, $full + 1, 0);
        for ($mask = 1; $mask <= $full; $mask++) {
            $low = $mask & -$mask;
            $sum[$mask] = $sum[$mask ^ $low] + $balances[$ids[self::bitIndex($low)]];
            $value = 0;
            for ($rest = $mask; $rest > 0; $rest &= $rest - 1) {
                $value = max($value, $best[$mask ^ ($rest & -$rest)]);
            }
            $best[$mask] = $value + ($sum[$mask] === 0 ? 1 : 0);
        }

        // Odtworzenie podziału: zdejmujemy kolejne osoby, zachowując optimum; zerowa suma zamyka grupę.
        $groups = [];
        $current = [];
        for ($mask = $full; $mask > 0;) {
            if ($sum[$mask] === 0 && $current) {
                $groups[] = $current;
                $current = [];
            }
            $gain = $sum[$mask] === 0 ? 1 : 0;
            for ($rest = $mask; $rest > 0; $rest &= $rest - 1) {
                $bit = $rest & -$rest;
                if ($best[$mask ^ $bit] + $gain === $best[$mask]) {
                    $id = $ids[self::bitIndex($bit)];
                    $current[$id] = $balances[$id];
                    $mask ^= $bit;
                    break;
                }
            }
        }
        $groups[] = $current;

        $transfers = [];
        foreach ($groups as $group) {
            array_push($transfers, ...self::greedy($group));
        }
        return $transfers;
    }

    /**
     * Łączy największego dłużnika z największym wierzycielem; każdy krok zeruje co najmniej jedno saldo,
     * więc dla k osób powstaje najwyżej k−1 przelewów.
     *
     * @param array<int, int> $balances
     * @return list<array{0: int, 1: int, 2: int}>
     */
    private static function greedy(array $balances): array
    {
        $transfers = [];
        while ($balances = array_filter($balances, static fn (int $b) => $b !== 0)) {
            if (min($balances) > 0 || max($balances) < 0) {
                break; // salda nie sumują się do zera (niepoprawne udziały)
            }
            $debtor = array_keys($balances, min($balances), true)[0];
            $creditor = array_keys($balances, max($balances), true)[0];
            $amount = min(-$balances[$debtor], $balances[$creditor]);
            $transfers[] = [$debtor, $creditor, $amount];
            $balances[$debtor] += $amount;
            $balances[$creditor] -= $amount;
        }
        return $transfers;
    }

    private static function bitIndex(int $bit): int
    {
        return strlen(decbin($bit)) - 1;
    }
}
