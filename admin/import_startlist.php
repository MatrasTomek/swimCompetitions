<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/livetiming_cache.php';
$cache_status = ltcache_status();
?>
<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Import listy startowej — Panel admina</title>
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/style.css?v=<?= CSS_VERSION ?>">
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/admin.css?v=<?= CSS_VERSION ?>">
</head>
<body>
<header class="admin-header">
    <div class="container">
        <span class="admin-logo">Panel admina</span>
        <nav>
            <a href="<?= BASE_URL ?>/admin/lista.php">Lista zawodów</a>
            <a href="<?= BASE_URL ?>/admin/dodaj.php">Dodaj zawody</a>
            <a href="<?= BASE_URL ?>/admin/import_startlist.php" class="active">Import PDF</a>
            <a href="<?= BASE_URL ?>/admin/live.php">Wyniki LENEX</a>
            <a href="<?= BASE_URL ?>/index.php">Strona główna</a>
            <a href="<?= BASE_URL ?>/admin/logout.php">Wyloguj</a>
        </nav>
    </div>
</header>
<main class="container">
    <div class="page-header">
        <h1>Import listy startowej z livetiming.pl</h1>
        <a href="<?= BASE_URL ?>/admin/lista.php" class="btn btn-outline">← Wróć do listy</a>
    </div>

    <div class="admin-form" style="max-width:600px">

        <!-- Step 0: Search livetiming.pl -->
        <div id="step-search">
            <?php
            $cs = $cache_status;
            if (!$cs['exists']) {
                $cache_color  = '#f0a800';
                $cache_label  = 'brak cache';
                $cache_detail = '— wyszukiwanie wymaga zbudowania cache';
            } elseif (!$cs['is_fresh']) {
                $cache_color  = '#fa8030';
                $cache_label  = 'nieaktualny';
                $cache_detail = '· ' . $cs['count'] . ' zawodów · ' . $cs['age_hours'] . 'h temu';
            } else {
                $cache_color  = '#4caf50';
                $cache_label  = 'aktualny';
                $cache_detail = '· ' . $cs['count'] . ' zawodów · ' . $cs['age_hours'] . 'h temu';
            }
            ?>
            <div style="background:#0a0a0a;border:1px solid #1e1e1e;border-radius:5px;padding:.4rem .75rem;font-size:.78rem;color:#555;display:flex;align-items:center;justify-content:space-between;gap:.5rem;margin-bottom:.85rem;flex-wrap:wrap">
                <span id="cache-info">Cache zawodów: <span id="cache-label" style="color:<?= h($cache_color) ?>"><?= h($cache_label) ?></span> <span id="cache-detail"><?= h($cache_detail) ?></span></span>
                <button id="btn-cache-refresh" type="button" style="font-size:.72rem;background:transparent;border:1px solid #2a2a2a;color:#555;border-radius:4px;padding:2px 8px;cursor:pointer;white-space:nowrap">↺ Odśwież cache</button>
            </div>

            <div class="form-group" style="margin-bottom:.6rem">
                <label for="search-input">Wyszukaj zawody na livetiming.pl</label>
                <input type="text" id="search-input" autocomplete="off"
                    placeholder="Wpisz nazwę miasta lub zawodów...">
                <span class="form-hint">Wpisz min. 2 znaki — kliknij wynik, aby pobrać listę startową.</span>
            </div>

            <div id="search-results" style="margin-bottom:.75rem"></div>

            <div style="display:flex;align-items:center;gap:.6rem;color:#333;font-size:.78rem;margin:.85rem 0">
                <div style="flex:1;height:1px;background:#1e1e1e"></div>
                lub podaj URL bezpośrednio
                <div style="flex:1;height:1px;background:#1e1e1e"></div>
            </div>
        </div>

        <!-- Step 1: URL + club form -->
        <div id="step-fetch">
            <div class="form-group">
                <label for="contest-url">Link do zawodów (livetiming.pl)</label>
                <input type="text" id="contest-url" name="contest_url"
                    placeholder="https://live.livetiming.pl/zak/2026/02_21_oswiecim/startowa.pdf">
                <span class="form-hint">Wklej link do PDF z listą startową lub link do strony zawodów na livetiming.pl.</span>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label for="klub-name">Nazwa klubu</label>
                    <input type="text" id="klub-name" name="klub"
                        value="Olimpijczyk Brzesko">
                    <span class="form-hint">Filtruje startujących po nazwie klubu.</span>
                </div>
                <div class="form-group">
                    <label for="basen-size">Długość basenu</label>
                    <select id="basen-size" name="basen" class="form-select">
                        <option value="25m" selected>25m</option>
                        <option value="50m">50m</option>
                    </select>
                </div>
            </div>
            <div class="form-actions">
                <button id="btn-fetch" class="btn btn-primary">Pobierz listę startową</button>
            </div>
            <div id="fetch-status" style="margin-top:1rem;font-size:.88rem"></div>
        </div>

        <!-- Step 2: Preview + editable metadata (hidden until fetch succeeds) -->
        <div id="step-save" style="display:none">
            <hr>
            <h2 style="color:#f0a800;font-size:1rem;margin:1.25rem 0 .75rem">Podgląd i metadane</h2>

            <div id="import-stats" style="background:#111;border:1px solid #2a2a2a;border-radius:6px;padding:.75rem 1rem;margin-bottom:1.25rem;font-size:.88rem;color:#aaa"></div>

            <div class="form-group">
                <label for="meta-nazwa">Nazwa zawodów *</label>
                <input type="text" id="meta-nazwa" maxlength="255">
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label for="meta-miejsce">Miejscowość</label>
                    <input type="text" id="meta-miejsce" maxlength="100">
                </div>
                <div class="form-group">
                    <label for="meta-data">Data (np. 9-10/5/2026)</label>
                    <input type="text" id="meta-data" maxlength="30">
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label for="meta-klub">Klub</label>
                    <input type="text" id="meta-klub" maxlength="255">
                </div>
                <div class="form-group">
                    <label for="meta-basen">Basen</label>
                    <select id="meta-basen" class="form-select">
                        <option value="25m">25m</option>
                        <option value="50m">50m</option>
                    </select>
                </div>
            </div>

            <div class="form-actions">
                <button id="btn-save" class="btn btn-primary">Zapisz listę startową</button>
                <button id="btn-reset" class="btn btn-outline">Zacznij od nowa</button>
            </div>
            <div id="save-status" style="margin-top:1rem;font-size:.88rem"></div>
        </div>

        <!-- Raw text debug toggle -->
        <div id="raw-text-section" style="display:none;margin-top:1.5rem">
            <button id="btn-toggle-raw" class="btn btn-secondary" style="font-size:.8rem">
                Pokaż surowy tekst PDF
            </button>
            <pre id="raw-text-box" style="display:none;margin-top:.75rem;background:#0d0d0d;border:1px solid #2a2a2a;border-radius:6px;padding:.75rem;font-size:.72rem;color:#888;max-height:300px;overflow:auto;white-space:pre-wrap;word-break:break-all"></pre>
        </div>
    </div>
