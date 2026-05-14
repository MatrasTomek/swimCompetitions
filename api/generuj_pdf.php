<?php
/**
 * Generates a PDF with club athletes' results for the active competition.
 * GET /api/generuj_pdf.php?zawody=olimpijczyk_brzesko
 */
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../fpdf/fpdf.php';

$json_file = preg_replace('/\.json$/', '', basename($_GET['zawody'] ?? ''));
$path = safe_json_path($json_file . '.json');
if (!$path) {
    http_response_code(404);
    die('Nie znaleziono pliku zawodów.');
}

$zawody = json_decode(file_get_contents($path), true);
if (!$zawody) {
    http_response_code(500);
    die('Błąd odczytu danych.');
}

// --- Build results table ---
$rows = [];
foreach ($zawody['bloki'] ?? [] as $blok) {
    foreach ($blok['starty'] ?? [] as $s) {
        $rows[] = [
            'blok'      => $blok['blok'] ?? '',
            'data'      => $blok['data'] ?? '',
            'imie'      => $s['imie']    ?? '',
            'konk'      => format_konkurencja($s['konkurencja'] ?? '', (int)($s['konkurencja_nr'] ?? 0)),
            'godz'      => $s['godz']    ?? '',
            'tor'       => $s['tor']     ?? '',
            'czas'      => $s['czas_result'] ?? ($s['czas'] ?? ''),
            'fetched'   => !empty($s['result_fetched']),
            'punkty'    => $s['punkty']  ?? '',
        ];
    }
}

// --- Generate PDF ---
class SwimPDF extends FPDF {
    public string $title = '';
    public string $subtitle = '';

    function Header() {
        $this->SetFont('Arial', 'B', 14);
        $this->SetTextColor(240, 168, 0);
        $this->Cell(0, 8, $this->title, 0, 1, 'C');
        $this->SetFont('Arial', '', 9);
        $this->SetTextColor(180, 180, 180);
        $this->Cell(0, 5, $this->subtitle, 0, 1, 'C');
        $this->SetDrawColor(240, 168, 0);
        $this->Line(10, $this->GetY() + 1, 200, $this->GetY() + 1);
        $this->Ln(4);
    }

    function Footer() {
        $this->SetY(-12);
        $this->SetFont('Arial', 'I', 7);
        $this->SetTextColor(120, 120, 120);
        $this->Cell(0, 5, 'Wygenerowano: ' . date('d.m.Y H:i') . '   Strona ' . $this->PageNo(), 0, 0, 'C');
    }
}

$pdf = new SwimPDF('P', 'mm', 'A4');
$pdf->SetAutoPageBreak(true, 15);
$pdf->SetMargins(10, 15, 10);
$pdf->title    = iconv('UTF-8', 'windows-1250', $zawody['nazwa'] ?? 'Wyniki');
$pdf->subtitle = iconv('UTF-8', 'windows-1250',
    implode('  ·  ', array_filter([$zawody['klub'] ?? '', $zawody['miejsce'] ?? '', $zawody['data'] ?? '']))
);
$pdf->AddPage();
$pdf->SetFillColor(30, 30, 30);

// Table header
$pdf->SetFont('Arial', 'B', 8);
$pdf->SetTextColor(240, 168, 0);
$pdf->SetFillColor(36, 36, 36);
$pdf->SetDrawColor(50, 50, 50);

$cols = [
    ['w' => 8,  'label' => 'Blok', 'align' => 'C'],
    ['w' => 14, 'label' => 'Data',  'align' => 'C'],
    ['w' => 42, 'label' => 'Zawodnik', 'align' => 'L'],
    ['w' => 55, 'label' => 'Konkurencja', 'align' => 'L'],
    ['w' => 14, 'label' => 'Godz.', 'align' => 'C'],
    ['w' => 8,  'label' => 'Tor',  'align' => 'C'],
    ['w' => 22, 'label' => 'Wynik', 'align' => 'C'],
    ['w' => 17, 'label' => 'Pkt', 'align' => 'C'],
];

foreach ($cols as $c) {
    $pdf->Cell($c['w'], 6, $c['label'], 1, 0, $c['align'], true);
}
$pdf->Ln();

// Rows
$pdf->SetFont('Arial', '', 8);
$fill = false;
foreach ($rows as $r) {
    $pdf->SetFillColor($fill ? 28 : 22, $fill ? 28 : 22, $fill ? 28 : 22);
    $pdf->SetTextColor(242, 242, 242);

    $imie_raw = $r['imie'];
    $imie_enc = iconv('UTF-8', 'windows-1250//TRANSLIT', $imie_raw);
    $konk_enc = iconv('UTF-8', 'windows-1250//TRANSLIT', $r['konk']);

    if ($r['fetched']) {
        $pdf->SetTextColor(76, 175, 80);
    } else {
        $pdf->SetTextColor(180, 180, 180);
    }

    $values = [
        $r['blok'],
        $r['data'],
        $imie_enc,
        $konk_enc,
        $r['godz'],
        (string)$r['tor'],
        $r['fetched'] ? $r['czas'] : '—',
        $r['fetched'] ? (string)$r['punkty'] : '—',
    ];

    foreach ($cols as $i => $c) {
        $pdf->Cell($c['w'], 6, $values[$i], 1, 0, $c['align'], true);
    }
    $pdf->Ln();
    $fill = !$fill;
}

$pdf->Output('I', 'wyniki_' . $json_file . '.pdf');
