<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/result_fetch.php';

$error  = '';
$success = '';
$config = load_live_config();
$zawody = load_all_zawody();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    if (isset($_POST['dezaktywuj'])) {
        $config['aktywna'] = false;
        save_live_config($config);
        $success = 'Zawody live dezaktywowane.';
    } else {
        $url       = trim($_POST['url'] ?? '');
        $json_file = basename($_POST['json_file'] ?? '');
        $json_file = preg_replace('/\.json$/', '', $json_file);

        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            $error = 'Podaj prawidłowy URL (np. https://live.livetiming.pl/.../index.html).';
        } elseif (!preg_match('/^[a-zA-Z0-9_\-]+$/', $json_file)) {
            $error = 'Wybierz plik zawodów.';
        } elseif (!safe_json_path($json_file . '.json')) {
            $error = 'Wybrany plik zawodów nie istnieje.';
        } else {
            $zawody_data = json_decode(file_get_contents(safe_json_path($json_file . '.json')), true);
            $config = [
                'url'                  => $url,
                'json_file'            => $json_file,
                'nazwa'                => $zawody_data['nazwa']   ?? '',
                'aktywna'              => true,
                'ostatnia_aktualizacja'=> date('c'),
            ];
            save_live_config($config);
            $success = 'Konfiguracja live zapisana.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Zawody live — Panel admina</title>
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
            <a href="<?= BASE_URL ?>/admin/live.php" class="active">Zawody live</a>
            <a href="<?= BASE_URL ?>/admin/debug_live.php">Diagnostyka</a>
            <a href="<?= BASE_URL ?>/index.php">Strona główna</a>
            <a href="<?= BASE_URL ?>/admin/logout.php">Wyloguj</a>
        </nav>
    </div>
</header>
<main class="container">
    <div class="page-header">
        <h1>Zawody live</h1>
        <?php if (!empty($config['aktywna'])): ?>
            <a href="<?= BASE_URL ?>/wyniki.php?zawody=<?= urlencode($config['json_file'] ?? '') ?>" class="btn btn-primary" target="_blank">Podgląd wyników</a>
        <?php endif; ?>
    </div>

    <?php if ($error): ?>
        <div class="alert alert-error"><?= h($error) ?></div>
    <?php endif; ?>
    <?php if ($success): ?>
        <div class="alert alert-success"><?= h($success) ?></div>
    <?php endif; ?>

    <?php if (!empty($config['aktywna'])): ?>
    <div class="live-status">
        <div class="live-badge">LIVE</div>
        <div class="live-info">
            <strong><?= h($config['nazwa'] ?? '') ?></strong><br>
            <small>Plik: <code><?= h($config['json_file'] ?? '') ?>.json</code></small><br>
            <small>Ostatnia aktualizacja: <?= h($config['ostatnia_aktualizacja'] ?? '—') ?></small>
        </div>
        <form method="post" style="margin:0">
            <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
            <button type="submit" name="dezaktywuj" class="btn btn-danger btn-sm">Dezaktywuj</button>
        </form>
    </div>
    <?php endif; ?>

    <div class="admin-form" style="margin-top:1.5rem">
        <h2 style="color:#f0a800;font-size:1.1rem;margin-bottom:1.25rem">
            <?= empty($config['aktywna']) ? 'Ustaw zawody live' : 'Zmień konfigurację' ?>
        </h2>
        <form method="post">
            <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">

            <div class="form-group">
                <label for="url">Link do wyników livetiming.pl</label>
                <input type="text" id="url" name="url"
                    value="<?= h($config['url'] ?? '') ?>"
                    placeholder="https://live.livetiming.pl/zak/2026/05_10_oswiecim/index.html"
                    required>
                <span class="form-hint">Wklej link do strony index.html z wynikami zawodów.</span>
            </div>

            <div class="form-group">
                <label for="json_file">Plik listy startowej</label>
                <select id="json_file" name="json_file" class="form-select" required>
                    <option value="">— wybierz plik —</option>
                    <?php foreach ($zawody as $z): ?>
                        <?php $slug = basename($z['file'], '.json'); ?>
                        <option value="<?= h($slug) ?>"
                            <?= ($config['json_file'] ?? '') === $slug ? 'selected' : '' ?>>
                            <?= h($z['nazwa']) ?> (<?= h($z['file']) ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
                <span class="form-hint">JSON z listą startową tego klubu dla tych zawodów.</span>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn btn-primary">Zapisz konfigurację</button>
                <a href="<?= BASE_URL ?>/admin/lista.php" class="btn btn-outline">Anuluj</a>
            </div>
        </form>
    </div>
</main>
</body>
</html>
