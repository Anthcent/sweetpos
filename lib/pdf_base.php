<?php
// lib/pdf_base.php — FPDF base class with the shop branding (header, footer,
// watermark and simple table helpers). Text given to the u*() helpers is UTF-8;
// it is converted to Windows-1252, the encoding of FPDF's core fonts.

require_once __DIR__ . '/fpdf/fpdf.php';

// Loads config/business.php over safe defaults, so a missing or broken config
// never prevents a PDF from being generated.
function pdfBusinessConfig(): array {
    $defaults = [
        'name'      => 'Sweet POS',
        'rif'       => '',
        'address'   => '',
        'phone'     => '',
        'logo_path' => 'assets/ice_cream_branding.png',
        'timezone'  => 'America/Caracas',
        'currency'  => '$',
    ];
    $file = __DIR__ . '/../config/business.php';
    $config = is_file($file) ? (include $file) : [];
    return array_merge($defaults, is_array($config) ? $config : []);
}

// UTF-8 -> Windows-1252 (a superset of ISO-8859-1). Characters without an
// equivalent are transliterated or dropped instead of breaking the document.
function pdfText($text): string {
    $text = (string)$text;
    if ($text === '' || !preg_match('/[\x80-\xFF]/', $text)) return $text;
    if (function_exists('iconv')) {
        $converted = @iconv('UTF-8', 'Windows-1252//TRANSLIT//IGNORE', $text);
        if ($converted !== false) return $converted;
    }
    if (function_exists('mb_convert_encoding')) {
        return mb_convert_encoding($text, 'Windows-1252', 'UTF-8');
    }
    return preg_replace('/[\x80-\xFF]/', '?', $text);
}

function pdfTimezone(): DateTimeZone {
    try {
        return new DateTimeZone(pdfBusinessConfig()['timezone']);
    } catch (Throwable $e) {
        return new DateTimeZone('America/Caracas');
    }
}

// Formats a UTC timestamp stored by SQLite (CURRENT_TIMESTAMP) in the shop timezone.
function pdfLocalTime(?string $utc, string $format = 'd/m/Y h:i A'): string {
    if ($utc === null || $utc === '') return '';
    try {
        $date = new DateTime($utc, new DateTimeZone('UTC'));
        return $date->setTimezone(pdfTimezone())->format($format);
    } catch (Throwable $e) {
        return $utc;
    }
}

function pdfMoney($amount): string {
    return pdfBusinessConfig()['currency'] . number_format((float)$amount, 2, '.', ',');
}

// Returns [path, type] of a logo image FPDF can embed, or null. The branding file
// may be a JPEG with a .png name or a PNG with alpha/interlacing, which FPDF
// rejects, so when GD is available it is re-encoded once into a small JPEG.
function pdfResolveLogo(string $relativePath): ?array {
    $path = $relativePath === '' ? '' : (preg_match('/^([a-zA-Z]:)?[\\\\\/]/', $relativePath) ? $relativePath : __DIR__ . '/../' . $relativePath);
    if ($path === '' || !is_file($path) || !is_readable($path)) return null;
    $info = @getimagesize($path);
    if (!$info) return null;

    if (function_exists('imagecreatefromstring') && function_exists('imagejpeg')) {
        $cache = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'sweetpos_logo_' . md5(realpath($path) . '|' . filemtime($path)) . '.jpg';
        if (is_file($cache) && @getimagesize($cache)) return [$cache, 'JPG'];
        $source = @imagecreatefromstring((string)file_get_contents($path));
        if ($source) {
            $size = 240;
            $scale = min(1, $size / max($info[0], $info[1]));
            $w = max(1, (int)round($info[0] * $scale));
            $h = max(1, (int)round($info[1] * $scale));
            $canvas = imagecreatetruecolor($w, $h);
            imagefill($canvas, 0, 0, imagecolorallocate($canvas, 255, 255, 255)); // flatten alpha on white
            imagecopyresampled($canvas, $source, 0, 0, 0, 0, $w, $h, $info[0], $info[1]);
            $tmp = $cache . '.' . getmypid() . '.tmp';
            $ok = @imagejpeg($canvas, $tmp, 85) && @rename($tmp, $cache);
            imagedestroy($canvas);
            imagedestroy($source);
            if ($ok) return [$cache, 'JPG'];
            @unlink($tmp);
        }
    }

    // No GD: only a baseline JPEG or a plain PNG is safe to hand to FPDF directly.
    if ($info[2] === IMAGETYPE_JPEG) return [$path, 'JPG'];
    if ($info[2] === IMAGETYPE_PNG) return [$path, 'PNG'];
    return null;
}

class SweetPdf extends FPDF {
    protected $business;
    protected $docTitle;
    protected $docSubtitle;
    protected $watermark = '';
    protected $logo;
    protected $generatedAt;
    protected $angle = 0;
    protected $tableColumns = [];

