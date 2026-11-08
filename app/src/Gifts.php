<?php

declare(strict_types=1);

defined('COST_SPLIT') || exit;

final class Gifts
{
    /**
     * Łączna kwota i liczba prezentów dla każdej osoby obdarowanej (także bez prezentów).
     *
     * @param list<array{id: int, name: string}> $recipients
     * @param list<array{recipient_id: int, amount: int}> $purchases
     * @return list<array{id: int, name: string, count: int, amount: int}>
     */
    public static function totals(array $recipients, array $purchases): array
    {
        $rows = [];
        foreach ($recipients as $r) {
            $rows[$r['id']] = ['id' => $r['id'], 'name' => $r['name'], 'count' => 0, 'amount' => 0];
        }
        foreach ($purchases as $pu) {
            if (isset($rows[$pu['recipient_id']])) {
                $rows[$pu['recipient_id']]['count']++;
                $rows[$pu['recipient_id']]['amount'] += $pu['amount'];
            }
        }
        return array_values($rows);
    }
}
