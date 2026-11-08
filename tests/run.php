<?php

declare(strict_types=1);

const COST_SPLIT = true;

require __DIR__ . '/../app/src/bootstrap.php';

$failures = 0;
$count = 0;
function check(string $name, mixed $actual, mixed $expected): void
{
    global $failures, $count;
    $count++;
    if ($actual !== $expected) {
        $failures++;
        echo "FAIL: {$name}\n  expected: " . var_export($expected, true) . "\n  actual:   " . var_export($actual, true) . "\n";
    }
}

// Walidacja kwot
check('money comma', Validation::parseMoney('12,5'), 1250);
check('money dot', Validation::parseMoney('12.05'), 1205);
check('money spaces', Validation::parseMoney('1 234,56'), 123456);
check('money integer', Validation::parseMoney('7'), 700);
check('money 3 decimals', Validation::parseMoney('1,234'), null);
check('money negative', Validation::parseMoney('-5'), null);
check('money text', Validation::parseMoney('abc'), null);
check('amount zero error', Validation::amountError('0'), 'Kwota musi być większa od zera.');
check('amount ok', Validation::amountError('0,01'), null);
check('amount too big', Validation::amountError('10000000,01'), 'Kwota jest zbyt duża.');

// Walidacja udziałów
check('percent', Validation::parsePercent('33,33'), 3333);
check('percent sign', Validation::parsePercent('50%'), 5000);
check('percent >100', Validation::parsePercent('100,01'), null);
check('percent 100', Validation::parsePercent('100'), 10000);
check('sum ok', Validation::sharesSumError([3333, 3333, 3334]), null);
check('sum bad', Validation::sharesSumError([3333, 3333, 3333]), 'Suma udziałów musi wynosić 100% (obecnie 99,99%).');
check('sum over', Validation::sharesSumError([5000, 6000]), 'Suma udziałów musi wynosić 100% (obecnie 110%).');

// Udziały automatyczne: reszta do 100% dzielona po równo
check('shares two auto', Shares::effective([null, null]), [5000, 5000]);
check('shares three auto rounding', Shares::effective([null, null, null]), [3334, 3333, 3333]);
check('shares mixed', Shares::effective([2000, null, null]), [2000, 4000, 4000]);
check('shares all explicit', Shares::effective([3000, 3000]), [3000, 3000]);
check('shares over 100', Shares::effective([8000, 4000, null]), [8000, 4000, 0]);
check('shares rule ok', Validation::sharesSumError(Shares::effective([null, null, null])), null);
check('shares rule explicit bad', Validation::sharesSumError(Shares::effective([3000, 3000])), 'Suma udziałów musi wynosić 100% (obecnie 60%).');

// Formatowanie
check('format money', Format::money(123456), "1\u{00A0}234,56\u{00A0}zł");
check('format percent', Format::percent(5050), '50,5%');
check('format percent input', Format::percentInput(10000), '100');

check('format money input', Format::moneyInput(123456), '1234,56');

// Rozliczenia: A=1, B=2, C=3, równe udziały poza C
$shares = [1 => 4000, 2 => 4000, 3 => 2000];
check('settlement simple', Settlement::compute($shares, [1 => 10000], []), [
    ['debtor' => 2, 'creditor' => 1, 'owed' => 4000, 'returned' => 0, 'remaining' => 4000],
    ['debtor' => 3, 'creditor' => 1, 'owed' => 2000, 'returned' => 0, 'remaining' => 2000],
]);
check('settlement netting and repayment', Settlement::compute(
    [1 => 5000, 2 => 5000],
    [1 => 10000, 2 => 4000],
    [['from' => 2, 'to' => 1, 'amount' => 1000], ['from' => 1, 'to' => 2, 'amount' => 200]],
), [
    ['debtor' => 2, 'creditor' => 1, 'owed' => 3000, 'returned' => 800, 'remaining' => 2200],
]);
check('settlement reversed direction', Settlement::compute([1 => 5000, 2 => 5000], [2 => 1000], []), [
    ['debtor' => 1, 'creditor' => 2, 'owed' => 500, 'returned' => 0, 'remaining' => 500],
]);
check('settlement balanced', Settlement::compute([1 => 5000, 2 => 5000], [1 => 1000, 2 => 1000], []), []);

