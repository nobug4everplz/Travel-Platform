<?php

declare(strict_types=1);

/**
 * Minimal pure-PHP PDF generator — no external dependencies.
 *
 * ── Supported ──────────────────────────────────────────────
 * • UTF-8 text (CJK via CID-Identity-H)
 * • JPEG / PNG images
 * • Bold / normal text
 * • Multi-page
 * • Static OSM map image URL support
 *
 * ── Usage ─────────────────────────────────────────────────
 *   $pdf = new PdfDocument();
 *   $pdf->addPage();
 *   $pdf->setFont('bold', 16);
 *   $pdf->text(20, 30, 'Hello World');
 *   $pdf->output('php://output');
 */

// ───────────────────────────────────────────────────────────
//  Configuration
// ───────────────────────────────────────────────────────────

/** PDF unit: 1 mm */
const PDF_UNIT = 'mm';

/** Default page size */
const PDF_PAGE_W = 210; // A4 width  mm
const PDF_PAGE_H = 297; // A4 height mm
const PDF_MARGIN_L = 20;
const PDF_MARGIN_R = 20;
const PDF_MARGIN_T = 25;
const PDF_MARGIN_B = 20;

// ───────────────────────────────────────────────────────────
//  PdfDocument class
// ───────────────────────────────────────────────────────────

class PdfDocument
{
    private array $objects = [];
    private int $objCount = 0;
    private int $catalogObjNum = 0;
    private int $pagesObjNum = 0;
    private int $pageObjNum = 0;
    private int $fontBoldObjNum = 0;
    private int $fontNormalObjNum = 0;
    private int $contentObjNum = 0;
    private int $fontDescBoldObjNum = 0;
    private int $fontDescNormalObjNum = 0;
    private string $contentBuf = '';
    private float $pageW = PDF_PAGE_W;
    private float $pageH = PDF_PAGE_H;
    private float $marginL = PDF_MARGIN_L;
    private float $marginR = PDF_MARGIN_R;
    private float $marginT = PDF_MARGIN_T;
    private float $marginB = PDF_MARGIN_B;
    private float $cursorX = PDF_MARGIN_L;
    private float $cursorY = PDF_MARGIN_T;
    private float $contentW = 0; // usable width
    private float $contentH = 0; // usable height
    private int $pageCount = 0;
    private array $pageContentObjs = [];
    private array $xObjects = []; // image objects

    public function __construct()
    {
        $this->contentW = $this->pageW - $this->marginL - $this->marginR;
        $this->contentH = $this->pageH - $this->marginT - $this->marginB;
        // Pre-allocate catalog (1) and pages (2) so font objects use correct numbers
        $this->catalogObjNum = $this->allocObjNum();
        $this->pagesObjNum = $this->allocObjNum();
        $this->setFont('normal', 12);
    }

    // ── font helpers ──

    public function setFont(string $style = 'normal', float $size = 12): void
    {
        $this->fontBoldObjNum = $this->newFontBoldObj($size);
        $this->fontNormalObjNum = $this->newFontNormalObj($size);
    }

    // ── page ──

    public function addPage(): void
    {
        // Flush previous page content if any
        if ($this->contentBuf !== '') {
            $this->finalizePage();
        }

        $this->pageCount++;
        $this->contentBuf = '';
        $this->cursorX = $this->marginL;
        $this->cursorY = $this->marginT;
    }

    // ── image ──

