<?php defined('COST_SPLIT') || exit; ?>
<h1>Edycja zakupu</h1>
<p><a href="<?= h(url($base)) ?>">← <?= h($group['name']) ?></a></p>

<form method="post" action="<?= h(url($base . '/purchases/' . $purchase['id'])) ?>" data-validate novalidate>
    <?= csrf_field() ?>
    <?php if ($isAdminScope): ?>
        <label>Kto kupił
            <select name="person" data-rule="required">
                <?php foreach ($persons as $p): ?>
                    <option value="<?= $p['id'] ?>"<?= (string) ($old['person'] ?? '') === (string) $p['id'] ? ' selected' : '' ?>><?= h($p['name']) ?></option>
                <?php endforeach ?>
            </select>
        </label>
        <?= field_error($errors, 'person') ?>
    <?php endif ?>
    <label>Nazwa
        <input name="name" value="<?= h($old['name'] ?? '') ?>" data-rule="required" maxlength="200">
    </label>
    <?= field_error($errors, 'name') ?>
    <label>Kwota (PLN)
        <input name="amount" value="<?= h($old['amount'] ?? '') ?>" data-rule="money" inputmode="decimal" placeholder="0,00">
    </label>
    <?= field_error($errors, 'amount') ?>
    <label>Dla kogo prezent
        <select name="recipient" data-rule="required">
            <option value="">– wybierz osobę –</option>
            <?php foreach ($recipients as $r): ?>
                <option value="<?= $r['id'] ?>"<?= (string) ($old['recipient'] ?? '') === (string) $r['id'] ? ' selected' : '' ?>><?= h($r['name']) ?></option>
            <?php endforeach ?>
        </select>
    </label>
    <?= field_error($errors, 'recipient') ?>
    <?php if (!$recipients): ?>
        <p class="hint">Administrator nie dodał jeszcze osób, dla których są prezenty – zakupu nie można zapisać.</p>
    <?php endif ?>
    <button type="submit">Zapisz</button>
    <a class="button secondary" href="<?= h(url($base)) ?>">Anuluj</a>
</form>

<form method="post" action="<?= h(url($base . '/purchases/' . $purchase['id'] . '/delete')) ?>" data-confirm="Usunąć zakup „<?= h($purchase['name']) ?>”?">
    <?= csrf_field() ?>
    <button type="submit" class="danger">Usuń zakup</button>
</form>
