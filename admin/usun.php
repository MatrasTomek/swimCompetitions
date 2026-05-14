<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

// Delete announcement (no JSON file)
$zap_id = trim($_GET['zap'] ?? $_POST['zap'] ?? '');
if ($zap_id !== '') {
    $zap = get_zapowiedz($zap_id);
    if (!$zap) {
        http_response_code(404); die('Nie znaleziono zapowiedzi.');
    }
    $nazwa = $zap['nazwa'] ?? $zap_id;

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        csrf_verify();
        delete_zapowiedz($zap_id);
        $_SESSION['flash'] = 'Zapowiedź "' . $nazwa . '" została usunięta.';
        header('Location: ' . BASE_URL . '/admin/lista.php');
        exit;
    }
    $is_zapowiedz = true;
    $filename     = '';
} else {
    // Delete competition with a JSON file
    $filepath = safe_json_path($_GET['f'] ?? $_POST['f'] ?? '');
    if (!$filepath) {
        http_response_code(404); die('Nie znaleziono pliku.');
    }
    $json     = json_decode(file_get_contents($filepath), true);
    $filename = basename($filepath);
    $nazwa    = $json['nazwa'] ?? $filename;

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        csrf_verify();
        unlink($filepath);
        $_SESSION['flash'] = 'Zawody "' . $nazwa . '" zostały usunięte.';
        header('Location: ' . BASE_URL . '/admin/lista.php');
        exit;
    }
    $is_zapowiedz = false;
}
?>
<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Usuń zawody — Panel admina</title>
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
    <div class="delete-confirm">
        <h1>Usuń zawody</h1>
        <p>Czy na pewno chcesz usunąć zawody <strong><?= h($nazwa) ?></strong>?</p>
        <?php if ($is_zapowiedz): ?>
            <p class="warning-text">Ta operacja jest nieodwracalna — zapowiedź zostanie usunięta.</p>
        <?php else: ?>
            <p class="warning-text">Ta operacja jest nieodwracalna — plik <code><?= h($filename) ?></code> zostanie usunięty.</p>
        <?php endif; ?>
        <form method="post" action="">
            <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
            <?php if ($is_zapowiedz): ?>
                <input type="hidden" name="zap" value="<?= h($zap_id) ?>">
            <?php else: ?>
                <input type="hidden" name="f" value="<?= h($filename) ?>">
            <?php endif; ?>
            <div class="form-actions">
                <button type="submit" class="btn btn-danger">Tak, usuń</button>
                <a href="<?= BASE_URL ?>/admin/lista.php" class="btn btn-outline">Anuluj</a>
            </div>
        </form>
    </div>
</main>
</body>
</html>
