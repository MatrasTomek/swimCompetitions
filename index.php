<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/functions.php';

$zawody = load_all_zawody();
?>
<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Zawody pływackie — Olimpijczyk Proszówki</title>
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/style.css?v=<?= CSS_VERSION ?>">
</head>
<body>
<header class="site-header">
    <div class="container">
        <div class="site-logo">
            <img src="<?= BASE_URL ?>/logo.jpg" alt="Olimpijczyk Proszówki" class="site-logo-img">
        </div>
        <nav class="site-nav">
            <a href="<?= BASE_URL ?>/admin/login.php" class="btn btn-sm btn-outline">Panel admina</a>
        </nav>
    </div>
</header>

<main class="container">
    <section class="hero">
        <h1>Zawody pływackie</h1>
        <p>Listy startowe zawodów klubu Olimpijczyk Proszówki</p>
    </section>

    <?php if (empty($zawody)): ?>
        <div class="empty-state">
            <p>Brak zawodów. Wyniki pojawią się tutaj po dodaniu plików JSON.</p>
        </div>
    <?php else: ?>
        <div class="zawody-grid">
            <?php foreach ($zawody as $z): ?>
            <article class="zawody-card">
                <div class="zawody-card-body">
                    <div class="zawody-meta">
                        <?php if ($z['data']): ?>
                            <span class="badge-date"><?= h($z['data']) ?></span>
                        <?php endif; ?>
                        <?php if ($z['miejsce']): ?>
                            <span class="badge-city"><?= h($z['miejsce']) ?></span>
                        <?php endif; ?>
                    </div>
                    <h2 class="zawody-name"><?= h($z['nazwa']) ?></h2>
                    <?php if ($z['klub']): ?>
                        <p class="zawody-klub"><?= h($z['klub']) ?></p>
                    <?php endif; ?>
                </div>
                <div class="zawody-card-footer">
                    <?php if ($z['has_file']): ?>
                        <a href="<?= BASE_URL ?>/lista_startowa.php?f=<?= urlencode($z['file']) ?>" class="btn btn-primary">
                            Lista startowa →
                        </a>
                    <?php else: ?>
                        <span class="btn-wkrotce">wkrótce</span>
                    <?php endif; ?>
                </div>
            </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</main>

<footer class="site-footer">
    <div class="container">
        <p>&copy; <?= date('Y') ?> Olimpijczyk Proszówki</p>
        <p class="footer-madeby">made by <a href="https://www.nd-soft.pl" target="_blank" rel="noopener">nd-soft</a></p>
    </div>
</footer>
</body>
</html>
