<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    $nazwa   = trim($_POST['nazwa']   ?? '');
    $miejsce = trim($_POST['miejsce'] ?? '');
    $data    = trim($_POST['data']    ?? '');
    $klub    = trim($_POST['klub']    ?? '');

    if ($nazwa === '') {
        $errors[] = 'Podaj nazwę zawodów.';
    }

    $has_file = !empty($_FILES['plik']['name']);

    if ($has_file && empty($errors)) {
        $result = validate_json_upload($_FILES['plik']);
        if (!$result['ok']) {
            $errors[] = $result['msg'];
        }
    }

    if (empty($errors)) {
        if ($has_file) {
            $decoded  = $result['decoded'];
            // Override metadata from form if provided
            if ($nazwa)   $decoded['nazwa']   = $nazwa;
            if ($miejsce) $decoded['miejsce'] = $miejsce;
            if ($data)    $decoded['data']    = $data;
            if ($klub)    $decoded['klub']    = $klub;

            $basename = slugify($decoded['nazwa'] ?? pathinfo($_FILES['plik']['name'], PATHINFO_FILENAME));
            $filename = unique_filename($basename ?: 'zawody');
            $dest     = ZAWODY_DIR . '/' . $filename;

            if (!move_uploaded_file($_FILES['plik']['tmp_name'], $dest)) {
                $errors[] = 'Nie udało się zapisać pliku. Sprawdź uprawnienia katalogu zawody/.';
            } else {
                // Update metadata in the saved file
                $decoded['nazwa']   = $decoded['nazwa']   ?? $filename;
                $decoded['miejsce'] = $decoded['miejsce'] ?? '';
                $decoded['data']    = $decoded['data']    ?? '';
                $decoded['klub']    = $decoded['klub']    ?? '';
                file_put_contents($dest, json_encode($decoded, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));

                $_SESSION['flash'] = 'Zawody "' . $decoded['nazwa'] . '" zostały dodane.';
                header('Location: ' . BASE_URL . '/admin/lista.php');
                exit;
            }
        } else {
            // No file — save as announcement
            save_zapowiedz($nazwa, $miejsce, $data, $klub);
            $_SESSION['flash'] = 'Zapowiedź "' . $nazwa . '" została dodana (bez listy startowej).';
            header('Location: ' . BASE_URL . '/admin/lista.php');
            exit;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Dodaj zawody — Panel admina</title>
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/style.css?v=<?= CSS_VERSION ?>">
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/admin.css?v=<?= CSS_VERSION ?>">
</head>
<body>
<header class="admin-header">
    <div class="container">
        <span class="admin-logo">Panel admina</span>
        <nav>
            <a href="<?= BASE_URL ?>/admin/lista.php">Lista zawodów</a>
            <a href="<?= BASE_URL ?>/admin/dodaj.php" class="active">Dodaj zawody</a>
            <a href="<?= BASE_URL ?>/index.php">Strona główna</a>
            <a href="<?= BASE_URL ?>/admin/logout.php">Wyloguj</a>
        </nav>
    </div>
</header>
<main class="container">
    <div class="page-header">
        <h1>Dodaj zawody</h1>
        <a href="<?= BASE_URL ?>/admin/lista.php" class="btn btn-outline">← Wróć do listy</a>
    </div>

    <?php if (!empty($errors)): ?>
        <div class="alert alert-error">
            <ul><?php foreach ($errors as $e): ?><li><?= h($e) ?></li><?php endforeach; ?></ul>
        </div>
    <?php endif; ?>

    <div class="admin-form" style="max-width:520px">
        <p style="margin-bottom:1.25rem;color:#555;font-size:.92rem">
            Wypełnij dane zawodów. Plik JSON z listą startową jest <strong>opcjonalny</strong> —
            bez niego zawody pojawią się na stronie z napisem <em>wkrótce</em>.
        </p>
        <form method="post" enctype="multipart/form-data">
            <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">

            <div class="form-group">
                <label for="comp-name">Nazwa zawodów *</label>
                <input type="text" id="comp-name" name="nazwa" maxlength="255" required
                       value="<?= h($_POST['nazwa'] ?? '') ?>">
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label for="comp-location">Miejscowość</label>
                    <input type="text" id="comp-location" name="miejsce" maxlength="100"
                           value="<?= h($_POST['miejsce'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label for="comp-date">Data (np. 9-10/5/2026)</label>
                    <input type="text" id="comp-date" name="data" maxlength="30"
                           value="<?= h($_POST['data'] ?? '') ?>">
                </div>
            </div>
            <div class="form-group">
                <label for="comp-club">Klub</label>
                <input type="text" id="comp-club" name="klub" maxlength="255"
                       value="<?= h($_POST['klub'] ?? '') ?>">
            </div>

            <hr style="border:none;border-top:1px solid #e5e8ec;margin:1.25rem 0">

            <div class="form-group">
                <label for="comp-file">Plik wyników (JSON) — opcjonalnie</label>
                <input type="file" id="comp-file" name="plik" accept=".json,application/json">
                <small class="form-hint">Max 5 MB. Plik musi zawierać pole "bloki". Jeśli nie wgrasz pliku, zawody pojawią się jako zapowiedź.</small>
            </div>
            <div class="form-actions">
                <button type="submit" class="btn btn-primary">Zapisz</button>
                <a href="<?= BASE_URL ?>/admin/lista.php" class="btn btn-outline">Anuluj</a>
            </div>
        </form>
    </div>
</main>
</body>
</html>