check('settlement fully repaid stays visible', Settlement::compute([1 => 5000, 2 => 5000], [1 => 1000], [['from' => 2, 'to' => 1, 'amount' => 500]]), [
    ['debtor' => 2, 'creditor' => 1, 'owed' => 500, 'returned' => 500, 'remaining' => 0],
]);
// Minimalna liczba przelewów: C płaci od razu D zamiast przez pośredników.
check('settlement chain collapsed', Settlement::compute(
    [1 => 2500, 2 => 2500, 3 => 2500, 4 => 2500],
    [1 => 4000, 2 => 4000, 4 => 8000],
    [],
), [
    ['debtor' => 3, 'creditor' => 4, 'owed' => 4000, 'returned' => 0, 'remaining' => 4000],
]);
check('balances sum to zero with rounding', array_sum(Settlement::balances([1 => 3334, 2 => 3333, 3 => 3333], [1 => 100], [])), 0);
check('balances rounding', Settlement::balances([1 => 3334, 2 => 3333, 3 => 3333], [1 => 100], []), [1 => 66, 2 => -33, 3 => -33]);

// Rozłączne grupy o zerowej sumie: zachłannie 5 przelewów, optymalnie 4.
$balances = [1 => 6, 2 => -5, 3 => -1, 4 => -4, 5 => -3, 6 => 7];
$transfers = Settlement::minimalTransfers($balances);
check('minimal transfers count', count($transfers), 4);
foreach ($transfers as [$from, $to, $amount]) {
    $balances[$from] += $amount;
    $balances[$to] -= $amount;
}
check('minimal transfers settle all', array_filter($balances), []);
check('minimal transfers none', Settlement::minimalTransfers([1 => 0, 2 => 0]), []);

// Podsumowanie prezentów: osoby bez prezentów też widoczne.
$recipients = [['id' => 1, 'name' => 'Ola'], ['id' => 2, 'name' => 'Jan']];
check('gifts totals', Gifts::totals($recipients, [
    ['recipient_id' => 1, 'amount' => 1000],
    ['recipient_id' => 1, 'amount' => 250],
]), [
    ['id' => 1, 'name' => 'Ola', 'count' => 2, 'amount' => 1250],
    ['id' => 2, 'name' => 'Jan', 'count' => 0, 'amount' => 0],
]);
check('gifts single', Gifts::totals($recipients, [['recipient_id' => 2, 'amount' => 100]]), [
    ['id' => 1, 'name' => 'Ola', 'count' => 0, 'amount' => 0],
    ['id' => 2, 'name' => 'Jan', 'count' => 1, 'amount' => 100],
]);
check('gifts no recipients', Gifts::totals([], []), []);

// Uprawnienia do zwrotów: 1 zgłosił zwrot dla 2.
$pending = ['from' => 1, 'to' => 2, 'confirmed' => false];
check('sender edits pending', App::canEditRepayment($pending, 1), true);
check('sender cannot confirm', App::canConfirmRepayment($pending, 1), false);
check('recipient confirms', App::canConfirmRepayment($pending, 2), true);
check('admin confirms', App::canConfirmRepayment($pending, null), true);
check('other cannot edit', App::canEditRepayment($pending, 3), false);
check('sender cannot edit confirmed', App::canEditRepayment(['confirmed' => true] + $pending, 1), false);
check('recipient edits confirmed', App::canEditRepayment(['confirmed' => true] + $pending, 2), true);
check('confirmed cannot be confirmed again', App::canConfirmRepayment(['confirmed' => true] + $pending, 2), false);

// Routing po adresie URL (mod_rewrite)
check('routing root', Routing::path('/', '/index.php'), '/');
check('routing index.php', Routing::path('/index.php', '/index.php'), '/');
check('routing admin', Routing::path('/admin', '/index.php'), '/admin');
check('routing query ignored', Routing::path('/admin/groups/3?x=1', '/index.php'), '/admin/groups/3');
check('routing trailing slash', Routing::path('/admin/', '/index.php'), '/admin');
check('routing subdir', Routing::path('/koszty/p/abc', '/koszty/index.php'), '/p/abc');
check('routing subdir root', Routing::path('/koszty/', '/koszty/index.php'), '/');
check('routing subdir no slash', Routing::path('/koszty', '/koszty/index.php'), '/');
check('routing subdir index.php', Routing::path('/koszty/index.php', '/koszty/index.php'), '/');
check('routing similar prefix', Routing::path('/koszty2/admin', '/koszty/index.php'), '/koszty2/admin');
check('routing legacy r', Routing::path('/index.php?r=/p/abc', '/index.php', '/p/abc'), '/p/abc');
check('routing legacy r subdir', Routing::path('/koszty/index.php?r=/admin', '/koszty/index.php', '/admin'), '/admin');
check('base path root', Routing::basePath('/index.php'), '');
check('base path subdir', Routing::basePath('/koszty/index.php'), '/koszty');
check('url root', Routing::url('', '/admin'), '/admin');
check('url subdir', Routing::url('/koszty', '/admin/groups/1'), '/koszty/admin/groups/1');

echo "{$count} checks, {$failures} failures\n";
exit($failures > 0 ? 1 : 0);
