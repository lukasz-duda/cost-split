<?php
defined('COST_SPLIT') || exit;
// $editBase – adres bieżącej strony, $editorId – osoba oglądająca (null = administrator).
$canEditPurchase = static fn (array $pu): bool => $editorId === null || $pu['person_id'] === $editorId;
$inline = static fn (string $path, string $label, array $fields = [], string $class = ''): string =>
    '<form method="post" class="inline" action="' . h(url($editBase . $path)) . '">' . csrf_field()
    . implode('', array_map(static fn ($k, $v) => '<input type="hidden" name="' . h($k) . '" value="' . h($v) . '">', array_keys($fields), $fields))
    . '<button type="submit"' . ($class ? ' class="' . $class . '"' : '') . '>' . h($label) . '</button></form>';
?>
<?php if ($sharesError): ?>
    <p class="error-box">Udziały w grupie są niepoprawne: <?= h($sharesError) ?></p>
<?php endif ?>

<h2>Do zwrotu</h2>
<p class="hint">Stan wyliczany na bieżąco z zakupów, udziałów i potwierdzonych zwrotów, przy najmniejszej liczbie przelewów.</p>
<?php if ($settlement): ?>
    <table class="cards">
        <thead><tr><th>Kto zwraca</th><th>Dla kogo zwrot</th><th class="num">Kwota zwrócona</th><th class="num">Pozostało do zwrotu</th><th class="num">Niepotwierdzone</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($settlement as $row):
            $waiting = $pending[$row['debtor']][$row['creditor']] ?? 0;
            $toSend = max(0, $row['remaining'] - $waiting);
            ?>
            <tr<?= $row['remaining'] === 0 ? ' class="settled"' : '' ?>>
                <td data-label="Kto zwraca"><?= h($names[$row['debtor']]) ?></td>
                <td data-label="Dla kogo zwrot"><?= h($names[$row['creditor']]) ?></td>
                <td class="num" data-label="Kwota zwrócona"><?= h(Format::money($row['returned'])) ?></td>
                <td class="num" data-label="Pozostało do zwrotu"><?= h(Format::money($row['remaining'])) ?></td>
                <td class="num<?= $waiting ? '' : ' empty' ?>" data-label="Niepotwierdzone"><?= $waiting ? h(Format::money($waiting)) : '' ?></td>
                <td class="actions">
                    <?php if ($row['remaining'] > 0 && $editorId === $row['debtor'] && $toSend > 0): ?>
                        <?= $inline('/repayments', 'Zgłoś zwrot ' . Format::money($toSend), ['_form' => 'sent', 'to' => $row['creditor'], 'amount' => Format::moneyInput($toSend)]) ?>
                    <?php elseif ($row['remaining'] > 0 && $editorId === $row['creditor'] && !$waiting): ?>
                        <?= $inline('/repayments', 'Potwierdź odbiór ' . Format::money($row['remaining']), ['_form' => 'repayment', 'from' => $row['debtor'], 'amount' => Format::moneyInput($row['remaining'])]) ?>
                    <?php endif ?>
                </td>
            </tr>
        <?php endforeach ?>
        </tbody>
    </table>
<?php else: ?>
    <p>Brak kwot do zwrotu.</p>
<?php endif ?>

<h2>Zwroty</h2>
<?php if ($repayments): ?>
    <table class="cards">
        <thead><tr><th>Od kogo</th><th>Dla kogo</th><th class="num">Kwota</th><th>Status</th><th>Data</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($repayments as $r): ?>
            <tr<?= $r['confirmed'] ? '' : ' class="pending"' ?>>
                <td data-label="Od kogo"><?= h($names[$r['from']]) ?></td>
                <td data-label="Dla kogo"><?= h($names[$r['to']]) ?></td>
                <td class="num" data-label="Kwota"><?= h(Format::money($r['amount'])) ?></td>
                <td data-label="Status"><span class="status <?= $r['confirmed'] ? 'status-ok' : 'status-wait' ?>"><?= $r['confirmed'] ? 'Potwierdzony' : 'Niepotwierdzony' ?></span></td>
                <td data-label="Data"><?= h($r['created_at']) ?></td>
                <td class="actions">
                    <?php if (App::canConfirmRepayment($r, $editorId)): ?>
                        <?= $inline("/repayments/{$r['id']}/confirm", 'Potwierdź') ?>
                    <?php endif ?>
                    <?php if (App::canEditRepayment($r, $editorId)): ?>
                        <a class="button secondary" href="<?= h(url("{$editBase}/repayments/{$r['id']}")) ?>">Edytuj</a>
                    <?php endif ?>
                </td>
            </tr>
        <?php endforeach ?>
        </tbody>
    </table>
<?php else: ?>
    <p>Brak zwrotów.</p>
<?php endif ?>

<h2>Prezenty dla osób</h2>
<?php if ($gifts): ?>
    <table class="cards">
        <thead><tr><th>Dla kogo</th><th class="num">Liczba prezentów</th><th class="num">Łączna kwota</th></tr></thead>
        <tbody>
        <?php foreach ($gifts as $g): ?>
            <tr>
                <td data-label="Dla kogo"><?= h($g['name']) ?></td>
                <td class="num" data-label="Liczba prezentów"><?= $g['count'] ?></td>
                <td class="num" data-label="Łączna kwota"><?= h(Format::money($g['amount'])) ?></td>
            </tr>
        <?php endforeach ?>
        </tbody>
        <tfoot><tr><th>Razem</th><th class="num" data-label="Liczba prezentów"><?= array_sum(array_column($gifts, 'count')) ?></th><th class="num" data-label="Łączna kwota"><?= h(Format::money(array_sum(array_column($gifts, 'amount')))) ?></th></tr></tfoot>
    </table>
<?php else: ?>
    <p>Brak osób, dla których są prezenty.</p>
<?php endif ?>

<h2>Zakupy</h2>
<?php if ($purchases): ?>
    <table class="cards">
        <thead><tr><th>Kto kupił</th><th>Co kupił</th><th>Dla kogo</th><th class="num">Kwota</th><th>Data</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($purchases as $pu): ?>
            <tr>
                <td data-label="Kto kupił"><?= h($pu['person_name']) ?></td>
                <td data-label="Co kupił"><?= h($pu['name']) ?></td>
                <td data-label="Dla kogo"><?= h($pu['recipient_name']) ?></td>
                <td class="num" data-label="Kwota"><?= h(Format::money($pu['amount'])) ?></td>
                <td data-label="Data"><?= h($pu['created_at']) ?></td>
                <td class="actions">
                    <?php if ($canEditPurchase($pu)): ?>
                        <a class="button secondary" href="<?= h(url("{$editBase}/purchases/{$pu['id']}")) ?>">Edytuj</a>
                    <?php endif ?>
                </td>
            </tr>
        <?php endforeach ?>
        </tbody>
        <tfoot><tr><th colspan="3">Razem</th><th class="num"><?= h(Format::money(array_sum(array_column($purchases, 'amount')))) ?></th><th colspan="2"></th></tr></tfoot>
    </table>
<?php else: ?>
    <p>Brak zakupów.</p>
<?php endif ?>
