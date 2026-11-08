<?php
defined('COST_SPLIT') || exit;

$fromOld = isset($old['persons']);
$value = static fn (string $key, int $id, string $dbValue) => $fromOld ? (string) ($old['persons'][$id][$key] ?? '') : $dbValue;
?>
<h1><?= h($group['name']) ?></h1>

<form method="post" action="<?= h(url('/admin/groups/' . (int) $group['id'])) ?>" data-validate novalidate class="row">
    <?= csrf_field() ?>
    <label>Nazwa grupy
        <input name="group_name" value="<?= h($old['group_name'] ?? $group['name']) ?>" data-rule="required" maxlength="100">
    </label>
    <button type="submit">Zmień nazwę</button>
    <?= field_error($errors, 'group_name') ?>
</form>

<h2>Osoby i udziały do zwrotu</h2>
<p class="hint">
    Wpisz udział tylko osobom, które mają płacić inaczej. Osoby z pustym polem dzielą po równo resztę do 100%.
    Suma udziałów wszystkich osób musi wynosić 100%.
</p>

<form method="post" action="<?= h(url('/admin/groups/' . (int) $group['id'] . '/persons')) ?>" data-validate data-shares-form novalidate>
    <?= csrf_field() ?>
    <table class="cards">
        <thead><tr><th>Imię</th><th>E-mail</th><th>Udział (%)</th><th class="num">Udział wyliczony</th><th>Link dostępowy</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($persons as $p):
            $prefix = "persons[{$p['id']}]";
            $link = "{$baseUrl}/p/{$p['access_token']}";
            ?>
            <tr data-share-row data-person-id="<?= $p['id'] ?>" data-saved-share="<?= h((string) $p['share_bp']) ?>">
                <td data-label="Imię">
                    <input name="<?= h($prefix) ?>[name]" value="<?= h($value('name', $p['id'], $p['name'])) ?>" data-rule="required" maxlength="100">
                    <?= field_error($errors, "{$prefix}[name]") ?>
                </td>
                <td data-label="E-mail">
                    <input type="email" name="<?= h($prefix) ?>[email]" value="<?= h($value('email', $p['id'], (string) $p['email'])) ?>" data-rule="email" maxlength="190">
                    <?= field_error($errors, "{$prefix}[email]") ?>
                </td>
                <td data-label="Udział (%)">
                    <input name="<?= h($prefix) ?>[share]" inputmode="decimal" data-rule="percent-optional" data-share
                           value="<?= h($value('share', $p['id'], Format::percentInput($p['share_bp']))) ?>"
                           placeholder="równo">
                    <?= field_error($errors, "{$prefix}[share]") ?>
                </td>
                <td class="num" data-label="Udział wyliczony" data-share-effective><?= h(Format::percent($shares[$p['id']])) ?></td>
                <td class="nowrap" data-label="Link dostępowy">
                    <input readonly value="<?= h($link) ?>" class="link-input" aria-label="Link dla <?= h($p['name']) ?>">
                    <button type="button" data-copy="<?= h($link) ?>">Kopiuj</button>
                    <?php if ($p['email']): ?>
                        <a class="button" href="mailto:<?= h(rawurlencode($p['email'])) ?>?subject=<?= h(rawurlencode('Cost Split: ' . $group['name'])) ?>&amp;body=<?= h(rawurlencode("Cześć {$p['name']},\n\ntwój link do rozliczeń grupy „{$group['name']}”:\n{$link}\n")) ?>">Wyślij</a>
                    <?php endif ?>
                </td>
                <td class="actions"><button type="submit" class="danger" form="delete-person-<?= $p['id'] ?>">Usuń</button></td>
            </tr>
        <?php endforeach ?>
        <tr data-share-row data-share-new>
            <td data-label="Nowa osoba">
                <input name="new[name]" value="<?= h($old['new']['name'] ?? '') ?>" placeholder="Nowa osoba" data-share-name maxlength="100">
                <?= field_error($errors, 'new[name]') ?>
            </td>
            <td data-label="E-mail">
                <input type="email" name="new[email]" value="<?= h($old['new']['email'] ?? '') ?>" data-rule="email" maxlength="190">
                <?= field_error($errors, 'new[email]') ?>
            </td>
            <td data-label="Udział (%)">
                <input name="new[share]" inputmode="decimal" data-rule="percent-optional" data-share
                       value="<?= h($old['new']['share'] ?? '') ?>" placeholder="równo">
                <?= field_error($errors, 'new[share]') ?>
            </td>
            <td class="num" data-label="Udział wyliczony" data-share-effective></td>
            <td class="empty"></td>
            <td class="empty"></td>
        </tr>
        </tbody>
        <tfoot><tr><th colspan="3">Suma udziałów</th><th class="num" data-shares-sum><?= h(Format::percent(array_sum($shares))) ?></th><th colspan="2"></th></tr></tfoot>
    </table>
    <?= field_error($errors + ['shares' => $sharesError], 'shares') ?>
    <button type="submit">Zapisz</button>
