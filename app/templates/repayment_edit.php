<?php
defined('COST_SPLIT') || exit;
$personSelect = static function (string $field, string $label) use ($persons, $old, $errors): string {
    $html = '<label>' . h($label) . '<select name="' . h($field) . '" data-rule="required">';
    foreach ($persons as $p) {
        $selected = (string) ($old[$field] ?? '') === (string) $p['id'] ? ' selected' : '';
        $html .= '<option value="' . $p['id'] . '"' . $selected . '>' . h($p['name']) . '</option>';
    }
    return $html . '</select></label>' . field_error($errors, $field);
};
$names = array_column($persons, 'name', 'id');
?>
<h1>Edycja zwrotu</h1>
<p><a href="<?= h(url($base)) ?>">← <?= h($group['name']) ?></a></p>

<form method="post" action="<?= h(url($base . '/repayments/' . $repayment['id'])) ?>" data-validate data-repayment-form novalidate>
    <?= csrf_field() ?>
    <?php foreach (['from' => 'Od kogo', 'to' => 'Dla kogo'] as $field => $label): ?>
        <?php if ($fixed === $field): ?>
            <p><?= h($label) ?>: <strong><?= h($names[$repayment[$field]] ?? '') ?></strong></p>
        <?php else: ?>
            <?= $personSelect($field, $label) ?>
        <?php endif ?>
    <?php endforeach ?>
    <label>Kwota (PLN)
        <input name="amount" value="<?= h($old['amount'] ?? '') ?>" data-rule="money" inputmode="decimal" placeholder="0,00">
    </label>
    <?= field_error($errors, 'amount') ?>
    <?php if ($canSetStatus): ?>
        <label>Status
            <select name="confirmed">
                <option value="0"<?= ($old['confirmed'] ?? '') === '1' ? '' : ' selected' ?>>Niepotwierdzony</option>
                <option value="1"<?= ($old['confirmed'] ?? '') === '1' ? ' selected' : '' ?>>Potwierdzony</option>
            </select>
        </label>
    <?php else: ?>
        <p>Status: <strong>Niepotwierdzony</strong> – odbiorca potwierdzi odbiór.</p>
    <?php endif ?>
    <button type="submit">Zapisz</button>
    <a class="button secondary" href="<?= h(url($base)) ?>">Anuluj</a>
</form>

<form method="post" action="<?= h(url($base . '/repayments/' . $repayment['id'] . '/delete')) ?>" data-confirm="Usunąć zwrot?">
    <?= csrf_field() ?>
    <button type="submit" class="danger">Usuń zwrot</button>
</form>
