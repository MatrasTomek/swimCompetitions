<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/functions.php';

$json_file = preg_replace('/\.json$/', '', basename($_GET['zawody'] ?? ''));
$path = $json_file ? safe_json_path($json_file . '.json') : '';

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

$total_starts   = 0;
$fetched_starts = 0;
foreach ($bloki as $blok) {
    foreach ($blok['starty'] ?? [] as $s) {
        $total_starts++;
        if (!empty($s['result_fetched'])) $fetched_starts++;
    }
}
?>
<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Wyniki — <?= h($zawody['nazwa'] ?? '') ?></title>
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/style.css?v=<?= CSS_VERSION ?>">
    <style>
        .wyniki-toolbar { display:flex; align-items:center; gap:1rem; flex-wrap:wrap; margin:1rem 0; }
        .progress-bar { flex:1; height:6px; background:#2a2a2a; border-radius:3px; min-width:120px; }
        .progress-fill { height:100%; background:#f0a800; border-radius:3px; }
        .blok-section { margin-bottom:2rem; }
        .wyniki-footer { margin-top:1.5rem; display:flex; gap:1rem; flex-wrap:wrap; align-items:center; }
        .start-meta { font-size:.78rem; color:#888; margin-top:.18rem; }
        .start-meta strong { color:#bbb; }
        .start-result { margin-top:.3rem; font-size:.85rem; display:flex; gap:.6rem; flex-wrap:wrap; align-items:center; }
        .czas-seed  { color:#888; }
        .czas-arrow { color:#555; }
        .czas-result-val { font-weight:700; color:#4caf50; }
        .czas-pending { color:#555; font-style:italic; }
        .pkt-val { color:#f0a800; font-size:.8rem; }
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
                            <th class="text-center col-tor">T</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($blok['starty'] as $s):
                        $czesci   = explode(' ', trim($s['imie']), 2);
                        $nazwisko = $czesci[0];
                        $imie     = $czesci[1] ?? '';
                        $fetched  = !empty($s['result_fetched']);
                        $konk     = h(format_konkurencja($s['konkurencja'] ?? '', (int)($s['konkurencja_nr'] ?? 0)));
                        $godz     = h($s['godz'] ?? '');
                        $czas_s   = h($s['czas'] ?? '');
                    ?>
                        <tr>
                            <td class="zawodnik" data-label="Zawodnik">
                                <span class="nazwisko"><?= h($nazwisko) ?></span>
                                <?php if ($imie): ?><span class="imie"><?= h($imie) ?></span><?php endif; ?>
                                <div class="start-meta">
                                    <strong><?= $konk ?></strong>
                                    <?php if ($godz): ?> · <?= $godz ?><?php endif; ?>
                                </div>
                                <div class="start-result">
                                    <?php if ($czas_s): ?><span class="czas-seed"><?= $czas_s ?></span><?php endif; ?>
                                    <?php if ($fetched): ?>
                                        <?php if ($czas_s): ?><span class="czas-arrow">→</span><?php endif; ?>
                                        <span class="czas-result-val"><?= h($s['czas_result']) ?></span>
                                        <?php if (!empty($s['punkty'])): ?>
                                            <span class="pkt-val"><?= h((string)$s['punkty']) ?> pkt</span>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <?php if ($czas_s): ?><span class="czas-arrow">→</span><?php endif; ?>
                                        <span class="czas-pending">brak wyniku</span>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td class="text-center" data-label="Tor"><?= h((string)($s['tor'] ?? '')) ?></td>
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
    </div>
</main>

<footer class="site-footer">
    <div class="container">
        <p>&copy; <?= date('Y') ?> Olimpijczyk Proszówki</p>
        <p class="footer-madeby">made by <a href="https://www.nd-soft.pl" target="_blank" rel="noopener">nd-soft</a></p>
    </div>
</footer>
</body>
</html>
