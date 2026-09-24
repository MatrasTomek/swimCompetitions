<?php
/**
 * Registration / contact form:
 *   POST /api/v1/contact → send the form as an e-mail to CONTACT_TO_EMAIL (public, rate-limited per IP)
 *
 * Uses PHP mail() — available on OVH web hosting without SMTP configuration.
 */

require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/functions.php';

function handle_contact(string $sub, string $method): void {
    if ($sub !== '' || $method !== 'POST') {
        http_response_code(405);
        echo json_encode(['error' => 'Method Not Allowed']);
        return;
    }

    $body = json_decode(file_get_contents('php://input'), true);
    if (!is_array($body)) $body = [];

    // Honeypot — the field is hidden in the form, only bots fill it in.
    // Pretend success so they don't learn anything.
    if (trim((string)($body['website'] ?? '')) !== '') {
        echo json_encode(['ok' => true]);
        return;
    }

    $imie       = contact_field($body, 'imie', 100);
    $email      = contact_field($body, 'email', 150);
    $telefon    = contact_field($body, 'telefon', 30);
    $klub       = contact_field($body, 'klub', 150);
    $zawodnicy  = contact_field($body, 'zawodnicy', 1000, true);
    $wiadomosc  = contact_field($body, 'wiadomosc', 2000, true);
    $zgoda      = ($body['zgoda'] ?? false) === true;

    $error = null;
    if ($imie === '')                                     $error = 'Podaj imię i nazwisko.';
    elseif (!filter_var($email, FILTER_VALIDATE_EMAIL))   $error = 'Podaj poprawny adres e-mail.';
    elseif ($telefon !== '' && !preg_match('/^[0-9 +()\-]{6,30}$/', $telefon)) $error = 'Podaj poprawny numer telefonu.';
    elseif (!$zgoda)                                      $error = 'Zaakceptuj zgodę na przetwarzanie danych.';

    if ($error !== null) {
        http_response_code(422);
        echo json_encode(['error' => $error], JSON_UNESCAPED_UNICODE);
        return;
    }

    if (contact_rate_limited($_SERVER['REMOTE_ADDR'] ?? 'unknown')) {
        http_response_code(429);
        echo json_encode(['error' => 'Wysłano zbyt wiele zgłoszeń. Spróbuj ponownie później.'], JSON_UNESCAPED_UNICODE);
        return;
    }

    $lines = [
        'Nowe zgłoszenie rejestracji — Wyniki i Statystyki',
        '',
        'Imię i nazwisko: ' . $imie,
        'E-mail:          ' . $email,
        'Telefon:         ' . ($telefon !== '' ? $telefon : '—'),
        'Klub:            ' . ($klub !== '' ? $klub : '—'),
        '',
        'Zawodnicy:',
        $zawodnicy !== '' ? $zawodnicy : '—',
        '',
        'Wiadomość:',
        $wiadomosc !== '' ? $wiadomosc : '—',
        '',
        '---',
        'Zgoda na przetwarzanie danych: tak',
        'Wysłano: ' . date('Y-m-d H:i:s'),
        'IP: ' . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'),
    ];

    $sent = contact_send_mail(
        CONTACT_TO_EMAIL,
        'Rejestracja — Wyniki i Statystyki: ' . $imie,
        str_replace("\n", "\r\n", implode("\n", $lines)),
        $email
    );

    if (!$sent) {
        http_response_code(500);
        echo json_encode(['error' => 'Nie udało się wysłać zgłoszenia. Spróbuj ponownie później lub napisz na ' . CONTACT_TO_EMAIL . '.'], JSON_UNESCAPED_UNICODE);
        return;
    }

    echo json_encode(['ok' => true]);
}

/**
 * Reads a string field from the request body, trimmed and cut to $max characters.
 * Single-line fields have all control characters (incl. CR/LF) removed, which
 * also rules out e-mail header injection via fields used in headers.
 */
function contact_field(array $body, string $key, int $max, bool $multiline = false): string {
    $value = $body[$key] ?? '';
    if (!is_string($value)) return '';
    $value = $multiline
        ? preg_replace('/[^\P{C}\n\t]/u', '', str_replace("\r\n", "\n", $value))
        : preg_replace('/\p{C}/u', '', $value);
    return mb_substr(trim((string)$value), 0, $max, 'UTF-8');
}

/**
 * Sends a UTF-8 plain-text e-mail via mail().
 * From must be a mailbox in a domain hosted on the OVH account (CONTACT_FROM_EMAIL);
 * the visitor's address goes into Reply-To, so "Reply" answers them directly.
 */
function contact_send_mail(string $to, string $subject, string $text, string $replyTo): bool {
    $from = CONTACT_FROM_EMAIL;
    $headers = [
        'From: ' . mb_encode_mimeheader('Wyniki i Statystyki', 'UTF-8', 'B') . ' <' . $from . '>',
        'Reply-To: ' . $replyTo,
        'MIME-Version: 1.0',
        'Content-Type: text/plain; charset=UTF-8',
        'Content-Transfer-Encoding: base64',
    ];

    return mail(
        $to,
        mb_encode_mimeheader($subject, 'UTF-8', 'B'),
        chunk_split(base64_encode($text)),
        implode("\r\n", $headers),
        '-f' . $from
    );
}
