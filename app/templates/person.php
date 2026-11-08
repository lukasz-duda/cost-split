<?php
defined('COST_SPLIT') || exit;
$form = $old['_form'] ?? '';
$purchaseOld = $form === 'purchase' ? $old : [];
$repaymentOld = $form === 'repayment' ? $old : [];
$purchaseErrors = $form === 'purchase' ? $errors : [];
$repaymentErrors = $form === 'repayment' ? $errors : [];
$sentOld = $form === 'sent' ? $old : [];
$sentErrors = $form === 'sent' ? $errors : [];
$base = '/p/' . $person['access_token'];
?>
<h1><?= h($group['name']) ?></h1>
<p>Zalogowano jako <strong><?= h($person['name']) ?></strong>, udział do zwrotu: <?= h(Format::percent($shares[$person['id']])) ?>.</p>

<div class="columns">
    <section>
        <h2>Dodaję zakup</h2>
        <form method="post" action="<?= h(url($base . '/purchases')) ?>" data-validate novalidate>
            <?= csrf_field() ?>
            <input type="hidden" name="_form" value="purchase">
            <label>Nazwa
                <input name="name" value="<?= h($purchaseOld['name'] ?? '') ?>" data-rule="required" maxlength="200">
            </label>
            <?= field_error($purchaseErrors, 'name') ?>
            <label>Kwota (PLN)
                <input name="amount" value="<?= h($purchaseOld['amount'] ?? '') ?>" data-rule="money" inputmode="decimal" placeholder="0,00">
            </label>
            <?= field_error($purchaseErrors, 'amount') ?>
            <label>Dla kogo prezent
                <select name="recipient" data-rule="required">
                    <option value="">– wybierz osobę –</option>
                    <?php foreach ($recipients as $r): ?>
                        <option value="<?= $r['id'] ?>"<?= (string) ($purchaseOld['recipient'] ?? '') === (string) $r['id'] ? ' selected' : '' ?>><?= h($r['name']) ?></option>
                    <?php endforeach ?>
                </select>
            </label>
            <?= field_error($purchaseErrors, 'recipient') ?>
            <?php if (!$recipients): ?>
                <p class="hint">Administrator nie dodał jeszcze osób, dla których są prezenty – zakupu nie można zapisać.</p>
            <?php endif ?>
            <button type="submit">Dodaj zakup</button>
        </form>
    </section>

    <section>
        <h2>Potwierdzam odbiór</h2>
        <form method="post" action="<?= h(url($base . '/repayments')) ?>" data-validate novalidate>
            <?= csrf_field() ?>
            <input type="hidden" name="_form" value="repayment">
            <label>Od kogo
                <select name="from" data-rule="required">
                    <option value="">– wybierz osobę –</option>
                    <?php foreach ($persons as $p): if ($p['id'] === $person['id']) continue; ?>
                        <option value="<?= $p['id'] ?>"<?= (string) ($repaymentOld['from'] ?? '') === (string) $p['id'] ? ' selected' : '' ?>><?= h($p['name']) ?></option>
                    <?php endforeach ?>
                </select>
            </label>
            <?= field_error($repaymentErrors, 'from') ?>
            <label>Kwota (PLN)
                <input name="amount" value="<?= h($repaymentOld['amount'] ?? '') ?>" data-rule="money" inputmode="decimal" placeholder="0,00">
            </label>
            <?= field_error($repaymentErrors, 'amount') ?>
            <button type="submit">Potwierdź odbiór</button>
        </form>
    </section>

    <section>
        <h2>Zgłaszam zwrot</h2>
        <form method="post" action="<?= h(url($base . '/repayments')) ?>" data-validate novalidate>
            <?= csrf_field() ?>
            <input type="hidden" name="_form" value="sent">
            <label>Dla kogo
                <select name="to" data-rule="required">
                    <option value="">– wybierz osobę –</option>
                    <?php foreach ($persons as $p): if ($p['id'] === $person['id']) continue; ?>
                        <option value="<?= $p['id'] ?>"<?= (string) ($sentOld['to'] ?? '') === (string) $p['id'] ? ' selected' : '' ?>><?= h($p['name']) ?></option>
                    <?php endforeach ?>
                </select>
            </label>
            <?= field_error($sentErrors, 'to') ?>
            <label>Kwota (PLN)
                <input name="amount" value="<?= h($sentOld['amount'] ?? '') ?>" data-rule="money" inputmode="decimal" placeholder="0,00">
            </label>
            <?= field_error($sentErrors, 'amount') ?>
            <button type="submit">Zgłoś zwrot</button>
            <p class="hint">Zwrot będzie niepotwierdzony, dopóki odbiorca nie potwierdzi odbioru.</p>
        </form>
    </section>
</div>

<?php require __DIR__ . '/partials/group_overview.php'; ?>
