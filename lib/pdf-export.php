<?php

declare(strict_types=1);

/**
 * lib/pdf-export.php — PDF itinerary generation using TCPDF.
 *
 * Requires: composer require tecnickcom/tcpdf
 * Font: cid0ct (Traditional Chinese CID font, built into TCPDF)
 */

require_once __DIR__ . '/../vendor/autoload.php';

/**
 * Resolve a photo path to an accessible file path.
 * For URLs: downloads to a temp file (caller must unlink $tmpFile).
 * For local paths: resolves from lib/../uploads/...
 */
function resolvePhotoPath(string $imagePath, ?string &$tmpFile = null): ?string
{
    $tmpFile = null;
    if (filter_var($imagePath, FILTER_VALIDATE_URL)) {
        $imgData = @file_get_contents($imagePath, false, stream_context_create([
            'http' => ['timeout' => 10, 'ignore_errors' => true],
        ]));
        if ($imgData === false) return null;
        $tmpFile = tempnam(sys_get_temp_dir(), 'photo_');
        file_put_contents($tmpFile, $imgData);
        return $tmpFile;
    }
    $localPath = __DIR__ . '/../' . ltrim($imagePath, '/');
    return is_file($localPath) ? $localPath : null;
}

/**
 * Generate an itinerary PDF for a trip using TCPDF.
 *
 * @return string Raw PDF bytes
 */