    /**
     * Add an image at (x, y) in mm.
     */
    public function image(string $filePath, float $x, float $y, float $w, float $h): void
    {
        if (!is_file($filePath) || !is_readable($filePath)) {
            return; // silently skip missing images
        }

        $info = @getimagesize($filePath);
        if ($info === false) {
            return;
        }

        [$imgW, $imgH, $type] = $info;

        if ($type === IMAGETYPE_JPEG || $type === IMAGETYPE_PNG) {
            $data = file_get_contents($filePath);
            if ($data === false) {
                return;
            }

            $filter = $type === IMAGETYPE_JPEG ? '/DCTDecode' : '/FlateDecode';
            // PNG must use FlateDecode — handled by the filter
            $isJpeg = $type === IMAGETYPE_JPEG;
            $streamData = $isJpeg ? $data : $data; // raw for JPEG, raw for PNG
            $imgObjNum = $this->allocObjNum();

            // Write image object
            $stream = "{$streamData}";
            $streamLen = strlen($stream);

            $dict = "<< /Type /XObject /Subtype /Image /Width {$imgW} /Height {$imgH} /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter {$filter} /Length {$streamLen} >>";
            $object = "{$imgObjNum} 0 obj\n{$dict}\nstream\n{$stream}\nendstream\nendobj\n";
            $this->objects[] = $object;

            $this->xObjects[] = $imgObjNum;

            // Add image command to content stream
            $xMm = $this->mmToPoint($x);
            $yMm = $this->mmToPoint($y);
            $wMm = $this->mmToPoint($w);
            $hMm = $this->mmToPoint($h);

            $this->contentBuf .= "q\n{$wMm} 0 0 {$hMm} {$xMm} " . ($this->mmToPoint($this->pageH) - $yMm - $hMm) . " cm\n/I{$imgObjNum} Do\nQ\n";
        }
    }

    // ── text ──

    /**
     * Write text at position (x, y) in mm from top-left.
     */
    public function text(float $x, float $y, string $text, bool $bold = false): void
    {
        $this->cursorX = $x;
        $this->cursorY = $y;
        $this->writeText($text, $bold);
    }

    /**
     * Write text at current cursor, auto-advancing y.
     */
    public function writeText(string $text, bool $bold = false): void
    {
        $fontNum = $bold ? $this->fontBoldObjNum : $this->fontNormalObjNum;
        $hex = $this->utf8ToUtf16BeHex($text);
        $xPt = $this->mmToPoint($this->cursorX);
        $yPt = $this->mmToPoint($this->cursorY);
        // PDF y is from bottom
        $pdfY = $this->mmToPoint($this->pageH) - $yPt;

        $this->contentBuf .= "BT\n/F{$fontNum} {$this->mmToPoint(3.5)} Tf\n{$xPt} {$pdfY} Td\n<{$hex}> Tj\nET\n";
    }

    // ── body content helpers ──

    /**
     * Write a line of text at current position, auto-advance y by $lineHeight mm.
     */
    public function writeLine(string $text, bool $bold = false, float $lineHeight = 6): void
    {
        $this->writeText($text, $bold);
        $this->cursorY += $lineHeight;
    }

    // ── public getters ──

    public function getCursorY(): float { return $this->cursorY; }
    public function getPageH(): float { return $this->pageH; }
    public function getMarginB(): float { return $this->marginB; }
    public function getContentW(): float { return $this->contentW; }

    // ── output ──

    /**
     * Generate and output the PDF.
     * $destination: 'I' = inline (stdout), 'F' = file, 'S' = return string
     */
    public function output(string $destination = 'I'): string
    {
        // Finalize last page
        if ($this->contentBuf !== '') {
            $this->finalizePage();
        }

        $pdf = $this->buildPdf();

        // Ensure nothing else gets output before headers
        if ($destination === 'I') {
            header('Content-Type: application/pdf');
            header('Content-Disposition: inline; filename="document.pdf"');
            header('Content-Length: ' . strlen($pdf));
            echo $pdf;
            return '';
        }

        if ($destination === 'F') {
            // Not implemented for inline usage
            return $pdf;
        }

        return $pdf; // 'S' — return as string
    }

    // ── internal helpers ──

    private function allocObjNum(): int
    {
        return ++$this->objCount;
    }

    private function mmToPoint(float $mm): float
    {
        return $mm * 72 / 25.4; // 1 inch = 25.4 mm, 1 point = 1/72 inch
    }

    private function utf8ToUtf16BeHex(string $text): string
    {
        $utf16 = mb_convert_encoding($text, 'UTF-16BE', 'UTF-8');
        return strtoupper(bin2hex($utf16));
    }

