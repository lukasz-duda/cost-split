<?php defined('COST_SPLIT') || exit; ?>
<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= h($title) ?> – Cost Split</title>
    <link rel="stylesheet" href="assets/style.css">
    <script src="assets/validation.js" defer></script>
    <script src="assets/app.js" defer></script>
</head>
<body>
<header>
    <strong>Cost Split</strong>
    <?php if ($isAdmin): ?>
        <nav>
            <a href="<?= h(url('/admin')) ?>">Grupy</a>
            <form method="post" action="<?= h(url('/admin/logout')) ?>" class="inline">
                <?= csrf_field() ?>
                <button type="submit" class="link">Wyloguj</button>
            </form>
        </nav>
    <?php endif ?>
</header>
<main>
    <?php if ($flash): ?>
        <p class="flash"><?= h($flash) ?></p>
    <?php endif ?>
    <?= $content ?>
</main>
</body>
</html>
