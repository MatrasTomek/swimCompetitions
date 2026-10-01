<?php
/**
 * contest_http_get() and lenex_download_xml(): redirects re-checked against the host allowlist,
 * size limits (download and unpacked LENEX). Starts a local `php -S` with fixtures/http_router.php,
 * so no network is needed:
 *   php scripts/tests/test_contest_http_get.php
 */
require __DIR__ . '/_assert.php';

// The local server stands in for livetiming.pl; "localhost" plays the foreign host
define('ALLOWED_CONTEST_HOST_SUFFIX', '127.0.0.1');
require __DIR__ . '/../../includes/config.php';
require __DIR__ . '/../../includes/functions.php';
require __DIR__ . '/../../includes/lenex_fetch.php';

$dir = sys_get_temp_dir() . '/swim_http_test_' . bin2hex(random_bytes(4));
mkdir($dir);

$zip = new ZipArchive();
$zip->open("$dir/results.lxf", ZipArchive::CREATE);
$zip->addFromString('meet.lef', '<LENEX version="3.0"/>');
$zip->close();
$zip = new ZipArchive();
$zip->open("$dir/bomb.lxf", ZipArchive::CREATE);
$zip->addFromString('meet.lef', str_repeat('a', LENEX_XML_MAX_BYTES + 1));
$zip->close();
check('bomb.lxf is small on the wire', filesize("$dir/bomb.lxf") < LENEX_MAX_BYTES, true);

$port = 18000 + random_int(0, 999);
$devNull = PHP_OS_FAMILY === 'Windows' ? 'NUL' : '/dev/null'; // the server's own log is not needed
$server = proc_open(
    [PHP_BINARY, '-S', "127.0.0.1:$port", __DIR__ . '/fixtures/http_router.php'],
    [1 => ['file', $devNull, 'w'], 2 => ['file', $devNull, 'w']],
    $pipes, null, ['SWIM_TEST_DIR' => $dir] + getenv() // keep the rest (Windows needs SystemRoot for sockets)
);
for ($i = 0; $i < 50 && !@fsockopen('127.0.0.1', $port); $i++) usleep(100000);
register_shutdown_function(function () use ($server, $dir) {
    proc_terminate($server);
    array_map('unlink', glob("$dir/*"));
    rmdir($dir);
});

$base = "http://127.0.0.1:$port";

$r = contest_http_get("$base/ok.txt", 1000);
check('plain GET', [$r['ok'], $r['status'], $r['body']], [true, 200, 'hello']);

$r = contest_http_get("$base/redir-same", 1000);
check('redirect on the allowed host is followed', [$r['ok'], $r['body']], [true, 'hello']);

$r = contest_http_get("$base/dir/redir-relative", 1000);
check('relative Location resolved against the path', $r['ok'], false); // /dir/ok.txt does not exist
check('relative Location → 404 of /dir/ok.txt', $r['status'], 404);

$r = contest_http_get("$base/redir-evil", 1000);
check('redirect to a foreign host is refused', [$r['ok'], str_contains($r['error'], 'Niedozwolony host')], [false, true]);

$r = contest_http_get("$base/redir-chain-evil", 1000);
check('foreign host later in the chain is refused', [$r['ok'], str_contains($r['error'], 'Niedozwolony host')], [false, true]);

$r = contest_http_get("$base/redir-userinfo", 1000);
check('userinfo trick (allowed@foreign) is refused', [$r['ok'], str_contains($r['error'], 'Niedozwolony host')], [false, true]);

$r = contest_http_get("$base/loop", 1000);
check('redirect loop stops', [$r['ok'], str_contains($r['error'], 'przekierowań')], [false, true]);

$r = contest_http_get("$base/big", 1000);
check('Content-Length over the limit', [$r['ok'], str_contains($r['error'], 'za duży')], [false, true]);

$r = contest_http_get("$base/big-chunked", 1000);
check('body over the limit without Content-Length', [$r['ok'], str_contains($r['error'], 'za duży')], [false, true]);

$r = contest_http_get("$base/big", 5000);
check('body exactly at the limit is fine', [$r['ok'], strlen($r['body'] ?? '')], [true, 5000]);

$r = contest_http_get("$base/missing", 1000);
check('404 is an error with the status', [$r['ok'], $r['status']], [false, 404]);

$r = contest_http_get('http://localhost:' . $port . '/ok.txt', 1000);
check('foreign start URL is refused without a request', [$r['ok'], $r['status']], [false, 0]);

check('Location: absolute', contest_resolve_location('https://a.pl/x/y', 'http://b.pl/z'), 'http://b.pl/z');
check('Location: scheme-relative', contest_resolve_location('https://a.pl/x/y', '//b.pl/z'), 'https://b.pl/z');
check('Location: root path', contest_resolve_location('https://a.pl:8443/x/y', '/z'), 'https://a.pl:8443/z');
check('Location: relative path', contest_resolve_location('https://a.pl/x/y', 'z'), 'https://a.pl/x/z');

$r = lenex_download_xml("$base/results.lxf");
check('LENEX download + unzip', $r, ['ok' => true, 'xml' => '<LENEX version="3.0"/>']);

$r = lenex_download_xml("$base/redir-evil");
check('LENEX: redirect to a foreign host refused', [$r['ok'], str_contains($r['error'], 'Niedozwolony host')], [false, true]);

$r = lenex_download_xml("$base/bomb.lxf");
check('LENEX: unpacked size over the limit', $r, ['ok' => false, 'error' => 'Plik LENEX po rozpakowaniu jest za duży']);

$r = lenex_download_xml("$base/html");
check('LENEX: HTML instead of .lxf', [$r['ok'], str_contains($r['error'], 'jeszcze niedostępne')], [false, true]);

finish();
