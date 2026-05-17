<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/result_fetch.php';

$error   = '';
$success = '';
$config  = load_live_config();
$zawody  = load_all_zawody();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    $contest_url = trim($_POST['contest_url'] ?? '');
    $json_file   = basename($_POST['json_file'] ?? '');
    $json_file   = preg_replace('/\.json$/', '', $json_file);

    if (!filter_var($contest_url, FILTER_VALIDATE_URL)) {
        $error = 'Podaj prawidłowy URL zawodów (np. https://livetiming.pl/contest/UUID).';
    } elseif (!preg_match('/^[a-zA-Z0-9_\-]+$/', $json_file)) {
        $error = 'Wybierz plik listy startowej.';
    } elseif (!safe_json_path($json_file . '.json')) {
        $error = 'Wybrany plik zawodów nie istnieje.';
    } else {
        $zawody_data = json_decode(file_get_contents(safe_json_path($json_file . '.json')), true);
        $config = [
            'contest_url'          => $contest_url,
            'json_file'            => $json_file,
            'nazwa'                => $zawody_data['nazwa'] ?? '',
            'ostatnia_aktualizacja'=> $config['ostatnia_aktualizacja'] ?? null,
        ];
        save_live_config($config);
        $success = 'Konfiguracja zapisana.';
    }
}

$has_config = !empty($config['contest_url']) && !empty($config['json_file']);
?>
<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Wyniki LENEX — Panel admina</title>
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
            <a href="<?= BASE_URL ?>/admin/live.php" class="active">Wyniki LENEX</a>
            <a href="<?= BASE_URL ?>/index.php">Strona główna</a>
            <a href="<?= BASE_URL ?>/admin/logout.php">Wyloguj</a>
        </nav>
    </div>
</header>
<main class="container">
    <div class="page-header">
        <h1>Wyniki LENEX</h1>
        <?php if ($has_config): ?>
            <a href="<?= BASE_URL ?>/wyniki.php?zawody=<?= urlencode($config['json_file']) ?>"
               class="btn btn-primary" target="_blank">Podgląd wyników</a>
        <?php endif; ?>
    </div>

    <?php if ($error): ?>
        <div class="alert alert-error"><?= h($error) ?></div>
    <?php endif; ?>
    <?php if ($success): ?>
        <div class="alert alert-success"><?= h($success) ?></div>
    <?php endif; ?>

    <div class="admin-form">
        <h2 style="color:#f0a800;font-size:1.1rem;margin-bottom:1.25rem">Konfiguracja źródła wyników</h2>
        <form method="post">
            <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">

            <div class="form-group">
                <label for="contest_url">Link do wyników (livetiming.pl)</label>
                <input type="url" id="contest_url" name="contest_url"
                    value="<?= h($config['contest_url'] ?? '') ?>"
                    placeholder="https://livetiming.pl/contest/4b7c8861-8a13-4d35-ab34-ce45d490c9d2"
                    required>
                <span class="form-hint">Wklej link do zawodów z livetiming.pl.</span>
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
                <button type="submit" class="btn btn-primary">Zapisz</button>
                <a href="<?= BASE_URL ?>/admin/lista.php" class="btn btn-outline">Anuluj</a>
            </div>
        </form>
    </div>

    <?php if ($has_config): ?>
    <div class="admin-form" style="margin-top:1.5rem">
        <h2 style="color:#f0a800;font-size:1.1rem;margin-bottom:.75rem">Pobierz wyniki</h2>

        <?php if (!empty($config['nazwa'])): ?>
            <p style="margin-bottom:.75rem;color:#aaa">
                Zawody: <strong style="color:#eee"><?= h($config['nazwa']) ?></strong>
            </p>
        <?php endif; ?>

        <?php if (!empty($config['ostatnia_aktualizacja'])): ?>
            <p style="font-size:.85rem;color:#666;margin-bottom:1rem">
                Ostatnie pobranie: <?= h($config['ostatnia_aktualizacja']) ?>
            </p>
        <?php endif; ?>

        <p style="font-size:.875rem;color:#888;margin-bottom:1rem">
            Link .lxf zostanie wyciągnięty automatycznie ze strony zawodów.<br>
            <code style="color:#aaa;font-size:.8rem"><?= h($config['contest_url']) ?></code>
        </p>

        <button id="btn-fetch" class="btn btn-primary">Pobierz wyniki LENEX</button>
        <div id="fetch-status" style="margin-top:1rem;font-size:.9rem"></div>
    </div>

    <script>
    document.getElementById('btn-fetch').addEventListener('click', function() {
        var btn = this;
        var status = document.getElementById('fetch-status');
        btn.disabled = true;
        btn.textContent = 'Pobieranie…';
        status.style.color = '#aaa';
        status.textContent = 'Łączenie z livetiming.pl…';

        fetch('<?= BASE_URL ?>/api/fetch_result.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: '{}'
        })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            btn.disabled = false;
            btn.textContent = 'Pobierz wyniki LENEX';
            if (data.error || (data.errors && data.errors.length)) {
                status.style.color = '#f44';
                status.textContent = data.error || data.errors.join('; ');
            } else {
                status.style.color = '#4caf50';
                status.textContent = 'Zaktualizowano ' + data.updated + ' z ' + data.total
                    + ' startów. Nie znaleziono: ' + data.not_found + '. [' + data.time + ']';
            }
        })
        .catch(function(e) {
            btn.disabled = false;
            btn.textContent = 'Pobierz wyniki LENEX';
            status.style.color = '#f44';
            status.textContent = 'Błąd połączenia: ' + e.message;
        });
    });
    </script>
    <?php endif; ?>

</main>
</body>
</html>
