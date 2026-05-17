<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/athlete.php';
require_once __DIR__ . '/../includes/result_fetch.php';

$config   = load_live_config();
$log      = [];
$updated  = null;
$pdf_test = null;

// Manual full fetch
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['akcja'])) {
    csrf_verify();

    if ($_POST['akcja'] === 'fetch') {
        $updated = process_pending_results($log);
    }

    if ($_POST['akcja'] === 'test_pdf') {
        $nr      = (int)($_POST['nr'] ?? 1);
        $base    = get_base_pdf_url($config['url'] ?? '');
        $pdf_url = $base . 'ResultList_' . $nr . '.pdf';
        $pdf     = pdf_download($pdf_url);
        if ($pdf === false || $pdf === '') {
            $pdf_test = ['url' => $pdf_url, 'ok' => false, 'text' => ''];
        } else {
            $text = pdf_extract_text($pdf);
            $pdf_test = ['url' => $pdf_url, 'ok' => true, 'text' => $text, 'bytes' => strlen($pdf)];
        }
    }
}

$base_url_preview = !empty($config['url']) ? get_base_pdf_url($config['url']) : '';
?>
<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Diagnostyka live — Panel admina</title>
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/style.css?v=<?= CSS_VERSION ?>">
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/admin.css?v=<?= CSS_VERSION ?>">
    <style>
        .debug-box { background:#1a1a1a; border:1px solid #333; border-radius:6px; padding:1rem; margin-bottom:1rem; }
        .debug-box pre { white-space:pre-wrap; word-break:break-all; font-size:.78rem; color:#ccc; max-height:300px; overflow-y:auto; margin:0; }
        .status-done      { color:#4caf50; }
        .status-updated   { color:#f0a800; font-weight:700; }
        .status-not_found { color:#e57373; }
        .status-wait      { color:#90a4ae; }
        .status-skip      { color:#ff7043; }
        table.diag td, table.diag th { padding:.3rem .6rem; font-size:.82rem; }
    </style>
</head>
<body>
<header class="admin-header">
    <div class="container">
        <span class="admin-logo">Panel admina</span>
        <nav>
            <a href="<?= BASE_URL ?>/admin/lista.php">Lista zawodów</a>
            <a href="<?= BASE_URL ?>/admin/live.php">Zawody live</a>
            <a href="<?= BASE_URL ?>/admin/debug_live.php" class="active">Diagnostyka</a>
            <a href="<?= BASE_URL ?>/index.php">Strona główna</a>
            <a href="<?= BASE_URL ?>/admin/logout.php">Wyloguj</a>
        </nav>
    </div>
</header>
<main class="container">
    <div class="page-header">
        <h1>Diagnostyka pobierania wyników</h1>
    </div>

    <div class="debug-box">
        <h3 style="margin-top:0;color:#f0a800">Konfiguracja live</h3>
        <?php if (empty($config)): ?>
            <p style="color:#e57373">Brak pliku live_config.json — skonfiguruj najpierw zawody live.</p>
        <?php else: ?>
            <table class="diag">
                <tr><th>Aktywna</th><td><?= !empty($config['aktywna']) ? '<span class="status-done">TAK</span>' : '<span class="status-not_found">NIE</span>' ?></td></tr>
                <tr><th>URL</th><td><code><?= h($config['url'] ?? '—') ?></code></td></tr>
                <tr><th>Base PDF URL</th><td><code style="color:#f0a800"><?= h($base_url_preview) ?></code></td></tr>
                <tr><th>Plik zawodów</th><td><code><?= h(($config['json_file'] ?? '—') . '.json') ?></code></td></tr>
                <tr><th>Ostatnia aktualizacja</th><td><?= h($config['ostatnia_aktualizacja'] ?? '—') ?></td></tr>
            </table>
        <?php endif; ?>
    </div>

    <div class="debug-box">
        <h3 style="margin-top:0;color:#f0a800">Test pobrania PDF</h3>
        <form method="post" style="display:flex;gap:.75rem;align-items:flex-end;flex-wrap:wrap">
            <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
            <input type="hidden" name="akcja" value="test_pdf">
            <div class="form-group" style="margin:0">
                <label>Nr konkurencji</label>
                <input type="number" name="nr" value="<?= h((string)($_POST['nr'] ?? 1)) ?>" min="1" style="width:80px">
            </div>
            <button type="submit" class="btn btn-outline btn-sm">Pobierz i parsuj PDF</button>
        </form>

        <?php if ($pdf_test !== null): ?>
            <p style="margin-top:.75rem">
                URL: <code><?= h($pdf_test['url']) ?></code><br>
                Status: <?php if ($pdf_test['ok']): ?>
                    <span class="status-done">OK (<?= $pdf_test['bytes'] ?> B)</span>
                <?php else: ?>
                    <span class="status-not_found">Błąd pobierania</span>
                <?php endif; ?>
            </p>
            <?php if ($pdf_test['ok'] && $pdf_test['text']): ?>
                <p style="margin:.5rem 0 .25rem;font-size:.8rem;color:#aaa">Wyekstrahowany tekst (pierwsze 3000 znaków):</p>
                <pre><?= h(substr($pdf_test['text'], 0, 3000)) ?></pre>
            <?php elseif ($pdf_test['ok']): ?>
                <p style="color:#ff7043">PDF pobrany, ale ekstrakcja tekstu nie zwróciła nic.</p>
            <?php endif; ?>
        <?php endif; ?>
    </div>

    <div class="debug-box">
        <h3 style="margin-top:0;color:#f0a800">Ręczne uruchomienie fetch</h3>
        <form method="post">
            <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
            <input type="hidden" name="akcja" value="fetch">
            <button type="submit" class="btn btn-primary btn-sm">Uruchom process_pending_results()</button>
        </form>

        <?php if ($updated !== null): ?>
            <p style="margin-top:.75rem">Zaktualizowano wyników: <strong style="color:#f0a800"><?= $updated ?></strong></p>

            <?php if (!empty($log)): ?>
            <table class="diag" style="width:100%;border-collapse:collapse">
                <thead>
                    <tr style="border-bottom:1px solid #333">
                        <th>Zawodnik</th><th>Nr</th><th>Status</th><th>Szczegóły</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($log as $e): ?>
                    <tr style="border-bottom:1px solid #222">
                        <td><?= h($e['imie'] ?? '') ?></td>
                        <td><?= h((string)($e['nr'] ?? '')) ?></td>
                        <td class="status-<?= h($e['status'] ?? '') ?>"><?= h($e['status'] ?? '') ?></td>
                        <td>
                            <?php if (!empty($e['czas'])): ?><code><?= h($e['czas']) ?></code> <?php endif; ?>
                            <?php if (!empty($e['reason'])): ?><small style="color:#aaa"><?= h($e['reason']) ?></small><?php endif; ?>
                            <?php if (!empty($e['pdf_url'])): ?><br><small style="color:#555"><?= h($e['pdf_url']) ?></small><?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
        <?php endif; ?>
    </div>

    <p><a href="<?= BASE_URL ?>/admin/live.php" class="btn btn-outline">← Wróć do konfiguracji live</a></p>
</main>
</body>
</html>
