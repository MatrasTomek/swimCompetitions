<?php
/**
 * Club user accounts — admin only:
 *   GET   /users            → account list (no password hashes/tokens, no club members)
 *   PATCH /users/{userId}   → {status: active|disabled|pending_approval}
 *
 * Activating an account e-mails its owner.
 */

require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/mailer.php';
require_once __DIR__ . '/require_auth.php';

function handle_users(string $userId, string $method): void {
    api_require_admin();
    api_require_accounts();

    if ($userId === '' && $method === 'GET') {
        echo json_encode(user_list(), JSON_UNESCAPED_UNICODE);
        return;
    }

    if ($userId !== '' && $method === 'PATCH') {
        $body   = json_decode(file_get_contents('php://input'), true) ?? [];
        $status = $body['status'] ?? null;
        // pending_email is only set by registration itself
        if (!in_array($status, ['active', 'disabled', 'pending_approval'], true)) {
            http_response_code(422);
            echo json_encode(['error' => 'Nieprawidłowy status.'], JSON_UNESCAPED_UNICODE);
            return;
        }

        $before = is_uuid($userId) ? user_find_by_id($userId) : null;
        $user   = $before ? user_set_status($userId, $status) : null;
        if ($user === null) {
            http_response_code(404);
            echo json_encode(['error' => 'Nie znaleziono konta.'], JSON_UNESCAPED_UNICODE);
            return;
        }

        if ($status === 'active' && $before['status'] !== 'active') {
            send_mail_utf8($user['userEmail'], 'Konto aktywne — Wyniki i Statystyki', implode("\n", [
                'Twoje konto w serwisie Wyniki i Statystyki zostało aktywowane.',
                '',
                'Zaloguj się adresem e-mail i hasłem podanym przy rejestracji:',
                app_url('/logowanie'),
            ]));
        }

        echo json_encode(user_summary($user), JSON_UNESCAPED_UNICODE);
        return;
    }

    http_response_code(404);
    echo json_encode(['error' => 'Not found']);
}
