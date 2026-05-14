<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

$filepath = safe_json_path($_GET['f'] ?? '');
if (!$filepath) {
    http_response_code(404); die('Nie znaleziono pliku.');
}

$json     = json_decode(file_get_contents($filepath), true);
$filename = basename($filepath);
$errors   = [];

// Editable fields
$fields = ['nazwa', 'miejsce', 'data', 'klub'];
$values = array_intersect_key($json, array_flip($fields));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    // Update metadata from form
    foreach ($fields as $field) {
        $json[$field] = trim($_POST[$field] ?? '');
    }

    // Optionally replace the entire file with a new JSON
    if (!empty($_FILES['plik']['name'])) {
        $result = validate_json_upload($_FILES['plik']);
        if (!$result['ok']) {
            $errors[] = $result['msg'];
        } else {
            $json = $result['decoded'];
        }
    }

    if (empty($errors)) {
        file_put_contents($filepath, json_encode($json, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
        $_SESSION['flash'] = 'Zawody "' . ($json['nazwa'] ?? $filename) . '" zostały zaktualizowane.';
        header('Location: ' . BASE_URL . '/admin/lista.php');
        exit;
    }

    $values = array_intersect_key($json, array_flip($fields));
}
?>
<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Edytuj zawody — Panel admina</title>
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
        <h1>Edytuj zawody</h1>
        <a href="<?= BASE_URL ?>/admin/lista.php" class="btn btn-outline">← Wróć do listy</a>
    </div>

    <?php if (!empty($errors)): ?>
        <div class="alert alert-error">
            <ul><?php foreach ($errors as $e): ?><li><?= h($e) ?></li><?php endforeach; ?></ul>
        </div>
    <?php endif; ?>

    <form method="post" enctype="multipart/form-data" class="admin-form">
        <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">

        <div class="form-group">
            <label for="nazwa">Nazwa zawodów</label>
            <input type="text" id="nazwa" name="nazwa" maxlength="255"
                   value="<?= h($values['nazwa'] ?? '') ?>">
        </div>
        <div class="form-row">
            <div class="form-group">
                <label for="miejsce">Miejscowość</label>
                <input type="text" id="miejsce" name="miejsce" maxlength="100"
                       value="<?= h($values['miejsce'] ?? '') ?>">
            </div>
            <div class="form-group">
                <label for="data">Data (np. 9-10/5/2026)</label>
                <input type="text" id="data" name="data" maxlength="30"
                       value="<?= h($values['data'] ?? '') ?>">
            </div>
        </div>
        <div class="form-group">
            <label for="klub">Klub</label>
            <input type="text" id="klub" name="klub" maxlength="255"
                   value="<?= h($values['klub'] ?? '') ?>">
        </div>

        <hr style="border:none;border-top:1px solid #e5e8ec;margin:1.25rem 0">

        <div class="form-group">
            <label for="plik">Zastąp plik wyników nowym JSON — opcjonalnie</label>
            <input type="file" id="plik" name="plik" accept=".json,application/json">
            <small class="form-hint">Plik: <code><?= h($filename) ?></code>. Wgraj nowy, aby zastąpić wyniki (metadane zostaną nadpisane z nowego pliku).</small>
        </div>

        <div class="form-actions">
            <button type="submit" class="btn btn-primary">Zapisz zmiany</button>
            <a href="<?= BASE_URL ?>/admin/lista.php" class="btn btn-outline">Anuluj</a>
        </div>
    </form>
</main>
</body>
</html>