    // Brand colors (pink accent of the UI).
    const ACCENT = [219, 39, 119];
    const MUTED  = [100, 116, 139];

    public function __construct(string $title, string $subtitle = '', string $orientation = 'P', array $options = []) {
        parent::__construct($orientation, 'mm', 'Letter');
        $this->business = pdfBusinessConfig();
        $this->docTitle = $title;
        $this->docSubtitle = $subtitle;
        $this->logo = pdfResolveLogo((string)$this->business['logo_path']);
        $this->generatedAt = (new DateTime('now', pdfTimezone()))->format('d/m/Y h:i A');
        if (array_key_exists('compress', $options)) $this->SetCompression((bool)$options['compress']);
        $this->SetMargins(15, 15, 15);
        $this->SetAutoPageBreak(true, 20);
        $this->AliasNbPages('{nb}');
        $this->SetTitle(pdfText($title . ($subtitle !== '' ? ' ' . $subtitle : '')));
        $this->SetAuthor(pdfText($this->business['name']));
        $this->SetCreator('Sweet POS');
    }

    public function setWatermark(string $text): void {
        $this->watermark = $text;
    }

    function Header() {
        if ($this->watermark !== '') $this->drawWatermark();

        $top = 12;
        $textX = $this->lMargin;
        if ($this->logo) {
            try {
                $this->Image($this->logo[0], $this->lMargin, $top, 18, 18, $this->logo[1]);
                $textX = $this->lMargin + 22;
            } catch (Throwable $e) {
                $this->logo = null; // unreadable logo: keep going without it
            }
        }

        $rightWidth = 70;
        $leftWidth = $this->w - $this->rMargin - $textX - $rightWidth;
        $this->SetXY($textX, $top);
        $this->SetTextColor(15, 23, 42);
        $this->SetFont('Helvetica', 'B', 13);
        $this->ucell($leftWidth, 6, $this->business['name'], 0, 1);
        $this->SetFont('Helvetica', '', 8);
        $this->SetTextColor(...self::MUTED);
        $lines = array_filter([
            $this->business['rif'] !== '' ? 'RIF: ' . $this->business['rif'] : '',
            $this->business['address'],
            $this->business['phone'] !== '' ? 'Tel: ' . $this->business['phone'] : '',
        ]);
        foreach ($lines as $line) {
            $this->SetX($textX);
            $this->ucell($leftWidth, 4, $line, 0, 1);
        }

        $this->SetXY($this->w - $this->rMargin - $rightWidth, $top);
        $this->SetFont('Helvetica', 'B', 12);
        $this->SetTextColor(...self::ACCENT);
        $this->ucell($rightWidth, 6, $this->docTitle, 0, 2, 'R');
        if ($this->docSubtitle !== '') {
            $this->SetFont('Helvetica', 'B', 10);
            $this->SetTextColor(15, 23, 42);
            $this->ucell($rightWidth, 5, $this->docSubtitle, 0, 2, 'R');
        }

        $this->SetDrawColor(...self::ACCENT);
        $this->SetLineWidth(0.6);
        $this->Line($this->lMargin, $top + 21, $this->w - $this->rMargin, $top + 21);
        $this->SetLineWidth(0.2);
        $this->SetDrawColor(226, 232, 240);
        $this->SetTextColor(15, 23, 42);
        $this->SetY($top + 25);

        // Tables split across pages repeat their header row.
        if ($this->tableColumns) $this->drawTableHeader();
    }

    function Footer() {
        $this->SetY(-15);
        $this->SetDrawColor(226, 232, 240);
        $this->Line($this->lMargin, $this->GetY(), $this->w - $this->rMargin, $this->GetY());
        $this->Ln(1.5);
        $this->SetFont('Helvetica', '', 7.5);
        $this->SetTextColor(...self::MUTED);
        $third = ($this->w - $this->lMargin - $this->rMargin) / 3;
        $this->ucell($third, 5, 'Documento no fiscal');
        $this->ucell($third, 5, 'Generado: ' . $this->generatedAt . ' (hora de Caracas)', 0, 0, 'C');
        $this->ucell($third, 5, 'Página ' . $this->PageNo() . '/{nb}', 0, 0, 'R');
    }

    protected function drawWatermark(): void {
        $this->SetFont('Helvetica', 'B', 96);
        $this->SetTextColor(254, 205, 211);
        $text = pdfText($this->watermark);
        $cx = $this->w / 2;
        $cy = $this->h / 2;
        $this->Rotate(45, $cx, $cy);
        $this->Text($cx - $this->GetStringWidth($text) / 2, $cy + 12, $text);
        $this->Rotate(0);
        $this->SetTextColor(15, 23, 42);
    }

