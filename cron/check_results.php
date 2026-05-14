<?php
/**
 * Cron script — fetches results for pending entries.
 * Run every minute: * * * * * php /path/to/cron/check_results.php
 */
define('CRON_MODE', true);

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/athlete.php';
require_once __DIR__ . '/../includes/result_fetch.php';

$updated = process_pending_results();
echo date('c') . " | Zaktualizowano: {$updated} startów\n";