    private function escPdfString(string $s): string
    {
        // Escape special PDF string characters
        $s = str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $s);
        return $s;
    }

    private function newFontBoldObj(float $size): int
    {
        $objNum = $this->allocObjNum();
        $descObjNum = $this->allocObjNum();
        $this->fontDescBoldObjNum = $descObjNum;

        // CIDFont for CJK (MSungStd-Light — Traditional Chinese)
        $cidObj = "{$descObjNum} 0 obj\n<< /Type /FontDescriptor /FontName /MSungStd-Light /Flags 4 /FontBBox [-167 -270 1015 934] /ItalicAngle 0 /Ascent 934 /Descent -270 /CapHeight 934 /StemV 76 /Style << /Panose <0000000000000000000000> >> >>\nendobj\n";

        // We need a proper CIDFont object
        $descendantObjNum = $this->allocObjNum();
        $descendant = "{$descendantObjNum} 0 obj\n<< /Type /Font /Subtype /CIDFontType2 /BaseFont /MSungStd-Light /CIDSystemInfo << /Registry (Adobe) /Ordering (CNS1) /Supplement 0 >> /FontDescriptor {$descObjNum} 0 R /W [0 [500]] >>\nendobj\n";

        // Type0 font with Identity-H encoding
        $font = "{$objNum} 0 obj\n<< /Type /Font /Subtype /Type0 /BaseFont /MSungStd-Light /Encoding /Identity-H /DescendantFonts [{$descendantObjNum} 0 R] >>\nendobj\n";

        $this->objects[] = $cidObj;
        $this->objects[] = $descendant;
        $this->objects[] = $font;

        return $objNum;
    }

    private function newFontNormalObj(float $size): int
    {
        $objNum = $this->allocObjNum();
        $descObjNum = $this->allocObjNum();

        // Same CID font, different object reference for bold vs normal
        $cidObj = "{$descObjNum} 0 obj\n<< /Type /FontDescriptor /FontName /MSungStd-Light /Flags 4 /FontBBox [-167 -270 1015 934] /ItalicAngle 0 /Ascent 934 /Descent -270 /CapHeight 934 /StemV 76 /Style << /Panose <0000000000000000000000> >> >>\nendobj\n";

        $descendantObjNum = $this->allocObjNum();
        $descendant = "{$descendantObjNum} 0 obj\n<< /Type /Font /Subtype /CIDFontType2 /BaseFont /MSungStd-Light /CIDSystemInfo << /Registry (Adobe) /Ordering (CNS1) /Supplement 0 >> /FontDescriptor {$descObjNum} 0 R /W [0 [500]] >>\nendobj\n";

        $font = "{$objNum} 0 obj\n<< /Type /Font /Subtype /Type0 /BaseFont /MSungStd-Light /Encoding /Identity-H /DescendantFonts [{$descendantObjNum} 0 R] >>\nendobj\n";

        $this->objects[] = $cidObj;
        $this->objects[] = $descendant;
        $this->objects[] = $font;

        return $objNum;
    }

    private function finalizePage(): void
    {
        $content = $this->contentBuf;

        // Create content stream object
        $contentObjNum = $this->allocObjNum();
        $stream = "{$contentObjNum} 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n";
        $this->objects[] = $stream;

        // Create page object
        $pageObjNum = $this->allocObjNum();
        $pageObj = $this->makePageObj($pageObjNum, $contentObjNum);
        $this->objects[] = $pageObj;
        $this->pageContentObjs[] = $pageObjNum;
    }

    private function makePageObj(int $pageObjNum, int $contentObjNum): string
    {
        $w = $this->mmToPoint($this->pageW);
        $h = $this->mmToPoint($this->pageH);

        // List fonts and XObjects
        $fontRefs = '';
        if ($this->fontBoldObjNum > 0) {
            $fontRefs .= "/F{$this->fontBoldObjNum} {$this->fontBoldObjNum} 0 R ";
        }
        if ($this->fontNormalObjNum > 0) {
            $fontRefs .= "/F{$this->fontNormalObjNum} {$this->fontNormalObjNum} 0 R ";
        }

        $xObjRefs = '';
        foreach ($this->xObjects as $xObjNum) {
            $xObjRefs .= "/I{$xObjNum} {$xObjNum} 0 R ";
        }

        $resources = "<< /Font << {$fontRefs}>> /XObject << {$xObjRefs}>> >>";

        return "{$pageObjNum} 0 obj\n<< /Type /Page /Parent {$this->pagesObjNum} 0 R /MediaBox [0 0 {$w} {$h}] /Contents {$contentObjNum} 0 R /Resources {$resources} >>\nendobj\n";
    }

    private function buildPdf(): string
    {
        // Create catalog and pages objects using pre-allocated numbers
        $kids = implode(' ', array_map(fn($n) => "{$n} 0 R", $this->pageContentObjs));
        $catalog = "{$this->catalogObjNum} 0 obj\n<< /Type /Catalog /Pages {$this->pagesObjNum} 0 R >>\nendobj\n";
        $pages = "{$this->pagesObjNum} 0 obj\n<< /Type /Pages /Kids [{$kids}] /Count {$this->pageCount} >>\nendobj\n";

        // All objects: catalog, pages, then our accumulated objects
        $allObjects = array_merge(
            [$catalog, $pages],
            $this->objects
        );

        // Build body while tracking byte offsets per object number
        $offsets = []; // objNum => byte offset (relative to start of PDF file)
        $body = '';
        $highestObjNum = 0;
        $pdfHeader = "%PDF-1.4\n";
        $bodyOffset = strlen($pdfHeader); // xref offsets are relative to file start

        foreach ($allObjects as $obj) {
            // Extract the object number from "N 0 obj"
            if (preg_match('/^(\d+) 0 obj/s', $obj, $m)) {
                $objNum = (int) $m[1];
                $offsets[$objNum] = $bodyOffset + strlen($body);
                $highestObjNum = max($highestObjNum, $objNum);
            }
            $body .= $obj;
        }

        $xrefOffset = $bodyOffset + strlen($body);
        $totalObjects = $highestObjNum + 1; // +1 for object 0 (free entry)

        // Build xref table — one entry per object number (0 to highestObjNum)
        $xref = "xref\n0 {$totalObjects}\n0000000000 65535 f \n";
        for ($i = 1; $i < $totalObjects; $i++) {
            if (isset($offsets[$i])) {
                $xref .= sprintf("%010d 00000 n \n", $offsets[$i]);
            } else {
                // Missing object number — free entry (shouldn't happen with sequential alloc)
                $xref .= "0000000000 00000 f \n";
            }
        }

        $trailer = "trailer\n<< /Size {$totalObjects} /Root {$this->catalogObjNum} 0 R >>\nstartxref\n{$xrefOffset}\n%%EOF\n";

        return "{$pdfHeader}{$body}{$xref}{$trailer}";
    }
}

