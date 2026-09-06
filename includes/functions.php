<?php
function h(string $s): string {
    return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
}

function slugify(string $text): string {
    $map = [
        'ą'=>'a','ć'=>'c','ę'=>'e','ł'=>'l','ń'=>'n','ó'=>'o','ś'=>'s','ź'=>'z','ż'=>'z',
        'Ą'=>'a','Ć'=>'c','Ę'=>'e','Ł'=>'l','Ń'=>'n','Ó'=>'o','Ś'=>'s','Ź'=>'z','Ż'=>'z',
    ];
    $text = strtr($text, $map);
    $text = strtolower($text);
    $text = preg_replace('/[^a-z0-9]+/', '_', $text);
    return trim(substr($text, 0, 60), '_');
}

/**
 * Atomically writes JSON to disk (write-to-temp + rename) with an exclusive
 * lock, so a killed/overlapping request can't leave a corrupt or empty file.
 * Returns false if encoding failed (e.g. invalid UTF-8) or the write failed —
 * callers must check this instead of assuming the write succeeded.
 */
function write_json_atomic(string $path, $data): bool {
    $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    if ($json === false) return false;

    $tmp = $path . '.tmp.' . bin2hex(random_bytes(6));
    $fp  = fopen($tmp, 'c');
    if ($fp === false) return false;

    flock($fp, LOCK_EX);
    ftruncate($fp, 0);
    $ok = fwrite($fp, $json) !== false;
    fflush($fp);
    flock($fp, LOCK_UN);
    fclose($fp);

    if (!$ok || !rename($tmp, $path)) {
        @unlink($tmp);
        return false;
    }
    return true;
}

/**
 * Runs $fn while holding an exclusive lock on $lock_path, to serialize
 * read-modify-write cycles against the same data file across requests.
 */
function with_file_lock(string $lock_path, callable $fn) {
    $fp = fopen($lock_path, 'c');
    if ($fp === false) return $fn();
    flock($fp, LOCK_EX);
    try {
        return $fn();
    } finally {
        flock($fp, LOCK_UN);
        fclose($fp);
    }
}

/**
 * SSRF guard: only http(s) URLs on the livetiming.pl host (or a subdomain)
 * may be fetched server-side. Used for every admin-supplied contest URL.
 */
function is_allowed_contest_host(string $url): bool {
    $parts = parse_url($url);
    if (!$parts || empty($parts['scheme']) || empty($parts['host'])) return false;
    if (!in_array(strtolower($parts['scheme']), ['http', 'https'], true)) return false;

    $host   = strtolower($parts['host']);
    $suffix = strtolower(ALLOWED_CONTEST_HOST_SUFFIX);
    return $host === $suffix || str_ends_with($host, '.' . $suffix);
}

function login_attempts_load(): array {
    if (!file_exists(LOGIN_ATTEMPTS_FILE)) return [];
    $data = json_decode(file_get_contents(LOGIN_ATTEMPTS_FILE), true);
    return is_array($data) ? $data : [];
}

/** Returns a user-facing lockout message if $ip is currently locked out, else null. */
function login_rate_limit_check(string $ip): ?string {
    $rec = login_attempts_load()[$ip] ?? null;
    if ($rec && ($rec['locked_until'] ?? 0) > time()) {
        $mins = (int)ceil(($rec['locked_until'] - time()) / 60);
        return "Zbyt wiele nieudanych prób logowania. Spróbuj ponownie za {$mins} min.";
    }
    return null;
}

function login_rate_limit_record_failure(string $ip): void {
    with_file_lock(LOGIN_ATTEMPTS_FILE . '.lock', function () use ($ip) {
        $attempts = login_attempts_load();
        $now = time();

        foreach ($attempts as $k => $a) {
            if ($now - ($a['first'] ?? 0) > LOGIN_WINDOW_SECONDS && ($a['locked_until'] ?? 0) < $now) {
                unset($attempts[$k]);
            }
        }

        $rec = $attempts[$ip] ?? ['count' => 0, 'first' => $now, 'locked_until' => 0];
        if ($now - $rec['first'] > LOGIN_WINDOW_SECONDS) {
            $rec = ['count' => 0, 'first' => $now, 'locked_until' => 0];
        }
        $rec['count']++;
        if ($rec['count'] >= LOGIN_MAX_ATTEMPTS) {
            $rec['locked_until'] = $now + LOGIN_LOCKOUT_SECONDS;
        }
        $attempts[$ip] = $rec;
        write_json_atomic(LOGIN_ATTEMPTS_FILE, $attempts);
    });
}

function login_rate_limit_clear(string $ip): void {
    with_file_lock(LOGIN_ATTEMPTS_FILE . '.lock', function () use ($ip) {
        $attempts = login_attempts_load();
        if (isset($attempts[$ip])) {
            unset($attempts[$ip]);
            write_json_atomic(LOGIN_ATTEMPTS_FILE, $attempts);
        }
    });
}

function validate_json_upload(array $file): array {
    if ($file['error'] !== UPLOAD_ERR_OK) {
        return ['ok' => false, 'msg' => 'Błąd przesyłania pliku (kod ' . $file['error'] . ').'];
    }
    if ($file['size'] > MAX_JSON_SIZE) {
        return ['ok' => false, 'msg' => 'Plik jest za duży (max 5 MB).'];
    }
    if (strtolower(pathinfo($file['name'], PATHINFO_EXTENSION)) !== 'json') {
        return ['ok' => false, 'msg' => 'Plik musi mieć rozszerzenie .json.'];
    }
    $content = file_get_contents($file['tmp_name']);
    $decoded = json_decode($content, true);
    if ($decoded === null) {
        return ['ok' => false, 'msg' => 'Plik nie jest prawidłowym JSON-em.'];
    }
    if (empty($decoded['bloki']) || !is_array($decoded['bloki'])) {
        return ['ok' => false, 'msg' => 'Plik JSON nie zawiera wymaganego pola "bloki".'];
    }
    return ['ok' => true, 'decoded' => $decoded];
}