</form>

<?php foreach ($persons as $p): ?>
    <form method="post" id="delete-person-<?= $p['id'] ?>" class="inline"
          action="<?= h(url('/admin/groups/' . (int) $group['id'] . '/persons/' . $p['id'] . '/delete')) ?>"
          data-delete-person="<?= $p['id'] ?>"
          data-confirm="Usunąć osobę <?= h($p['name']) ?> razem z jej zakupami i zwrotami?">
        <?= csrf_field() ?>
    </form>
<?php endforeach ?>

<h2>Osoby, dla których są prezenty</h2>
<p class="hint">Osoby obdarowane nie biorą udziału w rozliczeniu. Każdy zakup musi wskazywać osobę, dla której jest prezent, dlatego nie można usunąć osoby, która ma prezenty.</p>

<form method="post" action="<?= h(url('/admin/groups/' . (int) $group['id'] . '/recipients')) ?>" data-validate novalidate>
    <?= csrf_field() ?>
    <table class="cards">
        <thead><tr><th>Imię</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($recipients as $r):
            $field = "recipients[{$r['id']}]";
            ?>
            <tr>
                <td data-label="Imię">
                    <input name="<?= h($field) ?>" value="<?= h(isset($old['recipients']) ? (string) ($old['recipients'][$r['id']] ?? '') : $r['name']) ?>"
                           data-rule="required" maxlength="100" aria-label="Imię osoby obdarowanej">
                    <?= field_error($errors, $field) ?>
                </td>
                <td class="actions"><button type="submit" class="danger" form="delete-recipient-<?= $r['id'] ?>">Usuń</button></td>
            </tr>
        <?php endforeach ?>
        <tr>
            <td data-label="Nowa osoba obdarowana">
                <input name="new_recipient" value="<?= h($old['new_recipient'] ?? '') ?>" placeholder="Nowa osoba obdarowana"
                       maxlength="100" aria-label="Nowa osoba obdarowana">
                <?= field_error($errors, 'new_recipient') ?>
            </td>
            <td class="empty"></td>
        </tr>
        </tbody>
    </table>
    <?= field_error($errors, 'recipients') ?>
    <button type="submit">Zapisz</button>
</form>

<?php
$giftCounts = array_column($gifts, 'count', 'id');
foreach ($recipients as $r): ?>
    <form method="post" id="delete-recipient-<?= $r['id'] ?>" class="inline"
          action="<?= h(url('/admin/groups/' . (int) $group['id'] . '/recipients/' . $r['id'] . '/delete')) ?>"
          data-confirm="Usunąć osobę obdarowaną <?= h($r['name']) ?>?"
          <?php if (($giftCounts[$r['id']] ?? 0) > 0): ?>data-blocked="<?= h(App::RECIPIENT_HAS_GIFTS) ?>" data-blocked-for="recipients"<?php endif ?>>
        <?= csrf_field() ?>
    </form>
<?php endforeach ?>

<?php require __DIR__ . '/partials/group_overview.php'; ?>

<h2>Usuwanie grupy</h2>
<form method="post" action="<?= h(url('/admin/groups/' . (int) $group['id'] . '/delete')) ?>"
      data-confirm="Usunąć grupę <?= h($group['name']) ?> razem ze wszystkimi osobami, zakupami i zwrotami?">
    <?= csrf_field() ?>
    <button type="submit" class="danger">Usuń grupę</button>
</form>