function generate_trip_pdf(
    array $trip,
    array $spots,
    ?array $weatherNow = null,
    ?array $weatherForecast = null,
    array $photos = [],
    array $spotPhotos = []
): string {
    $pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8');
    $pdf->SetCreator('Travel Platform');
    $pdf->SetTitle($trip['title'] ?? '行程手冊');
    $pdf->SetMargins(15, 20, 15);
    $pdf->SetAutoPageBreak(true, 20);
    $pdf->setPrintHeader(false);
    $pdf->setPrintFooter(false);
    $pdf->AddPage();

    // ── CJK font (Traditional Chinese CID, built-in, no external file needed) ──
    $pdf->SetFont('cid0ct', '', 14);
    $fontSize = 12;
    $fontSmall = 9;
    $lineH = 6;

    $pdf->SetFont('cid0ct', 'B', 18);
    $pdf->Cell(0, 10, '🗺 ' . ($trip['title'] ?? '行程'), 0, 1, 'L');

    $pdf->SetFont('cid0ct', '', $fontSize);
    $pdf->Cell(0, $lineH, '規劃師：' . ($trip['author_name'] ?? $trip['author_email'] ?? ''), 0, 1);

    $dateStr = '';
    if (!empty($trip['start_date'])) $dateStr = date('Y/m/d', strtotime($trip['start_date']));
    if (!empty($trip['end_date'])) $dateStr .= ' → ' . date('Y/m/d', strtotime($trip['end_date']));
    if ($dateStr !== '') $pdf->Cell(0, $lineH, '日期：' . $dateStr, 0, 1);

    $pdf->Ln(3);

    // ── Cover image ──
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
            $pdf->Image($coverPath, 15, $pdf->GetY(), 180, 60, '', '', '', true, 300);
        }
        if ($tmpFile !== null) @unlink($tmpFile);
        $pdf->Ln(65);
    }

    // ── Map image ──
    if (!empty($trip['latitude']) && !empty($trip['longitude'])) {
        $mapUrl = sprintf(
            'https://staticmap.openstreetmap.de/staticmap.php?center=%s,%s&zoom=12&size=600x300&maptype=mapnik&markers=%s,%s,red-pushpin',
            $trip['latitude'], $trip['longitude'],
            $trip['latitude'], $trip['longitude']
        );
        $mapData = @file_get_contents($mapUrl, false, stream_context_create([
            'http' => ['timeout' => 10, 'ignore_errors' => true],
        ]));
        if ($mapData !== false) {
            $tmpFile = tempnam(sys_get_temp_dir(), 'trip_map_') . '.png';
            file_put_contents($tmpFile, $mapData);
            $pdf->Image($tmpFile, 15, $pdf->GetY(), 180, 40, 'PNG');
            @unlink($tmpFile);
            $pdf->Ln(43);
        }
    }

    // ── Weather ──
    if ($weatherNow !== null) {
        $pdf->SetFont('cid0ct', 'B', $fontSize);
        $pdf->Cell(0, $lineH + 1, '☀ 天氣資訊', 0, 1);
        $pdf->SetFont('cid0ct', '', $fontSize);
        $desc = $weatherNow['description'] ?? '';
        $desc = preg_replace('/[^\x{0000}-\x{FFFF}]/u', '', $desc);
        $pdf->Cell(0, $lineH, sprintf('目前：%s，%.1f°C', $desc, $weatherNow['temp'] ?? 0), 0, 1);

        if ($weatherForecast !== null) {
            foreach ($weatherForecast as $day) {
                $d = $day['description'] ?? '';
                $d = preg_replace('/[^\x{0000}-\x{FFFF}]/u', '', $d);
                $pdf->Cell(0, $lineH, sprintf(
                    '  %s：%.0f° / %.0f° — %s',
                    $day['date'] ?? '', $day['temp_high'] ?? 0, $day['temp_low'] ?? 0, $d
                ), 0, 1);
            }
        }
        $pdf->Ln(3);
    }

    // ── Budget ──
    $budgetAmount = $trip['budget'] ?? null;
    if ($budgetAmount !== null && (float)$budgetAmount > 0) {
        $pdf->SetFont('cid0ct', 'B', $fontSize);
        $pdf->Cell(0, $lineH + 1, '💰 行程預算', 0, 1);
        $pdf->SetFont('cid0ct', '', $fontSize);
        require_once __DIR__ . '/currency.php';
        $tripCurrency = $trip['currency'] ?? 'TWD';
        $destCurrency = guess_destination_currency($trip['address'] ?? null);
        $budgetStr = format_currency((float)$budgetAmount, $tripCurrency);
        if ($destCurrency !== null && $destCurrency !== $tripCurrency) {
            $converted = convert_currency((float)$budgetAmount, $tripCurrency, $destCurrency);
            if ($converted !== null) $budgetStr .= ' ≈ ' . format_currency($converted, $destCurrency);
        }
        $pdf->Cell(0, $lineH, $budgetStr, 0, 1);
        $pdf->Ln(3);
    }

    // ── Spots ──
    if (count($spots) > 0) {
        $pdf->SetFont('cid0ct', 'B', $fontSize);
        $pdf->Cell(0, $lineH + 1, '📍 景點清單', 0, 1);
        $pdf->SetFont('cid0ct', '', $fontSize);

        foreach ($spots as $idx => $spot) {
            $num = $idx + 1;
            $pdf->SetFont('cid0ct', 'B', $fontSize);
            $pdf->Cell(0, $lineH + 1, "{$num}. " . ($spot['name'] ?? '未命名景點'), 0, 1);
            $pdf->SetFont('cid0ct', '', $fontSize);

            if (!empty($spot['address'])) {
                $pdf->Cell(0, $lineH, '   地址：' . $spot['address'], 0, 1);
            }
            if (!empty($spot['notes'])) {
                $pdf->MultiCell(0, $lineH, '   備註：' . $spot['notes'], 0, 'L');
            }

            // ── Spot photo thumbnail ──
            $spotId = (int)($spot['id'] ?? 0);
            if ($spotId > 0 && isset($spotPhotos[$spotId]) && count($spotPhotos[$spotId]) > 0) {
                $photo = $spotPhotos[$spotId][0];
                $ptmp = null;
                $ppath = resolvePhotoPath($photo['image_path'], $ptmp);
                if ($ppath !== null) {
                    $pdf->Image($ppath, 25, $pdf->GetY(), 40, 30);
                    $pdf->Ln(33);
                }
                if ($ptmp !== null) @unlink($ptmp);
            }

            // Page break check
            if ($pdf->GetY() > 250) $pdf->AddPage();
        }
    } else {
        $pdf->Cell(0, $lineH, '這個行程尚未新增景點。', 0, 1);
    }

    // ── Photo Wall (3×3 grid) ──
    if (count($photos) > 0) {
        if ($pdf->GetY() > 210) $pdf->AddPage();
        $pdf->SetFont('cid0ct', 'B', $fontSize);
        $pdf->Cell(0, $lineH + 1, '📸 照片牆', 0, 1);
        $pdf->Ln(2);

        $thumbW = 55;
        $thumbH = 40;
        $gap = 5;
        $cols = 3;
        $maxPhotos = min(count($photos), 9);
        $startX = 15;
        $startY = $pdf->GetY();

        for ($i = 0; $i < $maxPhotos; $i++) {
            $col = $i % $cols;
            $row = intdiv($i, $cols);
            $x = $startX + $col * ($thumbW + $gap);
            $y = $startY + $row * ($thumbH + $gap);

            $ptmp = null;
            $ppath = resolvePhotoPath($photos[$i]['image_path'], $ptmp);
            if ($ppath !== null) {
                $pdf->Image($ppath, $x, $y, $thumbW, $thumbH);
            }
            if ($ptmp !== null) @unlink($ptmp);
        }

        $rowsUsed = (int)ceil($maxPhotos / $cols);
        $pdf->SetY($startY + $rowsUsed * ($thumbH + $gap) + 3);
    }

    // ── Footer ──
    $pdf->Ln(5);
    $pdf->SetFont('cid0ct', '', $fontSmall);
    $pdf->Cell(0, $lineH, '— 由 Travel Platform 自動產生 —', 0, 1, 'C');

    return $pdf->Output('', 'S');
}
