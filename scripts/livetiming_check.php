<?php
/**
 * One-off hosting check (OVH): can the server download pages from livetiming.pl?
 * The contest search cache, the start list import and the LENEX results all need it.
 *
 * Upload to the web root (next to index.html), open once in the browser:
 *   https://your-domain/livetiming_check.php
 * then DELETE this file.
 */

header('Content-Type: text/plain; charset=utf-8');

// ?url=… checks another livetiming.pl address, e.g. a start list PDF on live.livetiming.pl
$url  = 'https://livetiming.pl/contests/regional/1';
if (isset($_GET['url']) && preg_match('#^https://([a-z0-9-]+\.)?livetiming\.pl/#i', $_GET['url'])) {
    $url = $_GET['url'];
}
echo 'URL:               ', $url, "\n";
$host = parse_url($url, PHP_URL_HOST);

echo 'PHP:               ', PHP_VERSION, "\n";
echo 'allow_url_fopen:   ', ini_get('allow_url_fopen') ? 'TAK' : 'NIE', "\n";
echo 'ext-openssl:       ', extension_loaded('openssl') ? 'TAK' : 'NIE', "\n";
echo 'ext-curl:          ', extension_loaded('curl') ? 'TAK' : 'NIE', "\n";
echo 'https wrapper:     ', in_array('https', stream_get_wrappers(), true) ? 'TAK' : 'NIE', "\n";

$ips = @gethostbynamel($host);
echo 'DNS ', $host, ':  ', $ips ? implode(', ', $ips) : 'BŁĄD', "\n\n";

// 1. PHP streams — what the cache, the start list and LENEX downloads use
$ctx = stream_context_create([
    'http' => [
        'header'        => "User-Agent: Mozilla/5.0 SwimResults/1.0\r\n",
        'timeout'       => 15,
        'ignore_errors' => true,
    ],
]);
$t = microtime(true);
$html = @file_get_contents($url, false, $ctx);
$ms = round((microtime(true) - $t) * 1000);
if ($html === false) {
    echo "file_get_contents: BŁĄD po {$ms} ms — ", error_get_last()['message'] ?? '?', "\n";
} else {
    $status = $http_response_header[0] ?? '?';
    $n = preg_match_all('#href="/contest/[0-9a-f-]{36}"#', $html);
    echo "file_get_contents: {$status}, ", strlen($html), " B, linków do zawodów: {$n}, {$ms} ms\n";
}

// 2. cURL — for comparison
if (extension_loaded('curl')) {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 15,
        CURLOPT_USERAGENT      => 'Mozilla/5.0 SwimResults/1.0',
    ]);
    $t = microtime(true);
    $body = curl_exec($ch);
    $ms = round((microtime(true) - $t) * 1000);
    if ($body === false) {
        echo "cURL:              BŁĄD po {$ms} ms — ", curl_error($ch), "\n";
    } else {
        $code = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $n = preg_match_all('#href="/contest/[0-9a-f-]{36}"#', $body);
        echo "cURL:              HTTP {$code}, ", strlen($body), " B, linków do zawodów: {$n}, {$ms} ms\n";
    }
    curl_close($ch);
}

echo "\nPo sprawdzeniu USUŃ ten plik z serwera.\n";
