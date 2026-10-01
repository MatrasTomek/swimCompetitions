<?php
/**
 * Router for the built-in PHP server started by test_contest_http_get.php — serves redirects,
 * oversized bodies and LENEX ZIPs. Runs only under `php -S`.
 */
if (PHP_SAPI !== 'cli-server') {
    http_response_code(404);
    exit;
}

$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$port = $_SERVER['SERVER_PORT'];
$dir  = getenv('SWIM_TEST_DIR') ?: sys_get_temp_dir();

switch ($path) {
    case '/ok.txt':
        echo 'hello';
        break;
    case '/redir-same':
        header('Location: /ok.txt', true, 302);
        break;
    case '/redir-relative':
        header('Location: ok.txt', true, 301);
        break;
    case '/redir-evil':
        // Same server, but under a host name outside the allowlist
        header("Location: http://localhost:$port/ok.txt", true, 302);
        break;
    case '/redir-chain-evil':
        header('Location: /redir-evil', true, 302);
        break;
    case '/redir-userinfo':
        header("Location: http://127.0.0.1@localhost:$port/ok.txt", true, 302);
        break;
    case '/loop':
        header('Location: /loop', true, 302);
        break;
    case '/big':
        echo str_repeat('x', 5000);
        break;
    case '/big-chunked':
        // Flushed output → no Content-Length, so only the read loop can stop it
        for ($i = 0; $i < 10; $i++) {
            echo str_repeat('x', 500);
            flush();
        }
        break;
    case '/missing':
        http_response_code(404);
        echo 'not found';
        break;
    case '/html':
        header('Content-Type: text/html');
        echo '<html>not yet</html>';
        break;
    case '/results.lxf':
    case '/bomb.lxf':
        header('Content-Type: application/octet-stream');
        readfile($dir . $path);
        break;
    default:
        http_response_code(404);
}
