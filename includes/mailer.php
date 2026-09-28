<?php
/**
 * Plain-text UTF-8 e-mail via PHP mail() — available on OVH web hosting
 * without SMTP configuration.
 */

require_once __DIR__ . '/config.php';

/**
 * From must be a mailbox in a domain hosted on the OVH account (CONTACT_FROM_EMAIL).
 * $to and $replyTo must already be validated addresses (no CR/LF).
 */
function send_mail_utf8(string $to, string $subject, string $text, ?string $replyTo = null): bool {
    $from = CONTACT_FROM_EMAIL;
    $headers = [
        'From: ' . mb_encode_mimeheader('Wyniki i Statystyki', 'UTF-8', 'B') . ' <' . $from . '>',
    ];
    if ($replyTo !== null) {
        $headers[] = 'Reply-To: ' . $replyTo;
    }
    $headers[] = 'MIME-Version: 1.0';
    $headers[] = 'Content-Type: text/plain; charset=UTF-8';
    $headers[] = 'Content-Transfer-Encoding: base64';

    $ok = mail(
        $to,
        mb_encode_mimeheader($subject, 'UTF-8', 'B'),
        chunk_split(base64_encode(str_replace("\n", "\r\n", str_replace("\r\n", "\n", $text)))),
        implode("\r\n", $headers),
        '-f' . $from
    );
    if (!$ok) {
        $last = error_get_last();
        error_log('mail() to ' . $to . ' from ' . $from . ' failed' . ($last ? ': ' . $last['message'] : ''));
    }
    return $ok;
}

/** Link to an Angular page for e-mails — the SPA uses hash routing (withHashLocation). */
function app_url(string $route): string {
    return rtrim(APP_PUBLIC_URL, '/') . '/#' . $route;
}