    // Rotation around (x, y); from the official FPDF "Rotations" script.
    public function Rotate(float $angle, float $x = -1, float $y = -1): void {
        if ($x == -1) $x = $this->x;
        if ($y == -1) $y = $this->y;
        if ($this->angle != 0) $this->_out('Q');
        $this->angle = $angle;
        if ($angle != 0) {
            $angle *= M_PI / 180;
            $c = cos($angle);
            $s = sin($angle);
            $cx = $x * $this->k;
            $cy = ($this->h - $y) * $this->k;
            $this->_out(sprintf('q %.5F %.5F %.5F %.5F %.2F %.2F cm 1 0 0 1 %.2F %.2F cm',
                $c, $s, -$s, $c, $cx, $cy, -$cx, -$cy));
        }
    }

    function _endpage() {
        if ($this->angle != 0) {
            $this->angle = 0;
            $this->_out('Q');
        }
        parent::_endpage();
    }

    // Cell with UTF-8 input, truncated with "..." so it never overflows its width.
    public function ucell(float $w, float $h, $text, $border = 0, int $ln = 0, string $align = 'L', bool $fill = false): void {
        $text = pdfText($text);
        if ($w > 0) {
            $max = $w - 2 * $this->cMargin;
            if ($this->GetStringWidth($text) > $max) {
                while ($text !== '' && $this->GetStringWidth($text . '...') > $max) {
                    $text = substr($text, 0, -1);
                }
                $text .= '...';
            }
        }
        $this->Cell($w, $h, $text, $border, $ln, $align, $fill);
    }

    public function umulti(float $w, float $h, $text, $border = 0, string $align = 'L', bool $fill = false): void {
        $this->MultiCell($w, $h, pdfText($text), $border, $align, $fill);
    }

    public function sectionTitle(string $title): void {
        $this->ensureSpace(14);
        $this->Ln(3);
        $this->SetFont('Helvetica', 'B', 10);
        $this->SetTextColor(...self::ACCENT);
        $this->ucell(0, 6, $title, 0, 1);
        $this->SetTextColor(15, 23, 42);
    }

    // Label/value pair on one line.
    public function field(string $label, $value, float $labelWidth = 32): void {
        $this->SetFont('Helvetica', 'B', 9);
        $this->SetTextColor(...self::MUTED);
        $this->ucell($labelWidth, 5.5, $label);
        $this->SetFont('Helvetica', '', 9);
        $this->SetTextColor(15, 23, 42);
        $this->ucell(0, 5.5, $value === null || $value === '' ? '—' : (string)$value, 0, 1);
    }

    public function ensureSpace(float $height): void {
        if ($this->GetY() + $height > $this->PageBreakTrigger) $this->AddPage($this->CurOrientation);
    }

    // $columns: [[label, width, align], ...]; a width of 0 takes the remaining space.
    public function beginTable(array $columns): void {
        $available = $this->w - $this->lMargin - $this->rMargin;
        $fixed = array_sum(array_map(fn($c) => $c[1], $columns));
        $flex = count(array_filter($columns, fn($c) => $c[1] == 0));
        foreach ($columns as &$c) {
            if ($c[1] == 0) $c[1] = $flex ? max(10, ($available - $fixed) / $flex) : 10;
            $c[2] = $c[2] ?? 'L';
        }
        unset($c);
        $this->ensureSpace(14);
        $this->tableColumns = $columns;
        $this->drawTableHeader();
    }

    protected function drawTableHeader(): void {
        $this->SetFont('Helvetica', 'B', 8.5);
        $this->SetFillColor(252, 231, 243);
        $this->SetTextColor(131, 24, 67);
        foreach ($this->tableColumns as $c) $this->ucell($c[1], 7, $c[0], 0, 0, $c[2], true);
        $this->Ln();
        $this->SetTextColor(15, 23, 42);
    }

    public function tableRow(array $values, bool $bold = false, bool $shade = false): void {
        $h = 6;
        if ($this->GetY() + $h > $this->PageBreakTrigger) $this->AddPage($this->CurOrientation); // Header() repeats the column titles
        $this->SetFont('Helvetica', $bold ? 'B' : '', 8.5);
        $this->SetFillColor(248, 250, 252);
        foreach ($this->tableColumns as $i => $c) {
            $this->ucell($c[1], $h, $values[$i] ?? '', 'B', 0, $c[2], $shade);
        }
        $this->Ln();
    }

    public function endTable(): void {
        $this->tableColumns = [];
        $this->Ln(2);
    }

    public function emptyRow(string $message): void {
        $this->SetFont('Helvetica', 'I', 8.5);
        $this->SetTextColor(...self::MUTED);
        $this->ucell(0, 7, $message, 'B', 1, 'C');
        $this->SetTextColor(15, 23, 42);
    }
}
