<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/result_fetch.php';

header('Content-Type: application/json; charset=utf-8');

$config      = load_live_config();
$contest_url = $config['contest_url'] ?? '';

if (empty($contest_url)) {
    echo json_encode(['error' => 'Brak contest_url w live_config.json'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    exit;
}

$resolved = resolve_lenex_url($contest_url);
$lxf_url  = $resolved['url'];

$ctx  = stream_context_create([
    'http' => ['header' => "User-Agent: Mozilla/5.0 SwimResults/1.0\r\n", 'timeout' => 20],
    'ssl'  => ['verify_peer' => false, 'verify_peer_name' => false],
]);
$data = @file_get_contents($lxf_url, false, $ctx);

if ($data === false || $data === '') {
    echo json_encode(['error' => 'Nie można pobrać LXF', 'url' => $lxf_url], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    exit;
}

$tmp = tempnam(sys_get_temp_dir(), 'swim_lxf_') . '.zip';
file_put_contents($tmp, $data);
$zip = new ZipArchive();
if ($zip->open($tmp) !== true) {
    @unlink($tmp);
    echo json_encode(['error' => 'Nie można otworzyć ZIP'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    exit;
}

$files_in_zip = [];
$xml_content  = null;
for ($i = 0; $i < $zip->numFiles; $i++) {
    $name = $zip->getNameIndex($i);
    $files_in_zip[] = $name;
    if ($xml_content === null && (str_ends_with(strtolower($name), '.lef') || str_ends_with(strtolower($name), '.xml'))) {
        $xml_content = $zip->getFromIndex($i);
    }
}
$zip->close();
@unlink($tmp);

if ($xml_content === null) {
    echo json_encode(['error' => 'Brak .lef/.xml w ZIP', 'files_in_zip' => $files_in_zip], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    exit;
}

libxml_use_internal_errors(true);
$dom = @simplexml_load_string($xml_content);
if ($dom === false) {
    echo json_encode(['error' => 'Błąd parsowania XML'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    exit;
}

function attrs(SimpleXMLElement $el): array {
    $out = [];
    foreach ($el->attributes() as $k => $v) $out[(string)$k] = (string)$v;
    return $out;
}

$out = [
    'meta' => ['lxf_url' => $lxf_url, 'files_in_zip' => $files_in_zip],
];

// ── 1. First ATHLETE from each path ──────────────────────────────────────────
// Path A: MEET > ATHLETES > ATHLETE  (LENEX 2.x)
$direct_athletes = [];
foreach ($dom->MEETS->MEET as $meet) {
    foreach ($meet->ATHLETES->ATHLETE as $ath) {
        $direct_athletes[] = attrs($ath);
        break;
    }
    break;
}
$out['path_MEET_ATHLETES_first'] = $direct_athletes ?: 'brak';

// Path B: MEET > CLUBS > CLUB > ATHLETES > ATHLETE  (LENEX 3.0)
$club_athletes = [];
foreach ($dom->MEETS->MEET as $meet) {
    foreach ($meet->CLUBS->CLUB as $club) {
        $club_info = ['club_attrs' => attrs($club), 'first_athlete' => null];
        foreach ($club->ATHLETES->ATHLETE as $ath) {
            $club_info['first_athlete'] = attrs($ath);
            break;
        }
        $club_athletes[] = $club_info;
        if (count($club_athletes) >= 2) break; // show first 2 clubs
    }
    break;
}
$out['path_MEET_CLUBS_CLUB_ATHLETES_first2clubs'] = $club_athletes ?: 'brak';

// ── 2. First EVENT with its first RESULT ─────────────────────────────────────
$events_sample = [];
foreach ($dom->MEETS->MEET as $meet) {
    foreach ($meet->SESSIONS->SESSION as $session) {
        $sess_attrs = attrs($session);
        foreach ($session->EVENTS->EVENT as $event) {
            $ev = ['event_attrs' => attrs($event), 'results' => []];
            foreach ($event->RESULTS->RESULT as $res) {
                $ev['results'][] = attrs($res);
                if (count($ev['results']) >= 3) break;
            }
            $events_sample[] = ['session_attrs' => $sess_attrs, 'event' => $ev];
            if (count($events_sample) >= 3) break 2; // first 3 events total
        }
    }
    break;
}
$out['first_3_events_with_results'] = $events_sample ?: 'brak';

// ── 3. Children tags of MEET (to see available sections) ─────────────────────
$meet_children = [];
foreach ($dom->MEETS->MEET as $meet) {
    foreach ($meet->children() as $child) {
        $meet_children[] = $child->getName();
    }
    break;
}
$out['meet_direct_children_tags'] = array_unique($meet_children);

echo json_encode($out, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
