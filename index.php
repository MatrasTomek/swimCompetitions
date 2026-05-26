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
        <div class="view-toggles">
            <div class="view-toggle" id="scope-toggle">
                <button class="view-toggle-btn active" data-scope="najnowsze">Najnowsze</button>
                <button class="view-toggle-btn" data-scope="wszystko">Wszystko</button>
            </div>
            <div class="view-toggle" id="view-toggle">
                <button class="view-toggle-btn active" data-view="grid">⊞ Karty</button>
                <button class="view-toggle-btn" data-view="table">☰ Tabela</button>
            </div>
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
                <?php foreach ($zawody as $idx => $z): ?>
                <article class="competition-card" data-recent="<?= $idx < 4 ? 'true' : 'false' ?>" data-search="<?= h(function_exists('mb_strtolower') ? mb_strtolower($z['nazwa'] . ' ' . $z['data'] . ' ' . $z['miejsce'] . ' ' . $z['klub']) : strtolower($z['nazwa'] . ' ' . $z['data'] . ' ' . $z['miejsce'] . ' ' . $z['klub'])) ?>">
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
                    <?php foreach ($zawody as $idx => $z): ?>
                        <tr data-recent="<?= $idx < 4 ? 'true' : 'false' ?>" data-search="<?= h(function_exists('mb_strtolower') ? mb_strtolower($z['nazwa'] . ' ' . $z['data'] . ' ' . $z['miejsce'] . ' ' . $z['klub']) : strtolower($z['nazwa'] . ' ' . $z['data'] . ' ' . $z['miejsce'] . ' ' . $z['klub'])) ?>">
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
    var input      = document.getElementById('search');
    var toggle     = document.getElementById('view-toggle');
    var scopeEl    = document.getElementById('scope-toggle');
    var viewGrid   = document.getElementById('view-grid');
    var viewTable  = document.getElementById('view-table');

    var VIEW_KEY  = 'swim-index-view';
    var SCOPE_KEY = 'swim-index-scope';

    function currentView()  { try { return localStorage.getItem(VIEW_KEY)  || 'grid';      } catch (e) { return 'grid';      } }
    function currentScope() { try { return localStorage.getItem(SCOPE_KEY) || 'najnowsze'; } catch (e) { return 'najnowsze'; } }

    function doFilter(q, view, scope) {
        var selector = view === 'table'
            ? '#view-table tbody tr[data-search]'
            : '#competitions-grid .competition-card';
        var items = document.querySelectorAll(selector);
        var visible = 0;
        items.forEach(function (el) {
            var matchSearch = !q || el.dataset.search.indexOf(q) !== -1;
            var matchScope  = scope === 'wszystko' || el.dataset.recent === 'true';
            var show = matchSearch && matchScope;
            el.style.display = show ? '' : 'none';
            if (show) visible++;
        });
        var container = view === 'table' ? viewTable : viewGrid;
        showEmpty(q && visible === 0, container);
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

    if (input) {
        input.addEventListener('input', function () {
            doFilter(this.value.toLowerCase().trim(), currentView(), currentScope());
        });
    }

    if (scopeEl) {
        function setScope(s) {
            scopeEl.querySelectorAll('.view-toggle-btn').forEach(function (b) {
                b.classList.toggle('active', b.dataset.scope === s);
            });
            if (input && input.value) { input.value = ''; }
            try { localStorage.setItem(SCOPE_KEY, s); } catch (e) {}
            doFilter('', currentView(), s);
        }

        scopeEl.addEventListener('click', function (e) {
            var btn = e.target.closest('.view-toggle-btn');
            if (btn) setScope(btn.dataset.scope);
        });
    }

    if (toggle && viewGrid && viewTable) {
        function setView(v) {
            viewGrid.hidden  = (v === 'table');
            viewTable.hidden = (v === 'grid');
            toggle.querySelectorAll('.view-toggle-btn').forEach(function (b) {
                b.classList.toggle('active', b.dataset.view === v);
            });
            if (input && input.value) { input.value = ''; }
            try { localStorage.setItem(VIEW_KEY, v); } catch (e) {}
            doFilter('', v, currentScope());
        }

        toggle.addEventListener('click', function (e) {
            var btn = e.target.closest('.view-toggle-btn');
            if (btn) setView(btn.dataset.view);
        });

        try { setView(localStorage.getItem(VIEW_KEY) || 'grid'); } catch (e) { setView('grid'); }
    }

    // Apply initial scope after view is set
    if (scopeEl) {
        try { var s = localStorage.getItem(SCOPE_KEY) || 'najnowsze';
              scopeEl.querySelectorAll('.view-toggle-btn').forEach(function (b) {
                  b.classList.toggle('active', b.dataset.scope === s);
              });
              doFilter('', currentView(), s);
        } catch (e) {}
    }
})();
</script>

<footer class="site-footer">
    <div class="container">
        <p>&copy; <?= date('Y') ?> OlimpijczyK Proszówki</p>
        <span class="footer-madeby">madeBy: <a href="https://www.nd-soft.pl/" target="_blank" rel="noopener">ndsoft</a></span>
    </div>
</footer>
</body>
</html>
