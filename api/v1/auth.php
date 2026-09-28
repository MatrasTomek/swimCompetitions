<?php
/**
 * Auth endpoints:
 *   POST /api/v1/auth/login  → {token, expires_at, role}
 *        admin: username = ADMIN_USER; club user: username = e-mail (MongoDB account)
 *   POST /api/v1/auth/logout → {ok: true}
 *   GET  /api/v1/auth/me     → {role, username}
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
        $username = trim((string)($body['username'] ?? ''));
        $password = (string)($body['password'] ?? '');

        if ($username === ADMIN_USER) {
            if (!password_verify($password, ADMIN_PASSWORD_HASH)) {
                auth_login_failed($ip);
                return;
            }
            login_rate_limit_clear($ip);
            auth_issue_token(['sub' => ADMIN_USER, 'role' => 'admin'], 'admin', ADMIN_USER);
            return;
        }

        // Club user — e-mail + password from MongoDB
        api_require_accounts();
        $user = filter_var($username, FILTER_VALIDATE_EMAIL) ? user_find_by_email($username) : null;
        if ($user === null) {
            password_hash($password, PASSWORD_DEFAULT); // same timing as a wrong password — no e-mail probing
        }
        if ($user === null || !password_verify($password, $user['userPassword'])) {
            auth_login_failed($ip);
            return;
        }
        login_rate_limit_clear($ip);

        // Correct password — now the account state may be told
        $blocked = [
            'pending_email'    => 'Potwierdź adres e-mail — link wysłaliśmy po rejestracji.',
            'pending_approval' => 'Konto czeka na aktywację przez administratora.',
            'disabled'         => 'Konto jest zablokowane. Skontaktuj się z administratorem.',
        ];
        if (isset($blocked[$user['status']])) {
            http_response_code(403);
            echo json_encode(['error' => $blocked[$user['status']], 'status' => $user['status']], JSON_UNESCAPED_UNICODE);
            return;
        }

        $rehash = password_needs_rehash($user['userPassword'], PASSWORD_DEFAULT) ? password_hash($password, PASSWORD_DEFAULT) : null;
        user_record_login($user['userId'], $rehash);
        auth_issue_token(['sub' => $user['userId'], 'role' => 'user', 'tv' => (int)$user['tokenVersion']], 'user', $user['userEmail']);
        return;
    }

    if ($sub === 'logout' && $method === 'POST') {
        // JWT is stateless; client discards the token
        echo json_encode(['ok' => true]);
        return;
    }

    if ($sub === 'me' && $method === 'GET') {
        $payload = api_require_auth();
        if (jwt_is_admin($payload)) {
            echo json_encode(['role' => 'admin', 'username' => ADMIN_USER]);
            return;
        }
        $user = api_require_user();
        echo json_encode(['role' => 'user', 'username' => $user['userEmail']], JSON_UNESCAPED_UNICODE);
        return;
    }

    http_response_code(404);
    echo json_encode(['error' => 'Not found']);
}

function auth_login_failed(string $ip): void {
    login_rate_limit_record_failure($ip);
    http_response_code(401);
    echo json_encode(['error' => 'Nieprawidłowy login lub hasło.'], JSON_UNESCAPED_UNICODE);
}

function auth_issue_token(array $claims, string $role, string $username): void {
    $now   = time();
    $exp   = $now + JWT_TTL;
    $token = jwt_encode($claims + ['iat' => $now, 'exp' => $exp], JWT_SECRET);

    echo json_encode([
        'token'      => $token,
        'expires_at' => date('c', $exp),
        'role'       => $role,
        'username'   => $username,
    ], JSON_UNESCAPED_UNICODE);
}
