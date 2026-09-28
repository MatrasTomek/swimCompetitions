<?php
/**
 * Dev only: prints the links from e-mails the API "sent" in the dev container
 * (bodies in /tmp/mails.log are base64), newest last.
 * Usage: docker compose -f dev/docker-compose.yml exec api php dev/mail_links.php
 */
$log = @file_get_contents('/tmp/mails.log');
if ($log === false) exit("Brak /tmp/mails.log — API nie wysłało jeszcze żadnego maila.\n");
preg_match_all('~(?:^[A-Za-z0-9+/=]{16,}\r?\n)+~m', $log, $m);
foreach ($m[0] as $block) {
    preg_match_all('~https?://\S+~', base64_decode(preg_replace('~\s~', '', $block)), $urls);
    foreach ($urls[0] as $url) echo $url, PHP_EOL;
}
