<?php
/**
 * Club user accounts (MongoDB):
 *   POST   /account/register                          → e-mail confirmation link (public, rate-limited)
 *   POST   /account/verify-email                      → pending_email → pending_approval (public)
 *   POST   /account/forgot-password                   → password reset link (public, rate-limited)
 *   POST   /account/reset-password                    → new password from the link (public)
 *   GET    /account/me                                → own account incl. club members
 *   PATCH  /account/me                                → {userClub?, userInvoice?}
 *   DELETE /account/me                                → {password} — removes the account
 *   POST   /account/change-password                   → {currentPassword, newPassword} → new token
 *   POST   /account/members                           → add a club member
 *   PATCH  /account/members/{memberId}                → edit a club member
 *   DELETE /account/members/{memberId}                → remove a club member (and the member's results)
 *   POST   /account/results/fetch                     → {contest_url} — LENEX results of the club's members
 *   GET    /account/results?year=YYYY[&memberId=…]    → results of one season (optionally one member)
 *
 * Public responses never reveal whether an e-mail is registered.
 */

require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/jwt.php';
require_once __DIR__ . '/../../includes/mailer.php';
require_once __DIR__ . '/require_auth.php';
require_once __DIR__ . '/auth.php';

function handle_account(string $seg1, string $seg2, string $seg3, string $seg4, string $method): void {
    api_require_accounts();
    $body = json_decode(file_get_contents('php://input'), true);
    if (!is_array($body)) $body = [];

    switch ("$method $seg1") {
        case 'POST register':        account_register($body); return;
        case 'POST verify-email':    account_verify_email($body); return;
        case 'POST forgot-password': account_forgot_password($body); return;
        case 'POST reset-password':  account_reset_password($body); return;
    }

    $user = api_require_user();

    if ($seg1 === 'me' && $seg2 === '') {
        if ($method === 'GET') {
            echo json_encode(user_public($user), JSON_UNESCAPED_UNICODE);
            return;
        }
        if ($method === 'PATCH') {
            $fields = [];
            if (array_key_exists('userClub', $body)) {
                $fields['userClub'] = account_text($body['userClub'], 150);
                if ($fields['userClub'] === '') { account_error(422, 'Podaj nazwę klubu.'); return; }
            }
            if (array_key_exists('userInvoice', $body)) {
                [$invoice, $err] = user_invoice_validate($body['userInvoice']);
                if ($err !== null) { account_error(422, $err); return; }
                $fields['userInvoice'] = $invoice;
            }
            if ($fields) user_update_profile($user['userId'], $fields);
            echo json_encode(user_public(user_find_by_id($user['userId'])), JSON_UNESCAPED_UNICODE);
            return;
        }
        if ($method === 'DELETE') {
            if (!password_verify((string)($body['password'] ?? ''), $user['userPassword'])) {
                account_error(422, 'Nieprawidłowe hasło.');
                return;
            }
            user_delete($user['userId']);
            echo json_encode(['ok' => true]);
            return;
        }
    }

    if ($seg1 === 'change-password' && $method === 'POST') {
        if (!password_verify((string)($body['currentPassword'] ?? ''), $user['userPassword'])) {
            account_error(422, 'Obecne hasło jest nieprawidłowe.');
            return;
        }
        $new = (string)($body['newPassword'] ?? '');
        if (($err = account_password_error($new)) !== null) {
            account_error(422, $err);
            return;
        }
        $tv = user_change_password($user['userId'], password_hash($new, PASSWORD_DEFAULT));
        // Other sessions are logged out; this one gets a fresh token
        auth_issue_token(['sub' => $user['userId'], 'role' => 'user', 'tv' => $tv], 'user', $user['userEmail']);
        return;
    }

    if ($seg1 === 'members') {
        account_members($user, $seg2, $seg3, $method, $body);
        return;
    }

    if ($seg1 === 'results') {
        account_results($user, $seg2, $method, $body);
        return;
    }

    account_error(404, 'Not found');
}

// ── Public flows ─────────────────────────────────────────────────────────────

