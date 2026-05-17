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
    <div class="view-controls">
        <div class="search-bar">
            <input type="search" id="search" placeholder="Szukaj zawodów..." autocomplete="off">
        </div>
        <div class="view-toggle" id="view-toggle">
            <button class="view-toggle-btn active" data-view="grid">⊞ Karty</button>
            <button class="view-toggle-btn" data-view="table">☰ Tabela</button>
        </div>
    </div>
    <?php endif; ?>

    <?php if (empty($zawody)): ?>
        <div class="empty-state">
            <p>Brak zawodów. Wyniki pojawią się tutaj po dodaniu plików JSON.</p>
        </div>
    <?php else: ?>
        <div id="view-grid">
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
        </div>

        <div id="view-table" hidden>
            <div class="table-wrapper">
                <table class="results-table index-table">
                    <thead>
                        <tr>
                            <th>Nazwa</th>
                            <th>Data</th>
                            <th>Miejsce</th>
                            <th>Klub</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($zawody as $z): ?>
                        <tr data-search="<?= h(mb_strtolower($z['nazwa'] . ' ' . $z['data'] . ' ' . $z['miejsce'] . ' ' . $z['klub'])) ?>">
                            <td><?= h($z['nazwa']) ?></td>
                            <td style="white-space:nowrap"><?= h($z['data']) ?></td>
                            <td><?= h($z['miejsce']) ?></td>
                            <td><?= h($z['klub']) ?></td>
                            <td style="white-space:nowrap">
                                <?php if ($z['has_file']): ?>
                                    <a href="<?= BASE_URL ?>/lista_startowa.php?f=<?= urlencode($z['file']) ?>" class="btn btn-sm btn-primary">Lista →</a>
                                <?php else: ?>
                                    <span class="btn-coming-soon" style="padding:.3rem .6rem;font-size:.8rem">wkrótce</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php endif; ?>
</main>

<script>
(function () {
    var input     = document.getElementById('search');
    var toggle    = document.getElementById('view-toggle');
    var viewGrid  = document.getElementById('view-grid');
    var viewTable = document.getElementById('view-table');

    var KEY = 'swim-index-view';

    function doSearch(q, activeView) {
        var visible = 0;
        if (activeView === 'table') {
            var rows = document.querySelectorAll('#view-table tbody tr[data-search]');
            rows.forEach(function (row) {
                var match = !q || row.dataset.search.indexOf(q) !== -1;
                row.style.display = match ? '' : 'none';
                if (match) visible++;
            });
            showEmpty(q && visible === 0, viewTable);
        } else {
            var cards = document.querySelectorAll('#competitions-grid .competition-card');
            cards.forEach(function (card) {
                var match = !q || card.dataset.search.indexOf(q) !== -1;
                card.style.display = match ? '' : 'none';
                if (match) visible++;
            });
            showEmpty(q && visible === 0, viewGrid);
        }
    }

    function showEmpty(show, container) {
        var empty = document.getElementById('searchEmpty');
        if (!empty) {
            empty = document.createElement('p');
            empty.id = 'searchEmpty';
            empty.className = 'empty-state';
            empty.textContent = 'Brak wyników dla podanej frazy.';
            container.after(empty);
        }
        empty.style.display = show ? '' : 'none';
    }

    function currentView() {
        try { return localStorage.getItem(KEY) || 'grid'; } catch (e) { return 'grid'; }
    }

    if (input) {
        input.addEventListener('input', function () {
            doSearch(this.value.toLowerCase().trim(), currentView());
        });
    }

    if (toggle && viewGrid && viewTable) {
        function setView(v) {
            viewGrid.hidden  = (v === 'table');
            viewTable.hidden = (v === 'grid');
            toggle.querySelectorAll('.view-toggle-btn').forEach(function (b) {
                b.classList.toggle('active', b.dataset.view === v);
            });
            if (input && input.value) {
                input.value = '';
                doSearch('', v);
            }
            try { localStorage.setItem(KEY, v); } catch (e) {}
        }

        toggle.addEventListener('click', function (e) {
            var btn = e.target.closest('.view-toggle-btn');
            if (btn) setView(btn.dataset.view);
        });

        try { setView(localStorage.getItem(KEY) || 'grid'); } catch (e) { setView('grid'); }
    }
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
