<?php defined('COST_SPLIT') || exit; ?>
<h1>Grupy</h1>

<?php if ($groups): ?>
    <table>
        <thead><tr><th>Nazwa</th><th>Liczba osób</th></tr></thead>
        <tbody>
        <?php foreach ($groups as $g): ?>
            <tr>
                <td><a href="<?= h(url('/admin/groups/' . (int) $g['id'])) ?>"><?= h($g['name']) ?></a></td>
                <td><?= (int) $g['person_count'] ?></td>
            </tr>
        <?php endforeach ?>
        </tbody>
    </table>
<?php else: ?>
    <p>Brak grup.</p>
<?php endif ?>

<h2>Nowa grupa</h2>
<form method="post" action="<?= h(url('/admin/groups')) ?>" data-validate novalidate>
    <?= csrf_field() ?>
    <label>Nazwa
        <input name="name" value="<?= h($old['name'] ?? '') ?>" data-rule="required" maxlength="100">
    </label>
    <?= field_error($errors, 'name') ?>
    <button type="submit">Utwórz grupę</button>
</form>