function account_register(array $body): void {
    // Honeypot — hidden in the form, only bots fill it in
    if (trim((string)($body['website'] ?? '')) !== '') {
        echo json_encode(['ok' => true]);
        return;
    }

    $email    = user_normalize_email((string)($body['email'] ?? ''));
    $password = (string)($body['password'] ?? '');
    $club     = account_text($body['userClub'] ?? '', 150);

    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 150) {
        account_error(422, 'Podaj poprawny adres e-mail.');
        return;
    }
    if (($err = account_password_error($password)) !== null) {
        account_error(422, $err);
        return;
    }
    if ($club === '') {
        account_error(422, 'Podaj nazwę klubu.');
        return;
    }
    [$invoice, $err] = user_invoice_validate($body['userInvoice'] ?? null);
    if ($err !== null) {
        account_error(422, $err);
        return;
    }
    if (($body['zgoda'] ?? false) !== true) {
        account_error(422, 'Zaakceptuj zgodę na przetwarzanie danych.');
        return;
    }
    if (account_rate_limited()) return;

    $token = user_create($email, password_hash($password, PASSWORD_DEFAULT), $club, $invoice);
    if ($token !== null) {
        account_mail_verify($email, $token);
    } else {
        // E-mail already registered: resend the confirmation link if it was never
        // confirmed, otherwise tell the owner (not the visitor) that the account exists.
        $existing = user_find_by_email($email);
        $again    = $existing ? user_reissue_verify_token($existing['userId']) : null;
        if ($again !== null) {
            account_mail_verify($email, $again);
        } elseif ($existing !== null) {
            send_mail_utf8($email, 'Konto już istnieje — Wyniki i Statystyki', implode("\n", [
                'Ktoś (być może Ty) próbował założyć konto na ten adres e-mail, ale konto już istnieje.',
                '',
                'Jeśli nie pamiętasz hasła, ustaw nowe:',
                app_url('/konto/zapomniane-haslo'),
                '',
                'Jeśli to nie Ty — zignoruj tę wiadomość.',
            ]));
        }
    }

    echo json_encode(['ok' => true]);
}

function account_verify_email(array $body): void {
    $token = (string)($body['token'] ?? '');
    $user  = preg_match('/^[0-9a-f]{64}$/', $token) ? user_verify_email($token) : null;
    if ($user === null) {
        account_error(400, 'Link jest nieprawidłowy lub wygasł. Zarejestruj się ponownie, aby otrzymać nowy.');
        return;
    }

    send_mail_utf8(CONTACT_TO_EMAIL, 'Nowe konto do aktywacji: ' . $user['userEmail'], implode("\n", [
        'Nowe konto potwierdziło adres e-mail i czeka na aktywację — Wyniki i Statystyki',
        '',
        'E-mail: ' . $user['userEmail'],
        'Klub:   ' . ($user['userClub'] ?? '—'),
        '',
        'Dane do faktury:',
        '  ' . ($user['userInvoice']['companyName'] ?? '—'),
        '  ' . ($user['userInvoice']['street'] ?? '—'),
        '  ' . ($user['userInvoice']['postalCode'] ?? '') . ' ' . ($user['userInvoice']['city'] ?? ''),
        '  NIP: ' . ($user['userInvoice']['nip'] ?? '—'),
        '',
        'Aktywuj w panelu administratora:',
        app_url('/admin/uzytkownicy'),
    ]), $user['userEmail']);

    echo json_encode(['ok' => true]);
}

function account_forgot_password(array $body): void {
    $email = user_normalize_email((string)($body['email'] ?? ''));
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        account_error(422, 'Podaj poprawny adres e-mail.');
        return;
    }
    if (account_rate_limited()) return;

    $token = user_start_reset($email);
    if ($token !== null) {
        $minutes = intdiv(ACCOUNT_RESET_TTL, 60);
        send_mail_utf8($email, 'Ustaw nowe hasło — Wyniki i Statystyki', implode("\n", [
            'Otrzymaliśmy prośbę o zmianę hasła do konta Wyniki i Statystyki.',
            '',
            "Ustaw nowe hasło (link ważny $minutes minut, jednorazowy):",
            app_url('/konto/reset-hasla?token=' . $token),
            '',
            'Jeśli to nie Ty — zignoruj tę wiadomość, hasło pozostanie bez zmian.',
        ]));
    }

    echo json_encode(['ok' => true]);
}

function account_reset_password(array $body): void {
    $token    = (string)($body['token'] ?? '');
    $password = (string)($body['password'] ?? '');
    if (($err = account_password_error($password)) !== null) {
        account_error(422, $err);
        return;
    }
    if (!preg_match('/^[0-9a-f]{64}$/', $token) || !user_finish_reset($token, password_hash($password, PASSWORD_DEFAULT))) {
        account_error(400, 'Link jest nieprawidłowy lub wygasł. Poproś o nowy.');
        return;
    }
    echo json_encode(['ok' => true]);
}

// ── Club members ─────────────────────────────────────────────────────────────

