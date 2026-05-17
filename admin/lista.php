<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

$zawody = load_all_zawody();
$flash  = $_SESSION['flash'] ?? '';
unset($_SESSION['flash']);
?>
<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Lista zawodów — Panel admina</title>
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/style.css?v=<?= CSS_VERSION ?>">
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/admin.css?v=<?= CSS_VERSION ?>">
</head>
<body>
<header class="admin-header">
    <div class="container">
        <span class="admin-logo">Panel admina</span>
        <nav>
            <a href="<?= BASE_URL ?>/admin/lista.php" class="active">Lista zawodów</a>
            <a href="<?= BASE_URL ?>/admin/dodaj.php">Dodaj zawody</a>
            <a href="<?= BASE_URL ?>/admin/live.php">Wyniki LENEX</a>
            <a href="<?= BASE_URL ?>/index.php">Strona główna</a>
            <a href="<?= BASE_URL ?>/admin/logout.php">Wyloguj</a>
        </nav>
    </div>
</header>
<main class="container">
    <div class="page-header">
        <h1>Lista zawodów</h1>
        <a href="<?= BASE_URL ?>/admin/dodaj.php" class="btn btn-primary">+ Dodaj zawody</a>
    </div>

    <?php if ($flash): ?>
        <div class="alert alert-success"><?= h($flash) ?></div>
    <?php endif; ?>

    <?php if (empty($zawody)): ?>
        <p class="empty-state">Brak zawodów. <a href="<?= BASE_URL ?>/admin/dodaj.php">Dodaj pierwsze zawody.</a></p>
    <?php else: ?>
    <div class="table-wrapper">
        <table class="admin-table">
            <thead>
                <tr>
                    <th>Nazwa zawodów</th>
                    <th>Klub</th>
                    <th>Miejscowość</th>
                    <th>Data</th>
                    <th>Plik</th>
                    <th>Akcje</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($zawody as $z): ?>
                <tr>
                    <td>
                        <?= h($z['nazwa']) ?>
                        <?php if (!$z['has_file']): ?>
                            <span style="display:inline-block;margin-left:.4em;font-size:.75rem;background:#f0f4ff;color:#4a6cf7;border:1px solid #c7d7fd;border-radius:4px;padding:1px 6px">zapowiedź</span>
                        <?php endif; ?>
                    </td>
                    <td><?= h($z['klub']) ?></td>
                    <td><?= h($z['miejsce']) ?></td>
                    <td><?= h($z['data']) ?></td>
                    <td><?php if ($z['has_file']): ?><code><?= h($z['file']) ?></code><?php else: ?><em style="color:#aaa">brak pliku</em><?php endif; ?></td>
                    <td class="actions">
                        <?php if ($z['has_file']): ?>
                            <a href="<?= BASE_URL ?>/lista_startowa.php?f=<?= urlencode($z['file']) ?>" class="btn btn-sm btn-outline" target="_blank">Starty</a>
                            <?php $slug = basename($z['file'], '.json'); ?>
                            <a href="<?= BASE_URL ?>/wyniki.php?zawody=<?= urlencode($slug) ?>" class="btn btn-sm btn-outline" target="_blank">Wyniki</a>
                            <a href="<?= BASE_URL ?>/admin/edytuj.php?f=<?= urlencode($z['file']) ?>" class="btn btn-sm btn-secondary">Edytuj</a>
                            <a href="<?= BASE_URL ?>/admin/usun.php?f=<?= urlencode($z['file']) ?>" class="btn btn-sm btn-danger">Usuń</a>
                        <?php else: ?>
                            <a href="<?= BASE_URL ?>/admin/usun.php?zap=<?= urlencode($z['id']) ?>" class="btn btn-sm btn-danger">Usuń</a>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</main>
</body>
</html>