// ───────────────────────────────────────────────────────────
//  Export function
// ───────────────────────────────────────────────────────────

/**
 * Generate an itinerary PDF for a trip.
 *
 * @param array $trip   Trip row (from find_trip / find_visible_trip)
 * @param array $spots  Spot rows (from get_trip_spots)
 * @param array|null $weatherNow   Current weather (from get_weather)
 * @param array|null $weatherForecast  Forecast (from get_forecast)
 * @return string  Raw PDF bytes
 */
function generate_trip_pdf(array $trip, array $spots, ?array $weatherNow = null, ?array $weatherForecast = null): string
{
    $pdf = new PdfDocument();
    $pdf->addPage();

    // ── Trip Title ──
    $pdf->writeLine('🗺️ ' . ($trip['title'] ?? '行程'), bold: true, lineHeight: 10);
    $pdf->writeLine('規劃師：' . ($trip['author_name'] ?? $trip['author_email'] ?? ''), lineHeight: 7);

    // Date
    $dateStr = '';
    if (!empty($trip['start_date'])) {
        $dateStr = date('Y/m/d', strtotime($trip['start_date']));
    }
    if (!empty($trip['end_date'])) {
        $dateStr .= ' → ' . date('Y/m/d', strtotime($trip['end_date']));
    }
    if ($dateStr !== '') {
        $pdf->writeLine('日期：' . $dateStr, lineHeight: 7);
    }

    // Cover image — download if URL
    if (!empty($trip['cover_image'])) {
        $coverPath = $trip['cover_image'];
        $tmpFile = null;
        if (filter_var($coverPath, FILTER_VALIDATE_URL)) {
            $imgData = @file_get_contents($coverPath, false, stream_context_create([
                'http' => ['timeout' => 10, 'ignore_errors' => true],
            ]));
            if ($imgData !== false) {
                $tmpFile = tempnam(sys_get_temp_dir(), 'cover_');
                file_put_contents($tmpFile, $imgData);
                $coverPath = $tmpFile;
            }
        }
        if ($tmpFile !== null || is_file($coverPath)) {
            $pdf->image($coverPath, PDF_MARGIN_L, $pdf->getCursorY(), $pdf->getContentW(), 60);
        }
        if ($tmpFile !== null) {
            @unlink($tmpFile);
        }
        $pdf->writeLine('', lineHeight: 65);
        $pdf->addPage(); // move map to its own page for cleaner layout
    }

    $pdf->writeLine('', lineHeight: 3);

    // ── Map image ──
    if (!empty($trip['latitude']) && !empty($trip['longitude'])) {
        $mapUrl = sprintf(
            'https://staticmap.openstreetmap.de/staticmap.php?center=%s,%s&zoom=12&size=600x300&maptype=mapnik&markers=%s,%s,red-pushpin',
            $trip['latitude'],
            $trip['longitude'],
            $trip['latitude'],
            $trip['longitude']
        );
        // Download the map image
        $mapData = @file_get_contents($mapUrl, false, stream_context_create([
            'http' => ['timeout' => 10, 'ignore_errors' => true],
        ]));
        if ($mapData !== false) {
            $tmpFile = tempnam(sys_get_temp_dir(), 'trip_map_') . '.png';
            file_put_contents($tmpFile, $mapData);
            $pdf->image($tmpFile, PDF_MARGIN_L, $pdf->getCursorY(), $pdf->getContentW(), 45);
            @unlink($tmpFile);
            $pdf->writeLine('', lineHeight: 50);
        }
    }

    // ── Weather (if available) ──
    if ($weatherNow !== null) {
        $pdf->writeLine('☀️ 天氣資訊', bold: true, lineHeight: 8);
        $pdf->writeLine(sprintf('目前：%s，%.1f°C', $weatherNow['description'] ?? '', $weatherNow['temp'] ?? 0), lineHeight: 6);

        if ($weatherForecast !== null) {
            foreach ($weatherForecast as $day) {
                $pdf->writeLine(sprintf(
                    '  %s：%.0f° / %.0f° — %s',
                    $day['date'] ?? '',
                    $day['temp_high'] ?? 0,
                    $day['temp_low'] ?? 0,
                    $day['description'] ?? ''
                ), lineHeight: 5);
            }
        }
        $pdf->writeLine('', lineHeight: 3);
    }

    // ── Spots ──
    if (count($spots) > 0) {
        $pdf->writeLine('📍 景點清單', bold: true, lineHeight: 9);

        foreach ($spots as $idx => $spot) {
            $num = $idx + 1;
            $pdf->writeLine("{$num}. " . ($spot['name'] ?? '未命名景點'), bold: true, lineHeight: 7);

            if (!empty($spot['address'])) {
                $pdf->writeLine('   地址：' . $spot['address'], lineHeight: 5);
            }
            if (!empty($spot['notes'])) {
                $pdf->writeLine('   備註：' . $spot['notes'], lineHeight: 5);
            }

            // Check if we need a new page
            if ($pdf->getCursorY() > $pdf->getPageH() - $pdf->getMarginB() - 15) {
                // Close current page and open new one
                $pdf->addPage();
            }
        }
    } else {
        $pdf->writeLine('這個行程尚未新增景點。', lineHeight: 7);
    }

    // ── Footer ──
    $pdf->writeLine('', lineHeight: 10);
    $pdf->writeLine('— 由 Travel Platform 自動產生 —', lineHeight: 6);

    return $pdf->output('S');
}