</main>

<script>
(function () {
    var parsedZawody = null;

    var btnFetch  = document.getElementById('btn-fetch');
    var btnSave   = document.getElementById('btn-save');
    var btnReset  = document.getElementById('btn-reset');
    var btnToggle = document.getElementById('btn-toggle-raw');
    var rawBox    = document.getElementById('raw-text-box');

    // ── Search ──────────────────────────────────────────────────────────────
    var searchInput     = document.getElementById('search-input');
    var searchResults   = document.getElementById('search-results');
    var btnCacheRefresh = document.getElementById('btn-cache-refresh');
    var cacheLabel      = document.getElementById('cache-label');
    var cacheDetail     = document.getElementById('cache-detail');
    var searchTimer     = null;

    var CAT_LABEL = {regional: 'okręgowe', national: 'centralne', calendar: 'kalendarz', international: 'międzynarodowe'};
    var CAT_COLOR = {regional: '#6ab0ee', national: '#a07eee', calendar: '#6dcfa0', international: '#e8a060'};

    function renderSearchResults(items) {
        if (!Array.isArray(items) || items.length === 0) {
            searchResults.innerHTML =
                '<p style="color:#555;font-size:.82rem;padding:.35rem 0">' +
                'Nie znaleziono. Spróbuj innej frazy lub ' +
                '<button type="button" id="btn-empty-refresh" style="background:none;border:none;color:#f0a800;cursor:pointer;padding:0;font-size:.82rem;text-decoration:underline">odśwież cache</button>.' +
                '</p>';
            var b = document.getElementById('btn-empty-refresh');
            if (b) b.addEventListener('click', doRefreshCache);
            return;
        }

        var html = '<div style="display:flex;flex-direction:column;gap:.35rem">';
        items.forEach(function (item) {
            var cat   = CAT_LABEL[item.category] || item.category || '';
            var color = CAT_COLOR[item.category]  || '#888';
            html +=
                '<div class="lt-result-card" data-uuid="' + escHtml(item.uuid) + '"' +
                ' style="background:#161616;border:1px solid #252525;border-radius:6px;' +
                'padding:.55rem .85rem;cursor:pointer;display:flex;align-items:center;gap:.7rem;' +
                'transition:border-color .12s">' +
                '<div style="flex:1;min-width:0">' +
                '<div style="color:#e8e8e8;font-size:.85rem;font-weight:600;' +
                'white-space:nowrap;overflow:hidden;text-overflow:ellipsis">' + escHtml(item.name) + '</div>' +
                '<div style="color:#555;font-size:.74rem;margin-top:.1rem">' +
                escHtml(item.city || '') + (item.city && item.date ? ' · ' : '') + escHtml(item.date || '') +
                '</div></div>' +
                (cat ? '<span style="font-size:.66rem;font-weight:700;letter-spacing:.05em;' +
                'text-transform:uppercase;padding:2px 6px;border-radius:3px;background:#111;' +
                'color:' + color + ';flex-shrink:0">' + escHtml(cat) + '</span>' : '') +
                '</div>';
        });
        html += '</div>';
        searchResults.innerHTML = html;

        Array.prototype.forEach.call(
            searchResults.querySelectorAll('.lt-result-card'),
            function (card) {
                card.addEventListener('mouseenter', function () { this.style.borderColor = '#f0a800'; });
                card.addEventListener('mouseleave', function () { this.style.borderColor = '#252525'; });
                card.addEventListener('click', function () {
                    var uuid = this.dataset.uuid;
                    document.getElementById('contest-url').value =
                        'https://livetiming.pl/contest/' + uuid;
                    // Scroll into view then trigger fetch
                    document.getElementById('step-fetch').scrollIntoView({behavior: 'smooth', block: 'nearest'});
                    setTimeout(function () { btnFetch.click(); }, 120);
                });
            }
        );
    }

    if (searchInput) {
        searchInput.addEventListener('input', function () {
            clearTimeout(searchTimer);
            var q = this.value.trim();
            if (q.length < 2) { searchResults.innerHTML = ''; return; }
            searchTimer = setTimeout(function () {
                fetch('<?= BASE_URL ?>/api/competitions_search.php?q=' + encodeURIComponent(q))
                    .then(function (r) { return r.json(); })
                    .then(function (data) { renderSearchResults(data); })
                    .catch(function () {
                        searchResults.innerHTML = '<p style="color:#e53935;font-size:.82rem">Błąd wyszukiwania.</p>';
                    });
            }, 300);
        });
    }

    function doRefreshCache() {
        if (btnCacheRefresh) { btnCacheRefresh.disabled = true; btnCacheRefresh.textContent = 'Odświeżanie…'; }
        if (cacheLabel)  { cacheLabel.style.color = '#888'; cacheLabel.textContent = 'odświeżanie…'; }
        if (cacheDetail) cacheDetail.textContent = '';

        fetch('<?= BASE_URL ?>/api/ltcache_refresh.php', {method: 'POST'})
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (btnCacheRefresh) { btnCacheRefresh.disabled = false; btnCacheRefresh.textContent = '↺ Odśwież cache'; }
                var s = data.status || {};
                if (cacheLabel) {
                    if (s.is_fresh) {
                        cacheLabel.style.color = '#4caf50';
                        cacheLabel.textContent = 'aktualny';
                    } else {
                        cacheLabel.style.color = '#fa8030';
                        cacheLabel.textContent = 'nieaktualny';
                    }
                }
                if (cacheDetail && s.count !== undefined) {
                    cacheDetail.textContent = '· ' + s.count + ' zawodów · tylko co odświeżony';
                }
                // Re-run current search with fresh data
                if (searchInput && searchInput.value.trim().length >= 2) {
                    searchInput.dispatchEvent(new Event('input'));
                }
            })
            .catch(function () {
                if (btnCacheRefresh) { btnCacheRefresh.disabled = false; btnCacheRefresh.textContent = '↺ Odśwież cache'; }
                if (cacheLabel) { cacheLabel.style.color = '#e53935'; cacheLabel.textContent = 'błąd odświeżenia'; }
            });
    }

    if (btnCacheRefresh) {
        btnCacheRefresh.addEventListener('click', doRefreshCache);
    }

    // ── Fetch ──────────────────────────────────────────────────────────────
    btnFetch.addEventListener('click', function () {
        var url  = document.getElementById('contest-url').value.trim();
        var klub = document.getElementById('klub-name').value.trim();
        var bas  = document.getElementById('basen-size').value;
        var status = document.getElementById('fetch-status');

        if (!url)  { status.style.color = '#f44'; status.textContent = 'Wklej URL zawodów.'; return; }
        if (!klub) { status.style.color = '#f44'; status.textContent = 'Podaj nazwę klubu.'; return; }

        btnFetch.disabled = true;
        btnFetch.textContent = 'Pobieranie…';
        status.style.color = '#aaa';
        status.textContent = 'Łączenie z livetiming.pl i pobieranie PDF…';

        fetch('<?= BASE_URL ?>/api/import_startlist.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'preview', contest_url: url, klub: klub, basen: bas }),
        })
        .then(function (r) { return r.json(); })
        .then(function (data) {
            btnFetch.disabled = false;
            btnFetch.textContent = 'Pobierz listę startową';

            if (data.error) {
                status.style.color = '#f44';
                status.textContent = data.error;
                if (data.raw_text) showRaw(data.raw_text);
                return;
            }

            status.textContent = '';
            parsedZawody = data.zawody;

            // Fill metadata
            document.getElementById('meta-nazwa').value  = data.zawody.nazwa   || '';
            document.getElementById('meta-miejsce').value = data.zawody.miejsce || '';
            document.getElementById('meta-data').value   = data.zawody.data    || '';
            document.getElementById('meta-klub').value   = data.zawody.klub    || '';
            document.getElementById('meta-basen').value  = data.zawody.basen   || '25m';

            // Stats
            var s = data.stats;
            document.getElementById('import-stats').innerHTML =
                '<strong style="color:#eee">' + s.starts + '</strong> startów · ' +
                '<strong style="color:#eee">' + s.athletes + '</strong> zawodników · ' +
                '<strong style="color:#eee">' + s.blocks + '</strong> bloków' +
                (data.pdf_url ? ' &nbsp;·&nbsp; <a href="' + escHtml(data.pdf_url) + '" target="_blank" style="color:#888;font-size:.8rem">PDF ↗</a>' : '');

            document.getElementById('step-save').style.display = '';
            if (data.raw_text) showRaw(data.raw_text);
        })
        .catch(function (e) {
            btnFetch.disabled = false;
            btnFetch.textContent = 'Pobierz listę startową';
            document.getElementById('fetch-status').style.color = '#f44';
            document.getElementById('fetch-status').textContent = 'Błąd połączenia: ' + e.message;
        });
    });

    // ── Save ───────────────────────────────────────────────────────────────
    btnSave.addEventListener('click', function () {
        if (!parsedZawody) return;

        var status = document.getElementById('save-status');

        // Apply edits from form
        parsedZawody.nazwa   = document.getElementById('meta-nazwa').value.trim();
        parsedZawody.miejsce = document.getElementById('meta-miejsce').value.trim();
        parsedZawody.data    = document.getElementById('meta-data').value.trim();
        parsedZawody.klub    = document.getElementById('meta-klub').value.trim();
        parsedZawody.basen   = document.getElementById('meta-basen').value;

        if (!parsedZawody.nazwa) {
            status.style.color = '#f44';
            status.textContent = 'Podaj nazwę zawodów.';
            return;
        }

        btnSave.disabled = true;
        btnSave.textContent = 'Zapisywanie…';
        status.style.color = '#aaa';
        status.textContent = '';

        fetch('<?= BASE_URL ?>/api/import_startlist.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'save', zawody: parsedZawody }),
        })
        .then(function (r) { return r.json(); })
        .then(function (data) {
            if (data.error) {
                btnSave.disabled = false;
                btnSave.textContent = 'Zapisz listę startową';
                status.style.color = '#f44';
                status.textContent = data.error;
                return;
            }
            window.location.href = data.redirect;
        })
        .catch(function (e) {
            btnSave.disabled = false;
            btnSave.textContent = 'Zapisz listę startową';
            status.style.color = '#f44';
            status.textContent = 'Błąd zapisu: ' + e.message;
        });
    });

    // ── Reset ──────────────────────────────────────────────────────────────
    btnReset.addEventListener('click', function () {
        parsedZawody = null;
        document.getElementById('step-save').style.display = 'none';
        document.getElementById('fetch-status').textContent = '';
        document.getElementById('raw-text-section').style.display = 'none';
        rawBox.style.display = 'none';
    });

    // ── Raw text toggle ────────────────────────────────────────────────────
    btnToggle.addEventListener('click', function () {
        if (rawBox.style.display === 'none') {
            rawBox.style.display = 'block';
            btnToggle.textContent = 'Ukryj surowy tekst PDF';
        } else {
            rawBox.style.display = 'none';
            btnToggle.textContent = 'Pokaż surowy tekst PDF';
        }
    });

    function showRaw(text) {
        document.getElementById('raw-text-section').style.display = '';
        rawBox.textContent = text;
    }

    function escHtml(s) {
        return s.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
    }
}());
</script>
</body>
</html>
