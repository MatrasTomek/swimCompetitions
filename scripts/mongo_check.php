<?php
/**
 * One-off hosting check (OVH): is the mongodb extension loaded and can the
 * server reach the Atlas cluster on port 27017? Upload, open once in the
 * browser, then DELETE this file.
 *
 *   /scripts/mongo_check.php?host=cluster0-shard-00-00.xxxxx.mongodb.net
 *
 * (host = one of the cluster's nodes from Atlas → Connect → "Standard connection string")
 */

header('Content-Type: text/plain; charset=utf-8');

echo 'PHP:               ', PHP_VERSION, "\n";
echo 'ext-mongodb:       ', extension_loaded('mongodb') ? 'TAK, wersja ' . phpversion('mongodb') : 'NIE', "\n";
echo 'vendor/autoload:   ', file_exists(__DIR__ . '/../vendor/autoload.php') ? 'TAK' : 'NIE', "\n";

$host = (string)($_GET['host'] ?? '');
if ($host !== '') {
    if (!preg_match('/^[a-z0-9.-]+\.mongodb\.net$/i', $host)) {
        echo "host: tylko *.mongodb.net\n";
        exit;
    }
    $errno = 0; $errstr = '';
    $fp = @fsockopen($host, 27017, $errno, $errstr, 5);
    echo "TCP $host:27017: ", $fp ? 'POŁĄCZONO' : "BŁĄD ($errno) $errstr", "\n";
    if ($fp) fclose($fp);
}
