<?php
/**
 * Auth endpoints:
 *   POST /api/v1/auth/login  → {token, expires_at}
 *   POST /api/v1/auth/logout → {ok: true}
 *   GET  /api/v1/auth/me     → {username}
 */

require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/jwt.php';
require_once __DIR__ . '/require_auth.php';

function handle_auth(string $sub, string $method): void {
    if ($sub === 'login' && $method === 'POST') {
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';

        $lockout = login_rate_limit_check($ip);
        if ($lockout !== null) {
            http_response_code(429);
            echo json_encode(['error' => $lockout]);
            return;
        }

        $body     = json_decode(file_get_contents('php://input'), true) ?? [];
        $username = trim($body['username'] ?? '');
        $password = $body['password'] ?? '';

        if ($username !== ADMIN_USER || !password_verify($password, ADMIN_PASSWORD_HASH)) {
            login_rate_limit_record_failure($ip);
            http_response_code(401);
            echo json_encode(['error' => 'Nieprawidłowy login lub hasło.']);
            return;
        }

        login_rate_limit_clear($ip);

        $now    = time();
        $exp    = $now + JWT_TTL;
        $token  = jwt_encode(['sub' => $username, 'iat' => $now, 'exp' => $exp], JWT_SECRET);

        echo json_encode([
            'token'      => $token,
            'expires_at' => date('c', $exp),
        ]);
        return;
    }

    if ($sub === 'logout' && $method === 'POST') {
        // JWT is stateless; client discards the token
        echo json_encode(['ok' => true]);
        return;
    }

    if ($sub === 'me' && $method === 'GET') {
        $payload = api_require_auth();
        echo json_encode(['username' => $payload['sub']]);
        return;
    }

    http_response_code(404);
    echo json_encode(['error' => 'Not found']);
}
