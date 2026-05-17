<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/functions.php';

$filepath = safe_json_path($_GET['f'] ?? '');
if (!$filepath) {
    http_response_code(404);
    die('Nie znaleziono listy startowej.');
}

$json = json_decode(file_get_contents($filepath), true);
if ($json === null) {
    http_response_code(500);
    die('Błąd odczytu pliku listy startowej.');
}

$bloki     = $json['bloki'] ?? [];
$json_slug = basename($filepath, '.json');
?>
<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= h($json['nazwa'] ?? 'Lista startowa') ?> — Olimpijczyk Proszówki</title>
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/style.css?v=<?= CSS_VERSION ?>">
</head>
<body>
<header class="site-header">
    <div class="container">
        <div class="site-logo">
            <img src="<?= BASE_URL ?>/logo.jpg" alt="Olimpijczyk Proszówki" class="site-logo-img">
        </div>
        <nav class="site-nav">
            <a href="<?= BASE_URL ?>/index.php">← Wszystkie zawody</a>
        </nav>
    </div>
</header>

<main class="container">
    <div class="results-header">
        <h1><?= h($json['nazwa'] ?? 'Lista startowa') ?></h1>
        <div class="results-meta">
            <?php
            $parts = array_filter([
                $json['klub']    ?? '',
                $json['miejsce'] ?? '',
                $json['data']    ?? '',
            ]);
            echo h(implode(' · ', $parts));
            ?>
        </div>
    </div>

    <?php if (!empty($bloki)): ?>
    <div class="search-bar">
        <input type="search" id="athlete-search"
               placeholder="Szukaj zawodnika lub startu…"
               autocomplete="off" spellcheck="false">
    </div>

    <section id="search-results" hidden>
        <h2 style="margin-bottom:.75rem">Wyniki wyszukiwania</h2>
        <div class="table-wrapper">
            <table class="results-table">
                <thead>
                    <tr>
                        <th>Zawodnik</th>
                        <th>Konk.</th>
                        <th>Blok</th>
                        <th>Seria</th>
                        <th class="text-center">Godz.</th>
                        <th class="text-center col-tor">T</th>
                    </tr>
                </thead>
                <tbody id="search-tbody"></tbody>
            </table>
        </div>
        <p id="search-empty" class="empty-state" hidden>Brak wyników.</p>
    </section>
    <?php endif; ?>

    <?php if (empty($bloki)): ?>
        <p class="empty-state">Brak bloków startowych w pliku.</p>
    <?php else: ?>
        <?php foreach ($bloki as $blok): ?>
        <section class="block">
            <div class="block-header">
                <h2>Blok <?= h($blok['blok']) ?></h2>
                <div class="block-meta">
                    <span>📅 <?= h($blok['data']) ?></span>
                    <span>🕐 Start: <?= h($blok['godz_start']) ?></span>
                    <span><?= count($blok['starty'] ?? []) ?> startów</span>
                </div>
            </div>

            <?php if (!empty($blok['starty'])): ?>
            <div class="table-wrapper">
                <table class="results-table">
                    <thead>
                        <tr>
                            <th>Zawodnik</th>
                            <th>Konk.</th>
                            <th>Seria</th>
                            <th class="text-center">Godz.</th>
                            <th class="text-center col-tor">T</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($blok['starty'] as $s):
                        $czesci   = explode(' ', trim($s['imie']), 2);
                        $nazwisko = $czesci[0];
                        $imie     = $czesci[1] ?? '';
                        $fetched  = !empty($s['result_fetched']);
                        $search_key = mb_strtolower(
                            $s['imie'] . ' ' . format_konkurencja($s['konkurencja'], (int)$s['konkurencja_nr'])
                        );
                    ?>
                        <tr data-search="<?= h($search_key) ?>" data-blok="<?= h($blok['blok']) ?>">
                            <td class="athlete" data-label="Zawodnik">
                                <span class="last-name"><?= h($nazwisko) ?></span>
                                <?php if ($imie): ?>
                                    <span class="first-name"><?= h($imie) ?></span>
                                <?php endif; ?>
                                <?php if ($fetched): ?>
                                    <button class="btn-show-time"
                                        data-czas="<?= h($s['czas'] ?? '') ?>"
                                        data-czas-result="<?= h($s['czas_result']) ?>"
                                        data-punkty="<?= h((string)($s['punkty'] ?? '')) ?>"
                                        data-type="result">Pokaż czas</button>
                                <?php elseif (!empty($s['czas'])): ?>
                                    <button class="btn-show-time" data-czas="<?= h($s['czas']) ?>" data-type="seed">Pokaż czas</button>
                                <?php endif; ?>
                            </td>
                            <td data-label="Konk."><?= h(format_konkurencja($s['konkurencja'], (int)$s['konkurencja_nr'])) ?></td>
                            <td data-label="Seria"><?= h($s['seria']) ?></td>
                            <td class="text-center" data-label="Godz."><?= h($s['godz']) ?></td>
                            <td class="text-center" data-label="Tor"><?= h((string)$s['tor']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php else: ?>
                <p class="empty-state" style="padding:1rem 1.25rem">Brak startów w tym bloku.</p>
            <?php endif; ?>
        </section>
        <?php endforeach; ?>
    <?php endif; ?>

    <div class="back-link" style="display:flex;gap:1rem;flex-wrap:wrap;align-items:center">
        <a href="<?= BASE_URL ?>/index.php" class="btn btn-outline">← Wróć do listy zawodów</a>
        <a href="<?= BASE_URL ?>/wyniki.php?zawody=<?= urlencode($json_slug) ?>" class="btn btn-outline">Wyniki</a>
    </div>
</main>

<footer class="site-footer">
    <div class="container">
        <p>&copy; <?= date('Y') ?> Olimpijczyk Proszówki</p>
        <p class="footer-madeby">made by <a href="https://www.nd-soft.pl" target="_blank" rel="noopener">nd-soft</a></p>
    </div>
</footer>

<script>
(function () {
    var input   = document.getElementById('athlete-search');
    var section = document.getElementById('search-results');
    var tbody   = document.getElementById('search-tbody');
    var empty   = document.getElementById('search-empty');
    var blocks  = document.querySelectorAll('section.block');

    if (!input) return;

    var allRows = Array.from(document.querySelectorAll('section.block tr[data-search]'));

    function buildCell(row, colIndex) {
        var cell = row.cells[colIndex];
        return cell ? cell.innerHTML : '';
    }

    input.addEventListener('input', function () {
        var q = this.value.trim().toLowerCase();

        if (!q) {
            section.hidden = true;
            blocks.forEach(function (b) { b.hidden = false; });
            return;
        }

        blocks.forEach(function (b) { b.hidden = true; });
        section.hidden = false;

        var matched = allRows.filter(function (r) {
            return r.dataset.search.indexOf(q) !== -1;
        });

        tbody.innerHTML = '';
        if (matched.length === 0) {
            empty.hidden = false;
        } else {
            empty.hidden = true;
            matched.forEach(function (r) {
                var tr = document.createElement('tr');
                /* Athlete */
                var tdZ = document.createElement('td');
                tdZ.setAttribute('data-label', 'Zawodnik');
                tdZ.innerHTML = buildCell(r, 0);
                tr.appendChild(tdZ);
                /* Event */
                var tdK = document.createElement('td');
                tdK.setAttribute('data-label', 'Konk.');
                tdK.innerHTML = buildCell(r, 1);
                tr.appendChild(tdK);
                /* Block */
                var tdB = document.createElement('td');
                tdB.setAttribute('data-label', 'Blok');
                tdB.textContent = r.dataset.blok;
                tr.appendChild(tdB);
                /* Heat */
                var tdS = document.createElement('td');
                tdS.setAttribute('data-label', 'Seria');
                tdS.innerHTML = buildCell(r, 2);
                tr.appendChild(tdS);
                /* Time */
                var tdG = document.createElement('td');
                tdG.className = 'text-center';
                tdG.setAttribute('data-label', 'Godz.');
                tdG.innerHTML = buildCell(r, 3);
                tr.appendChild(tdG);
                /* Lane */
                var tdT = document.createElement('td');
                tdT.className = 'text-center';
                tdT.setAttribute('data-label', 'Tor');
                tdT.innerHTML = buildCell(r, 4);
                tr.appendChild(tdT);

                tbody.appendChild(tr);
            });
        }
    });
})();

document.addEventListener('click', function (e) {
    var btn = e.target;
    if (!btn.classList.contains('btn-show-time')) return;

    var text;
    var cls;
    if (btn.dataset.type === 'result') {
        cls = 'time-display time-result';
        var parts = [btn.dataset.czas, btn.dataset.czasResult, btn.dataset.punkty].filter(Boolean);
        text = parts.join(' ⇒ ');
    } else {
        cls = 'time-display time-seed';
        text = btn.dataset.czas;
    }

    btn.remove();

    var tr = e.target.closest('tr') || (function () {
        var el = e.target;
        while (el && el.tagName !== 'TR') el = el.parentElement;
        return el;
    })();
    if (!tr) return;

    var colspan = tr.cells.length;
    var newTr = document.createElement('tr');
    newTr.className = 'tr-time-display';
    var td = document.createElement('td');
    td.colSpan = colspan;
    var span = document.createElement('span');
    span.className = cls;
    span.textContent = text;
    td.appendChild(span);
    newTr.appendChild(td);
    tr.insertAdjacentElement('afterend', newTr);
});
</script>

</body>
</html>
