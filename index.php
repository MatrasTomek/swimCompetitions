<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/livetiming_cache.php';

$cache_status = ltcache_status();
?>
<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Szukaj zawodników — Olimpijczyk Proszówki</title>
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/style.css?v=<?= CSS_VERSION ?>">
    <style>
        .search-section { max-width: 640px; margin: 0 auto 2rem; }
        .search-section h1 { font-size: 1.5rem; margin-bottom: 1.25rem; }
        .field-group { margin-bottom: 1rem; position: relative; }
        .field-group label { display: block; font-size: .85rem; font-weight: 600; margin-bottom: .35rem; color: var(--text-muted, #555); }
        .field-group input[type="text"] { width: 100%; }
        .suggestions-dropdown { position: absolute; left: 0; right: 0; top: calc(100% + 2px); background: #fff; border: 1px solid #ccc; border-radius: 6px; box-shadow: 0 4px 12px rgba(0,0,0,.12); z-index: 100; max-height: 280px; overflow-y: auto; }
        .suggestions-dropdown li { list-style: none; padding: .6rem .85rem; cursor: pointer; border-bottom: 1px solid #f0f0f0; font-size: .92rem; }
        .suggestions-dropdown li:last-child { border-bottom: none; }
        .suggestions-dropdown li:hover, .suggestions-dropdown li.active { background: #f5f9ff; }
        .sug-name { font-weight: 600; }
        .sug-meta { font-size: .8rem; color: #666; margin-top: 2px; }
        .search-form-row { display: flex; gap: .75rem; align-items: flex-end; flex-wrap: wrap; }
        .search-form-row .field-group { flex: 1; min-width: 200px; margin-bottom: 0; }
        #results-section .results-header { margin-bottom: 1.25rem; }
        .cache-hint { font-size: .82rem; color: #888; margin-top: .4rem; }
    </style>
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
    <section class="search-section">
        <h1>Znajdź zawodników klubu</h1>

        <form id="search-form" autocomplete="off">
            <div class="field-group">
                <label for="comp-input">Zawody</label>
                <input id="comp-input" type="text"
                       placeholder="Wpisz nazwę zawodów lub miasto…"
                       spellcheck="false">
                <input id="comp-url" type="hidden">
                <ul id="comp-suggestions" class="suggestions-dropdown" hidden></ul>
                <?php if (!$cache_status['exists']): ?>
                    <p class="cache-hint">Autouzupełnianie wymaga zbudowania cache zawodów — <a href="<?= BASE_URL ?>/admin/livetiming_cache.php">odśwież w panelu admina</a>. Możesz też wkleić bezpośredni URL z livetiming.pl (np. https://livetiming.pl/contest/…).</p>
                <?php elseif (!$cache_status['is_fresh']): ?>
                    <p class="cache-hint">Cache zawodów ma <?= $cache_status['age_hours'] ?>h — rozważ <a href="<?= BASE_URL ?>/admin/livetiming_cache.php">odświeżenie</a>.</p>
                <?php endif; ?>
            </div>

            <div class="search-form-row">
                <div class="field-group">
                    <label for="club-input">Klub</label>
                    <input id="club-input" type="text"
                           placeholder="np. Olimpijczyk Brzesko">
                </div>
                <div class="field-group" style="flex:0">
                    <button type="submit" class="btn btn-primary" style="white-space:nowrap">Szukaj</button>
                </div>
            </div>
        </form>

        <div id="search-status" style="margin-top:.75rem;font-size:.9rem;color:#555" hidden></div>
    </section>

    <section id="results-section" hidden></section>
</main>

<footer class="site-footer">
    <div class="container">
        <p>&copy; <?= date('Y') ?> Olimpijczyk Proszówki</p>
        <span class="footer-madeby">madeBy: <a href="https://www.nd-soft.pl/" target="_blank" rel="noopener">ndsoft</a></span>
    </div>
</footer>

<script>
(function () {
'use strict';

var BASE = '<?= BASE_URL ?>';

// ── format_konkurencja (mirrors PHP) ─────────────────────────────────────────
function formatKonkurencja(k, nr) {
    if (!k) return nr ? String(nr) : '';
    var first = k.trim().charAt(0).toUpperCase();
    var rest  = k.replace(/^[^,]+,\s*/, '');
    rest = rest.replace(/grzbietowy/ig, 'grzbiet')
               .replace(/motylkowy/ig,  'motyl')
               .replace(/klasyczny/ig,  'klasyk');
    return first + nr + ' ' + rest;
}

function escHtml(s) {
    return String(s)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;');
}

// ── Autocomplete ──────────────────────────────────────────────────────────────
var compInput   = document.getElementById('comp-input');
var compUrl     = document.getElementById('comp-url');
var sugList     = document.getElementById('comp-suggestions');
var activeIdx   = -1;
var debTimer    = null;

function clearSuggestions() {
    sugList.innerHTML = '';
    sugList.hidden = true;
    activeIdx = -1;
}

function showSuggestions(items) {
    sugList.innerHTML = '';
    if (!items.length) { sugList.hidden = true; return; }
    items.forEach(function (c, i) {
        var li = document.createElement('li');
        li.innerHTML = '<div class="sug-name">' + escHtml(c.name) + '</div>' +
                       '<div class="sug-meta">' + escHtml(c.date) + (c.city ? ' · ' + escHtml(c.city) : '') + '</div>';
        li.addEventListener('mousedown', function (e) {
            e.preventDefault(); // prevent blur before click
            selectSuggestion(c);
        });
        sugList.appendChild(li);
    });
    sugList.hidden = false;
    activeIdx = -1;
}

function selectSuggestion(c) {
    compInput.value = c.name + (c.date ? ' (' + c.date + ')' : '');
    compUrl.value   = 'https://livetiming.pl/contest/' + c.uuid;
    clearSuggestions();
}

function navigateSuggestions(dir) {
    var items = sugList.querySelectorAll('li');
    if (!items.length) return;
    if (activeIdx >= 0) items[activeIdx].classList.remove('active');
    activeIdx = Math.max(-1, Math.min(items.length - 1, activeIdx + dir));
    if (activeIdx >= 0) items[activeIdx].classList.add('active');
}

compInput.addEventListener('input', function () {
    var q = this.value.trim();
    compUrl.value = ''; // clear selection when user types again
    clearTimeout(debTimer);
    if (q.length < 2) { clearSuggestions(); return; }
    debTimer = setTimeout(function () {
        fetch(BASE + '/api/competitions_search.php?q=' + encodeURIComponent(q))
            .then(function (r) { return r.json(); })
            .then(showSuggestions)
            .catch(function () { clearSuggestions(); });
    }, 300);
});

compInput.addEventListener('keydown', function (e) {
    if (sugList.hidden) return;
    if (e.key === 'ArrowDown') { e.preventDefault(); navigateSuggestions(1); }
    else if (e.key === 'ArrowUp') { e.preventDefault(); navigateSuggestions(-1); }
    else if (e.key === 'Enter' && activeIdx >= 0) {
        e.preventDefault();
        sugList.querySelectorAll('li')[activeIdx].dispatchEvent(new MouseEvent('mousedown'));
    }
    else if (e.key === 'Escape') { clearSuggestions(); }
});

compInput.addEventListener('blur', function () {
    setTimeout(clearSuggestions, 150);
});

// ── Form submit ───────────────────────────────────────────────────────────────
var searchForm   = document.getElementById('search-form');
var statusEl     = document.getElementById('search-status');
var resultsEl    = document.getElementById('results-section');
var clubInput    = document.getElementById('club-input');

searchForm.addEventListener('submit', function (e) {
    e.preventDefault();

    var contestUrl = compUrl.value.trim() || compInput.value.trim();
    var club       = clubInput.value.trim();

    if (!contestUrl) {
        showStatus('Wpisz nazwę zawodów lub wklej URL z livetiming.pl.', 'error');
        return;
    }
    if (!club) {
        showStatus('Wpisz nazwę klubu.', 'error');
        return;
    }

    showStatus('Pobieranie listy startowej…', 'loading');
    resultsEl.hidden = true;
    resultsEl.innerHTML = '';

    fetch(BASE + '/api/search_startlist.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ contest_url: contestUrl, club: club }),
    })
    .then(function (r) { return r.json(); })
    .then(function (data) {
        if (data.status === 'ok') {
            statusEl.hidden = true;
            renderResults(data.zawody, club);
        } else if (data.status === 'no_athletes') {
            showStatus(data.message || 'Brak zawodników z tego klubu.', 'warn');
        } else {
            showStatus((data.error || 'Nie udało się pobrać listy startowej.') +
                       ' Sprawdź czy zawody są dostępne na livetiming.pl.', 'error');
        }
    })
    .catch(function () {
        showStatus('Błąd połączenia. Spróbuj ponownie.', 'error');
    });
});

function showStatus(msg, type) {
    statusEl.textContent = msg;
    statusEl.style.color = type === 'error' ? '#c0392b' : type === 'warn' ? '#e67e22' : '#555';
    statusEl.hidden = false;
}

// ── Results rendering ─────────────────────────────────────────────────────────
function renderResults(zawody, clubFilter) {
    var html = '';

    html += '<div class="results-header">' +
            '<h1>' + escHtml(zawody.nazwa || 'Lista startowa') + '</h1>';
    var parts = [zawody.klub, zawody.miejsce, zawody.data].filter(Boolean);
    if (parts.length) html += '<div class="results-meta">' + escHtml(parts.join(' · ')) + '</div>';
    html += '</div>';

    var bloki = zawody.bloki || [];

    if (!bloki.length) {
        html += '<p class="empty-state">Brak bloków startowych.</p>';
    } else {
        bloki.forEach(function (blok) {
            var starty = blok.starty || [];
            html += '<section class="block">' +
                    '<div class="block-header">' +
                    '<h2>Blok ' + escHtml(blok.blok) + '</h2>' +
                    '<div class="block-meta">';
            if (blok.data)       html += '<span>📅 ' + escHtml(blok.data) + '</span>';
            if (blok.godz_start) html += '<span>🕐 Start: ' + escHtml(blok.godz_start) + '</span>';
            html += '<span>' + starty.length + ' start' + (starty.length === 1 ? '' : starty.length < 5 ? 'y' : 'ów') + '</span>' +
                    '</div></div>';

            if (starty.length) {
                html += '<div class="table-wrapper"><table class="results-table">' +
                        '<thead><tr>' +
                        '<th>Zawodnik</th><th>Konk.</th><th>Seria</th>' +
                        '<th class="text-center">Godz.</th><th class="text-center col-tor">T</th>' +
                        '</tr></thead><tbody>';

                starty.forEach(function (s) {
                    var parts2 = (s.imie || '').trim().split(/\s+/);
                    var nazwisko = parts2[0] || '';
                    var imie     = parts2.slice(1).join(' ');
                    var konk     = formatKonkurencja(s.konkurencja || '', parseInt(s.konkurencja_nr, 10) || 0);
                    var search   = (s.imie + ' ' + konk).toLowerCase();

                    html += '<tr data-search="' + escHtml(search) + '" data-blok="' + escHtml(blok.blok) + '">' +
                            '<td class="athlete" data-label="Zawodnik">' +
                            '<span class="last-name">' + escHtml(nazwisko) + '</span>';
                    if (imie) html += ' <span class="first-name">' + escHtml(imie) + '</span>';
                    if (s.czas) {
                        html += ' <button class="btn-show-time" data-czas="' + escHtml(s.czas) + '" data-type="seed">Pokaż czas</button>';
                    }
                    html += '</td>' +
                            '<td data-label="Konk.">' + escHtml(konk) + '</td>' +
                            '<td data-label="Seria">' + escHtml(s.seria || '') + '</td>' +
                            '<td class="text-center" data-label="Godz.">' + escHtml(s.godz || '') + '</td>' +
                            '<td class="text-center" data-label="Tor">' + escHtml(String(s.tor || '')) + '</td>' +
                            '</tr>';
                });

                html += '</tbody></table></div>';
            } else {
                html += '<p class="empty-state" style="padding:1rem 1.25rem">Brak startów w tym bloku.</p>';
            }
            html += '</section>';
        });
    }

    html += '<div class="back-link" style="margin-top:1.5rem">' +
            '<button type="button" onclick="document.getElementById(\'results-section\').hidden=true;document.getElementById(\'search-status\').hidden=true" class="btn btn-outline">← Nowe wyszukiwanie</button>' +
            '</div>';

    resultsEl.innerHTML = html;
    resultsEl.hidden = false;
    resultsEl.scrollIntoView({ behavior: 'smooth', block: 'start' });
}

// ── "Show time" button (same as lista_startowa.php) ─────────────────────────
document.addEventListener('click', function (e) {
    var btn = e.target;
    if (!btn.classList.contains('btn-show-time')) return;
    var tr = btn.closest('tr');
    if (!tr) return;
    var nextTr = tr.nextElementSibling;
    if (nextTr && nextTr.classList.contains('tr-time-display')) {
        nextTr.remove();
        btn.textContent = 'Pokaż czas';
        return;
    }
    var text = btn.dataset.czas || '';
    var cls  = 'time-display time-seed';
    btn.textContent = 'Ukryj czas';
    var newTr = document.createElement('tr');
    newTr.className = 'tr-time-display';
    var td = document.createElement('td');
    td.colSpan = tr.cells.length;
    var span = document.createElement('span');
    span.className = cls;
    span.textContent = text;
    td.appendChild(span);
    newTr.appendChild(td);
    tr.insertAdjacentElement('afterend', newTr);
});

})();
</script>
</body>
</html>