function parse_zawody_date(string $data): int {
    // Handles "9-10/5/2026", "9/5/2026"
    if (preg_match('/^(\d+)(?:-\d+)?\/(\d+)\/(\d{4})/', $data, $m)) {
        return mktime(0, 0, 0, (int)$m[2], (int)$m[1], (int)$m[3]);
    }
    return 0;
}

// Loads all competitions from the directory, sorted by newest first
function load_all_zawody(): array {
    $files = glob(ZAWODY_DIR . '/*.json') ?: [];
    $list  = [];
    foreach ($files as $path) {
        $data = json_decode(file_get_contents($path), true);
        if ($data === null) {
            error_log('load_all_zawody: nie udało się odczytać/sparsować ' . basename($path));
            continue;
        }
        $has_results = false;
        foreach ($data['bloki'] ?? [] as $blok) {
            foreach ($blok['starty'] ?? [] as $start) {
                if (!empty($start['result_fetched'])) { $has_results = true; break 2; }
            }
        }
        $list[] = [
            'file'        => basename($path),
            'nazwa'       => $data['nazwa']   ?? '(brak nazwy)',
            'klub'        => $data['klub']    ?? '',
            'miejsce'     => $data['miejsce'] ?? '',
            'data'        => $data['data']    ?? '',
            'mtime'       => filemtime($path),
            'has_file'    => true,
            'has_results' => $has_results,
            'id'          => '',
        ];
    }

    // Announcements without a start list
    $zapowiedzi = load_zapowiedzi();
    foreach ($zapowiedzi as $z) {
        $list[] = [
            'file'     => '',
            'nazwa'    => $z['nazwa']  ?? '(brak nazwy)',
            'klub'     => $z['klub']   ?? '',
            'miejsce'  => $z['miejsce'] ?? '',
            'data'     => $z['data']   ?? '',
            'mtime'    => $z['mtime']  ?? 0,
            'has_file' => false,
            'id'       => $z['id']     ?? '',
        ];
    }

    usort($list, fn($a, $b) => parse_zawody_date($b['data']) - parse_zawody_date($a['data']));
    return $list;
}

function load_zapowiedzi(): array {
    if (!file_exists(ZAPOWIEDZI_FILE)) return [];
    $data = json_decode(file_get_contents(ZAPOWIEDZI_FILE), true);
    return is_array($data) ? $data : [];
}

function save_zapowiedz(string $nazwa, string $miejsce, string $data, string $klub): ?string {
    return with_file_lock(ZAPOWIEDZI_FILE . '.lock', function () use ($nazwa, $miejsce, $data, $klub) {
        $zapowiedzi = load_zapowiedzi();
        $id = bin2hex(random_bytes(8));
        $zapowiedzi[] = [
            'id'      => $id,
            'nazwa'   => $nazwa,
            'miejsce' => $miejsce,
            'data'    => $data,
            'klub'    => $klub,
            'mtime'   => time(),
        ];
        return write_json_atomic(ZAPOWIEDZI_FILE, $zapowiedzi) ? $id : null;
    });
}

function delete_zapowiedz(string $id): bool {
    return with_file_lock(ZAPOWIEDZI_FILE . '.lock', function () use ($id) {
        $zapowiedzi = load_zapowiedzi();
        $filtered = array_values(array_filter($zapowiedzi, fn($z) => $z['id'] !== $id));
        if (count($filtered) === count($zapowiedzi)) return false;
        return write_json_atomic(ZAPOWIEDZI_FILE, $filtered);
    });
}

function get_zapowiedz(string $id): ?array {
    foreach (load_zapowiedzi() as $z) {
        if ($z['id'] === $id) return $z;
    }
    return null;
}

// Safe file path — basename-only *.json lookup, confined to $dir.
function safe_path(string $dir, string $filename): string {
    $name = basename($filename);
    if (!preg_match('/^[a-zA-Z0-9_\-]+\.json$/', $name)) return '';
    $path = rtrim($dir, '/') . '/' . $name;
    return file_exists($path) ? $path : '';
}

// Safe file path — basename only from the zawody/ directory
function safe_json_path(string $filename): string {
    return safe_path(ZAWODY_DIR, $filename);
}

// Shortens event name: "Kobiet, 400m zmienny" + nr 3 → "K3 400m zmienny"
function format_konkurencja(string $k, int $nr): string {
    $first = mb_strtoupper(mb_substr(trim($k), 0, 1, 'UTF-8'), 'UTF-8');
    $rest  = preg_replace('/^[^,]+,\s*/', '', $k);
    $skroty = [
        'grzbietowy' => 'grzbiet',
        'motylkowy'  => 'motyl',
        'klasyczny'  => 'klasyk',
    ];
    $rest = str_ireplace(array_keys($skroty), array_values($skroty), $rest);
    return $first . $nr . ' ' . $rest;
}

// Unique filename in the zawody/ directory
function unique_filename(string $base): string {
    $slug = slugify($base);
    $name = $slug . '.json';
    $i    = 2;
    while (file_exists(ZAWODY_DIR . '/' . $name)) {
        $name = $slug . '_' . $i++ . '.json';
    }
    return $name;
}
