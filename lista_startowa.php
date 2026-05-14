<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/result_fetch.php';

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

$bloki       = $json['bloki'] ?? [];
$live_config = load_live_config();
$json_slug   = basename($filepath, '.json');
$is_live     = !empty($live_config['aktywna']) && ($live_config['json_file'] ?? '') === $json_slug;
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
    <div class="wyniki-header">
        <h1><?= h($json['nazwa'] ?? 'Lista startowa') ?></h1>
        <div class="wyniki-meta">
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

    <?php if (empty($bloki)): ?>
        <p class="empty-state">Brak bloków startowych w pliku.</p>
    <?php else: ?>
        <?php foreach ($bloki as $blok): ?>
        <section class="blok">
            <div class="blok-header">
                <h2>Blok <?= h($blok['blok']) ?></h2>
                <div class="blok-meta">
                    <span>📅 <?= h($blok['data']) ?></span>
                    <span>🕐 Start: <?= h($blok['godz_start']) ?></span>
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
                            <th>Seria</th>
                            <th class="text-center">Godz.</th>
                            <th class="text-center col-tor">T</th>
                            <th class="text-center">Wynik</th>
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
                                <?php if ($imie): ?>
                                    <span class="imie"><?= h($imie) ?></span>
                                <?php endif; ?>
                            </td>
                            <td data-label="Konk."><?= h(format_konkurencja($s['konkurencja'], (int)$s['konkurencja_nr'])) ?></td>
                            <td data-label="Seria"><?= h($s['seria']) ?></td>
                            <td class="text-center" data-label="Godz."><?= h($s['godz']) ?></td>
                            <td class="text-center" data-label="Tor"><?= h((string)$s['tor']) ?></td>
                            <td class="text-center" data-label="Wynik">
                                <?php if ($fetched): ?>
                                    <strong style="color:#4caf50"><?= h($s['czas_result']) ?></strong>
                                <?php elseif (!empty($s['czas'])): ?>
                                    <span style="color:#888"><?= h($s['czas']) ?></span>
                                <?php else: ?>
                                    <span style="color:#555">—</span>
                                <?php endif; ?>
                            </td>
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
        <?php if ($is_live): ?>
            <a href="<?= BASE_URL ?>/wyniki.php?zawody=<?= urlencode($json_slug) ?>" class="btn btn-primary">Wyniki live</a>
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
(function() {
    setInterval(function() {
        fetch('<?= BASE_URL ?>/api/fetch_result.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: '{}'
        })
        .then(function(r) { return r.json(); })
        .then(function(d) { if (d.updated > 0) location.reload(); })
        .catch(function() {});
    }, 60000);
})();
</script>
<?php endif; ?>
</body>
</html>
