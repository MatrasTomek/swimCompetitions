<?php
/**
 * Minimal assertions for the CLI test scripts in scripts/tests/ (the project has no test framework).
 * CLI only.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$GLOBALS['__failed'] = 0;

function check(string $label, $actual, $expected): void {
    if ($actual === $expected) {
        echo "ok   $label\n";
        return;
    }
    $GLOBALS['__failed']++;
    echo "FAIL $label\n  expected: " . var_export($expected, true) . "\n  actual:   " . var_export($actual, true) . "\n";
}

function finish(): never {
    $failed = $GLOBALS['__failed'];
    echo $failed ? "\n$failed FAILED\n" : "\nALL OK\n";
    exit($failed ? 1 : 0);
}
