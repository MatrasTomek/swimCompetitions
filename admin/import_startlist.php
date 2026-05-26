<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
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
        <p style="margin-bottom:1.25rem;color:#888;font-size:.9rem">
            Podaj link do zawodów na livetiming.pl oraz nazwę klubu.
            System automatycznie pobierze PDF z listą startową i wygeneruje plik JSON.
        </p>

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
