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
                            <?php $slug = basename($z['file'], '.json'); ?>
                            <a href="<?= BASE_URL ?>/lista_startowa.php?f=<?= urlencode($z['file']) ?>" class="btn btn-sm btn-outline" target="_blank">Starty</a>
                            <?php if (!empty($z['has_results'])): ?>
                                <a href="<?= BASE_URL ?>/wyniki.php?zawody=<?= urlencode($slug) ?>" class="btn btn-sm btn-outline" target="_blank">Pokaż wyniki</a>
                            <?php else: ?>
                                <button class="btn btn-sm btn-primary btn-fetch-lenex"
                                    data-slug="<?= h($slug) ?>"
                                    data-nazwa="<?= h($z['nazwa']) ?>">Pobierz wyniki</button>
                            <?php endif; ?>
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

<!-- Modal: pobierz wyniki -->
<div id="modal-lenex" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.6);z-index:1000;align-items:center;justify-content:center">
    <div style="background:#1e2030;border:1px solid #333;border-radius:8px;padding:2rem;width:min(520px,95vw);position:relative">
        <button id="modal-close" style="position:absolute;top:.75rem;right:1rem;background:none;border:none;color:#888;font-size:1.4rem;cursor:pointer;line-height:1">&times;</button>
        <h2 style="margin:0 0 .25rem;font-size:1.1rem;color:#eee">Pobierz wyniki LENEX</h2>
        <p id="modal-nazwa" style="margin:0 0 1.25rem;font-size:.85rem;color:#888"></p>
        <label for="modal-url" style="display:block;margin-bottom:.4rem;font-size:.875rem;color:#ccc">Link do zawodów (livetiming.pl)</label>
        <input id="modal-url" type="url"
            placeholder="https://livetiming.pl/contest/4b7c8861-…"
            style="width:100%;box-sizing:border-box;padding:.55rem .75rem;background:#12141e;border:1px solid #444;border-radius:5px;color:#eee;font-size:.9rem;margin-bottom:1rem">
        <div id="modal-status" style="min-height:1.4rem;font-size:.875rem;margin-bottom:.75rem"></div>
        <div style="display:flex;gap:.75rem;justify-content:flex-end">
            <button id="modal-cancel" class="btn btn-outline">Anuluj</button>
            <button id="modal-submit" class="btn btn-primary">Pobierz wyniki</button>
        </div>
    </div>
</div>

<script>
(function () {
    var modal   = document.getElementById('modal-lenex');
    var urlInput= document.getElementById('modal-url');
    var status  = document.getElementById('modal-status');
    var submit  = document.getElementById('modal-submit');
    var currentSlug = '';

    function openModal(slug, nazwa) {
        currentSlug = slug;
        document.getElementById('modal-nazwa').textContent = nazwa;
        urlInput.value = '';
        status.textContent = '';
        status.style.color = '#aaa';
        modal.style.display = 'flex';
        urlInput.focus();
    }

    function closeModal() {
        modal.style.display = 'none';
    }

    document.querySelectorAll('.btn-fetch-lenex').forEach(function (btn) {
        btn.addEventListener('click', function () {
            openModal(btn.dataset.slug, btn.dataset.nazwa);
        });
    });

    document.getElementById('modal-close').addEventListener('click', closeModal);
    document.getElementById('modal-cancel').addEventListener('click', closeModal);
    modal.addEventListener('click', function (e) { if (e.target === modal) closeModal(); });

    document.addEventListener('keydown', function (e) { if (e.key === 'Escape') closeModal(); });

    submit.addEventListener('click', function () {
        var url = urlInput.value.trim();
        if (!url) { status.style.color = '#f44'; status.textContent = 'Wklej link do zawodów.'; return; }

        submit.disabled = true;
        submit.textContent = 'Pobieranie…';
        status.style.color = '#aaa';
        status.textContent = 'Łączenie z livetiming.pl…';

        fetch('<?= BASE_URL ?>/api/fetch_result.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({contest_url: url, json_file: currentSlug})
        })
        .then(function (r) { return r.json(); })
        .then(function (data) {
            submit.disabled = false;
            submit.textContent = 'Pobierz wyniki';
            if (data.error || (data.errors && data.errors.length)) {
                status.style.color = '#f44';
                status.textContent = data.error || data.errors.join('; ');
            } else {
                status.style.color = '#4caf50';
                status.textContent = 'Zaktualizowano ' + data.updated + ' z ' + data.total
                    + ' startów. Nie znaleziono: ' + data.not_found + '.';
                setTimeout(function () { location.reload(); }, 1500);
            }
        })
        .catch(function (e) {
            submit.disabled = false;
            submit.textContent = 'Pobierz wyniki';
            status.style.color = '#f44';
            status.textContent = 'Błąd połączenia: ' + e.message;
        });
    });
})();
</script>
</body>
</html>
