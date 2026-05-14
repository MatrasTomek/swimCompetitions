<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/result_fetch.php';

$json_file = preg_replace('/\.json$/', '', basename($_GET['zawody'] ?? ''));
$path = $json_file ? safe_json_path($json_file . '.json') : '';

if (!$path) {
    // Fall back to the active live config
    $live = load_live_config();
    if (!empty($live['json_file'])) {
        $json_file = $live['json_file'];
        $path = safe_json_path($json_file . '.json');
    }
}

if (!$path) {
    http_response_code(404);
    die('Nie znaleziono zawodów.');
}

$zawody = json_decode(file_get_contents($path), true);
if (!$zawody) {
    http_response_code(500);
    die('Błąd odczytu danych.');
}

$bloki = $zawody['bloki'] ?? [];

// Counters
$total_starts   = 0;
$fetched_starts = 0;
foreach ($bloki as $blok) {
    foreach ($blok['starty'] ?? [] as $s) {
        $total_starts++;
        if (!empty($s['result_fetched'])) $fetched_starts++;
    }
}
$live_config = load_live_config();
$is_live = !empty($live_config['aktywna']) && ($live_config['json_file'] ?? '') === $json_file;
?>
<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Wyniki — <?= h($zawody['nazwa'] ?? '') ?></title>
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/style.css?v=<?= CSS_VERSION ?>">
    <?php if ($is_live): ?>
    <meta http-equiv="refresh" content="120">
    <?php endif; ?>
    <style>
        .wyniki-toolbar { display:flex; align-items:center; gap:1rem; flex-wrap:wrap; margin:1rem 0; }
        .badge-live { background:#c62828; color:#fff; font-size:0.7rem; font-weight:800;
                      letter-spacing:.1em; padding:.2rem .55rem; border-radius:3px; text-transform:uppercase; }
        .badge-done { background:#1b5e20; color:#aed581; font-size:0.7rem; font-weight:700;
                      padding:.2rem .55rem; border-radius:3px; }
        .progress-bar { flex:1; height:6px; background:#2a2a2a; border-radius:3px; min-width:120px; }
        .progress-fill { height:100%; background:#f0a800; border-radius:3px; transition:width .5s; }
        .czas-cell { font-weight:700; color:#4caf50; }
        .czas-pending { color:#666; font-style:italic; }
        .blok-section { margin-bottom:2rem; }
        .wyniki-footer { margin-top:1.5rem; display:flex; gap:1rem; flex-wrap:wrap; align-items:center; }
        .auto-refresh-note { font-size:.8rem; color:#666; }
    </style>
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
    <div class="wyniki-header">
        <h1><?= h($zawody['nazwa'] ?? 'Wyniki') ?></h1>
        <div class="wyniki-meta">
            <?php
            $parts = array_filter([$zawody['klub'] ?? '', $zawody['miejsce'] ?? '', $zawody['data'] ?? '']);
            echo h(implode(' · ', $parts));
            ?>
        </div>
    </div>

    <div class="wyniki-toolbar">
        <?php if ($is_live): ?>
            <span class="badge-live">Live</span>
        <?php else: ?>
            <span class="badge-done">Zakończone</span>
        <?php endif; ?>
        <span style="font-size:.9rem;color:#aaa">
            Wyniki: <strong style="color:#f0a800"><?= $fetched_starts ?></strong> / <?= $total_starts ?>
        </span>
        <div class="progress-bar">
            <div class="progress-fill" style="width:<?= $total_starts > 0 ? round($fetched_starts / $total_starts * 100) : 0 ?>%"></div>
        </div>
        <a href="<?= BASE_URL ?>/api/generuj_pdf.php?zawody=<?= urlencode($json_file) ?>"
           class="btn btn-outline btn-sm" target="_blank">Pobierz PDF</a>
    </div>

    <?php if (empty($bloki)): ?>
        <p class="empty-state">Brak danych startowych.</p>
    <?php else: ?>
        <?php foreach ($bloki as $blok): ?>
        <section class="blok blok-section">
            <div class="blok-header">
                <h2>Blok <?= h($blok['blok']) ?></h2>
                <div class="blok-meta">
                    <span><?= h($blok['data']) ?></span>
                    <span>Start: <?= h($blok['godz_start']) ?></span>
                    <span><?= count($blok['starty'] ?? []) ?> startów</span>
                </div>
            </div>

            <?php if (!empty($blok['starty'])): ?>
            <div class="table-wrapper">
                <table class="tabela-wynikow">
                    <thead>
                        <tr>
                            <th>Zawodnik</th>
                            <th>Konk.</th>
                            <th class="text-center">Godz.</th>
                            <th class="text-center col-tor">T</th>
                            <th class="text-center">Wynik</th>
                            <th class="text-center">Pkt</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($blok['starty'] as $s):
                        $czesci   = explode(' ', trim($s['imie']), 2);
                        $nazwisko = $czesci[0];
                        $imie     = $czesci[1] ?? '';
                        $fetched  = !empty($s['result_fetched']);
                    ?>
                        <tr>
                            <td class="zawodnik" data-label="Zawodnik">
                                <span class="nazwisko"><?= h($nazwisko) ?></span>
                                <?php if ($imie): ?><span class="imie"><?= h($imie) ?></span><?php endif; ?>
                            </td>
                            <td data-label="Konk."><?= h(format_konkurencja($s['konkurencja'] ?? '', (int)($s['konkurencja_nr'] ?? 0))) ?></td>
                            <td class="text-center" data-label="Godz."><?= h($s['godz'] ?? '') ?></td>
                            <td class="text-center" data-label="Tor"><?= h((string)($s['tor'] ?? '')) ?></td>
                            <td class="text-center" data-label="Wynik">
                                <?php if ($fetched): ?>
                                    <span class="czas-cell"><?= h($s['czas_result']) ?></span>
                                <?php else: ?>
                                    <span class="czas-pending">oczekiwanie</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-center" data-label="Pkt">
                                <?= $fetched ? h((string)($s['punkty'] ?? '—')) : '—' ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </section>
        <?php endforeach; ?>
    <?php endif; ?>

    <div class="wyniki-footer">
        <a href="<?= BASE_URL ?>/index.php" class="btn btn-outline">← Wróć do listy zawodów</a>
        <?php if ($is_live): ?>
            <span class="auto-refresh-note">Strona odświeża się automatycznie co 2 minuty.</span>
        <?php endif; ?>
    </div>
</main>

<footer class="site-footer">
    <div class="container">
        <p>&copy; <?= date('Y') ?> Olimpijczyk Proszówki</p>
        <p class="footer-madeby">made by <a href="https://www.nd-soft.pl" target="_blank" rel="noopener">nd-soft</a></p>
    </div>
</footer>

<?php if ($is_live): ?>
<script>
// JS polling — fallback when cron is unavailable
(function() {
    var interval = 60000;
    function checkResults() {
        fetch('<?= BASE_URL ?>/api/fetch_result.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: '{}'
        })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if (data.updated > 0) location.reload();
        })
        .catch(function() {});
    }
    setInterval(checkResults, interval);
})();
</script>
<?php endif; ?>
</body>
</html>