function account_members(array $user, string $memberId, string $sub, string $method, array $body): void {
    $uid = $user['userId'];

    if ($memberId === '' && $method === 'POST') {
        [$fields, $err] = member_validate($body);
        if ($err !== null) { account_error(422, $err); return; }
        $member = member_add($uid, $fields);
        if ($member === null) { account_error(422, 'Osiągnięto limit ' . ACCOUNT_MAX_MEMBERS . ' zawodników.'); return; }
        http_response_code(201);
        echo json_encode($member, JSON_UNESCAPED_UNICODE);
        return;
    }

    if (!is_uuid($memberId)) { account_error(404, 'Nie znaleziono zawodnika.'); return; }

    if ($sub === '' && $method === 'PATCH') {
        [$fields, $err] = member_validate($body, true);
        if ($err !== null) { account_error(422, $err); return; }
        if (!$fields || !member_update($uid, $memberId, $fields)) { account_error(404, 'Nie znaleziono zawodnika.'); return; }
        echo json_encode(['ok' => true]);
        return;
    }

    if ($sub === '' && $method === 'DELETE') {
        if (!member_delete($uid, $memberId)) { account_error(404, 'Nie znaleziono zawodnika.'); return; }
        echo json_encode(['ok' => true]);
        return;
    }

    account_error(404, 'Not found');
}

// ── Results ──────────────────────────────────────────────────────────────────

function account_results(array $user, string $sub, string $method, array $body): void {
    if ($sub === 'fetch' && $method === 'POST') {
        require_once __DIR__ . '/../../includes/results_import.php';
        [$status, $out] = results_import_contest($user, trim((string)($body['contest_url'] ?? '')));
        http_response_code($status);
        echo json_encode($out, JSON_UNESCAPED_UNICODE);
        return;
    }

    if ($sub === '' && $method === 'GET') {
        $year = filter_var($_GET['year'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 2000, 'max_range' => (int)date('Y') + 1]]);
        if ($year === false) { account_error(422, 'Podaj poprawny rok sezonu.'); return; }

        $memberId = isset($_GET['memberId']) ? (string)$_GET['memberId'] : null;
        if ($memberId !== null) {
            $ids = array_column($user['clubItems']['clubMembers'] ?? [], 'memberId');
            if (!is_uuid($memberId) || !in_array($memberId, $ids, true)) { account_error(404, 'Nie znaleziono zawodnika.'); return; }
        }

        echo json_encode(results_list($user['userId'], $year, $memberId), JSON_UNESCAPED_UNICODE);
        return;
    }

    account_error(404, 'Not found');
}

// ── Helpers ──────────────────────────────────────────────────────────────────

function account_error(int $code, string $error): void {
    http_response_code($code);
    echo json_encode(['error' => $error], JSON_UNESCAPED_UNICODE);
}

/** Single-line text: control characters removed, trimmed, cut to $max characters. */
function account_text($value, int $max): string {
    if (!is_string($value)) return '';
    return mb_substr(trim((string)preg_replace('/\p{C}/u', '', $value)), 0, $max, 'UTF-8');
}

function account_password_error(string $password): ?string {
    $len = mb_strlen($password, 'UTF-8');
    if ($len < ACCOUNT_PASSWORD_MIN) return 'Hasło musi mieć co najmniej ' . ACCOUNT_PASSWORD_MIN . ' znaków.';
    if (strlen($password) > 72)      return 'Hasło jest za długie (maks. 72 bajty).'; // bcrypt limit
    return null;
}

/** Per-IP limit for requests that send e-mails; sends the 429 itself. */
function account_rate_limited(): bool {
    if (!ip_rate_limited(ACCOUNT_RATE_FILE, ACCOUNT_MAX, ACCOUNT_WINDOW, $_SERVER['REMOTE_ADDR'] ?? 'unknown')) {
        return false;
    }
    account_error(429, 'Zbyt wiele prób. Spróbuj ponownie później.');
    return true;
}

function account_mail_verify(string $email, string $token): void {
    $hours = intdiv(ACCOUNT_VERIFY_TTL, 3600);
    send_mail_utf8($email, 'Potwierdź adres e-mail — Wyniki i Statystyki', implode("\n", [
        'Dziękujemy za rejestrację w serwisie Wyniki i Statystyki.',
        '',
        "Potwierdź adres e-mail (link ważny $hours godz.):",
        app_url('/konto/potwierdz?token=' . $token),
        '',
        'Po potwierdzeniu konto zostanie aktywowane przez administratora — damy znać e-mailem.',
        'Jeśli to nie Ty — zignoruj tę wiadomość.',
    ]));
}
