<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';

if (session_status() === PHP_SESSION_NONE) session_start();

if (!empty($_SESSION['admin_logged_in'])) {
    header('Location: ' . BASE_URL . '/admin/lista.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user = $_POST['username'] ?? '';
    $pass = $_POST['password'] ?? '';

    if ($user === ADMIN_USER && password_verify($pass, ADMIN_PASSWORD_HASH)) {
        session_regenerate_id(true);
        $_SESSION['admin_logged_in'] = true;
        header('Location: ' . BASE_URL . '/admin/lista.php');
        exit;
    }
    $error = 'Nieprawidłowy login lub hasło.';
}
?>
<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Logowanie — Panel admina</title>
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/style.css?v=<?= CSS_VERSION ?>">
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/admin.css?v=<?= CSS_VERSION ?>">
</head>
<body class="login-page">
<div class="login-box">
    <div class="login-logo">
        <img src="<?= BASE_URL ?>/logo.jpg" alt="Olimpijczyk Proszówki">
    </div>
    <h1>Panel administracyjny</h1>
    <p class="login-subtitle">Zawody pływackie</p>
    <?php if ($error): ?>
        <div class="alert alert-error"><?= h($error) ?></div>
    <?php endif; ?>
    <form method="post" action="">
        <div class="form-group">
            <label for="username">Login</label>
            <input type="text" id="username" name="username" required autocomplete="username"
                   value="<?= h($_POST['username'] ?? '') ?>">
        </div>
        <div class="form-group">
            <label for="password">Hasło</label>
            <input type="password" id="password" name="password" required autocomplete="current-password">
        </div>
        <button type="submit" class="btn btn-primary btn-full">Zaloguj się</button>
    </form>
</div>
</body>
</html>
