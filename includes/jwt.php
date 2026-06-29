<?php
/**
 * Minimal JWT HS256 implementation — no external dependencies.
 */

function _jwt_b64u_encode(string $data): string {
    return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
}

function _jwt_b64u_decode(string $data): string {
    $pad = (4 - strlen($data) % 4) % 4;
    return base64_decode(strtr($data, '-_', '+/') . str_repeat('=', $pad));
}

function jwt_encode(array $payload, string $secret): string {
    $header  = _jwt_b64u_encode(json_encode(['alg' => 'HS256', 'typ' => 'JWT']));
    $body    = _jwt_b64u_encode(json_encode($payload));
    $sig     = _jwt_b64u_encode(hash_hmac('sha256', "$header.$body", $secret, true));
    return "$header.$body.$sig";
}

/** Returns payload array, or null if token is invalid or expired. */
function jwt_decode(string $token, string $secret): ?array {
    $parts = explode('.', $token);
    if (count($parts) !== 3) return null;

    [$header, $body, $sig] = $parts;
    $expected = _jwt_b64u_encode(hash_hmac('sha256', "$header.$body", $secret, true));
    if (!hash_equals($expected, $sig)) return null;

    $data = json_decode(_jwt_b64u_decode($body), true);
    if (!is_array($data)) return null;
    if (isset($data['exp']) && $data['exp'] < time()) return null;

    return $data;
}
