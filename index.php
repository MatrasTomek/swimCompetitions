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

    <?php if (!empty($zawody)): ?>
    <div class="search-bar">
        <input type="search" id="search" placeholder="Szukaj zawodów..." autocomplete="off">
    </div>
    <?php endif; ?>

    <?php if (empty($zawody)): ?>
        <div class="empty-state">
            <p>Brak zawodów. Wyniki pojawią się tutaj po dodaniu plików JSON.</p>
        </div>
    <?php else: ?>
        <div class="competitions-grid" id="competitions-grid">
            <?php foreach ($zawody as $z): ?>
            <article class="competition-card" data-search="<?= h(mb_strtolower($z['nazwa'] . ' ' . $z['data'] . ' ' . $z['miejsce'] . ' ' . $z['klub'])) ?>">
                <div class="competition-card-body">
                    <div class="competition-meta">
                        <?php if ($z['data']): ?>
                            <span class="badge-date"><?= h($z['data']) ?></span>
                        <?php endif; ?>
                        <?php if ($z['miejsce']): ?>
                            <span class="badge-city"><?= h($z['miejsce']) ?></span>
                        <?php endif; ?>
                    </div>
                    <h2 class="competition-name"><?= h($z['nazwa']) ?></h2>
                    <?php if ($z['klub']): ?>
                        <p class="competition-club"><?= h($z['klub']) ?></p>
                    <?php endif; ?>
                </div>
                <div class="competition-card-footer">
                    <?php if ($z['has_file']): ?>
                        <a href="<?= BASE_URL ?>/lista_startowa.php?f=<?= urlencode($z['file']) ?>" class="btn btn-primary">
                            Lista startowa →
                        </a>
                    <?php else: ?>
                        <span class="btn-coming-soon">wkrótce</span>
                    <?php endif; ?>
                </div>
            </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</main>

<script>
(function () {
    var input = document.getElementById('search');
    if (!input) return;
    input.addEventListener('input', function () {
        var q = this.value.toLowerCase().trim();
        var cards = document.querySelectorAll('#competitions-grid .competition-card');
        var visible = 0;
        cards.forEach(function (card) {
            var match = !q || card.dataset.search.indexOf(q) !== -1;
            card.style.display = match ? '' : 'none';
            if (match) visible++;
        });
        var empty = document.getElementById('searchEmpty');
        if (!empty) {
            empty = document.createElement('p');
            empty.id = 'searchEmpty';
            empty.className = 'empty-state';
            empty.textContent = 'Brak wyników dla podanej frazy.';
            document.getElementById('competitions-grid').after(empty);
        }
        empty.style.display = (q && visible === 0) ? '' : 'none';
    });
})();
</script>

<footer class="site-footer">
    <div class="container">
        <p>&copy; <?= date('Y') ?> Olimpijczyk Proszówki</p>
        <p class="footer-madeby">made by <a href="https://www.nd-soft.pl" target="_blank" rel="noopener">nd-soft</a></p>
    </div>
</footer>
</body>
</html>
