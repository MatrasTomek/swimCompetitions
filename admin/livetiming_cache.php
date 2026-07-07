<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/livetiming_cache.php';

$message = '';
$error   = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $action = $_POST['action'] ?? '';

    if ($action === 'refresh') {
        $max = max(1, min(200, (int)($_POST['max_pages'] ?? 30)));
        set_time_limit(300);
        $result = ltcache_refresh($max, true);
        if ($result['ok'] && !($result['skipped'] ?? false)) {
            $message = 'Cache odświeżony: ' . $result['count'] . ' zawodów w ' . $result['duration'] . 's.';
        } elseif ($result['skipped'] ?? false) {
            $message = 'Cache jest aktualny (' . ($result['reason'] ?? '') . '). Użyj "Wymuś odświeżenie".';
        } else {
            $error = $result['error'] ?? 'Błąd odświeżenia cache.';
        }
    }
}

$status = ltcache_status();
?>
<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Cache zawodów — Admin</title>
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/style.css?v=<?= CSS_VERSION ?>">
</head>
<body>
<header class="site-header">
    <div class="container">
        <div class="site-logo"><a href="<?= BASE_URL ?>/">Olimpijczyk Proszówki</a></div>
        <nav class="site-nav">
            <a href="<?= BASE_URL ?>/admin/lista.php">← Panel admina</a>
            <a href="<?= BASE_URL ?>/admin/logout.php" class="btn btn-sm btn-outline">Wyloguj</a>
        </nav>
    </div>
</header>

<main class="container" style="max-width:640px">
    <h1 style="margin-bottom:1.25rem">Cache zawodów livetiming.pl</h1>

    <?php if ($message): ?>
        <div class="alert alert-success" style="margin-bottom:1rem"><?= h($message) ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="alert alert-error" style="margin-bottom:1rem"><?= h($error) ?></div>
    <?php endif; ?>

    <div class="card" style="padding:1.25rem;margin-bottom:1.5rem">
        <h2 style="font-size:1rem;margin-bottom:.75rem">Status cache</h2>
        <?php if (!$status['exists']): ?>
            <p>Cache nie istnieje. Uruchom odświeżanie aby zbudować listę zawodów.</p>
        <?php else: ?>
            <table style="width:100%;border-collapse:collapse;font-size:.9rem">
                <tr><td style="padding:.3rem 0;color:#666">Ostatnia aktualizacja</td><td><?= h($status['updated_at']) ?></td></tr>
                <tr><td style="padding:.3rem 0;color:#666">Wiek</td><td><?= h($status['age_hours']) ?>h <?= $status['is_fresh'] ? '(aktualny)' : '(wymaga odświeżenia)' ?></td></tr>
                <tr><td style="padding:.3rem 0;color:#666">Liczba zawodów</td><td><?= h((string)$status['count']) ?></td></tr>
            </table>
        <?php endif; ?>
    </div>

    <form method="POST">
        <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
        <input type="hidden" name="action" value="refresh">

        <div style="margin-bottom:1rem">
            <label for="max_pages" style="display:block;font-size:.85rem;font-weight:600;margin-bottom:.35rem">
                Maks. stron na kategorię (regional/national/calendar/international)
            </label>
            <input type="number" id="max_pages" name="max_pages" value="30" min="1" max="200"
                   style="width:120px">
            <p style="font-size:.8rem;color:#666;margin-top:.3rem">
                ~24 zawody/stronę. 30 stron × 4 kategorie ≈ 2880 zawodów. Więcej stron = dłuższy czas.
            </p>
        </div>

        <button type="submit" class="btn btn-primary">Odśwież cache</button>
    </form>
</main>

<footer class="site-footer">
    <div class="container">
        <p>&copy; <?= date('Y') ?> Olimpijczyk Proszówki</p>
    </div>
</footer>
</body>
</html>
