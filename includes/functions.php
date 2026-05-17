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

function csrf_token(): string {
    if (session_status() === PHP_SESSION_NONE) session_start();
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_verify(): void {
    if (session_status() === PHP_SESSION_NONE) session_start();
    if (!hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'] ?? '')) {
        http_response_code(403);
        die('Błąd bezpieczeństwa – odśwież stronę i spróbuj ponownie.');
    }
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

// Loads all competitions from the directory, sorted by newest first
function load_all_zawody(): array {
    $files = glob(ZAWODY_DIR . '/*.json') ?: [];
    $list  = [];
    foreach ($files as $path) {
        $data = json_decode(file_get_contents($path), true);
        if ($data === null) continue;
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

    usort($list, fn($a, $b) => $b['mtime'] - $a['mtime']);
    return $list;
}

function load_zapowiedzi(): array {
    if (!file_exists(ZAPOWIEDZI_FILE)) return [];
    $data = json_decode(file_get_contents(ZAPOWIEDZI_FILE), true);
    return is_array($data) ? $data : [];
}

function save_zapowiedz(string $nazwa, string $miejsce, string $data, string $klub): string {
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
    file_put_contents(ZAPOWIEDZI_FILE, json_encode($zapowiedzi, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
    return $id;
}

function delete_zapowiedz(string $id): bool {
    $zapowiedzi = load_zapowiedzi();
    $filtered = array_values(array_filter($zapowiedzi, fn($z) => $z['id'] !== $id));
    if (count($filtered) === count($zapowiedzi)) return false;
    file_put_contents(ZAPOWIEDZI_FILE, json_encode($filtered, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
    return true;
}

function get_zapowiedz(string $id): ?array {
    foreach (load_zapowiedzi() as $z) {
        if ($z['id'] === $id) return $z;
    }
    return null;
}

// Safe file path — basename only from the zawody/ directory
function safe_json_path(string $filename): string {
    $name = basename($filename);
    if (!preg_match('/^[a-zA-Z0-9_\-]+\.json$/', $name)) return '';
    $path = ZAWODY_DIR . '/' . $name;
    return file_exists($path) ? $path : '';
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
