<?php defined('COST_SPLIT') || exit; ?>
<h1>Logowanie administratora</h1>
<form method="post" action="<?= h(url('/admin/login')) ?>" data-validate novalidate>
    <?= csrf_field() ?>
    <label>Token
        <input type="password" name="token" data-rule="required" autocomplete="current-password" autofocus>
    </label>
    <?= field_error($errors, 'token') ?>
    <button type="submit">Zaloguj</button>
</form>
