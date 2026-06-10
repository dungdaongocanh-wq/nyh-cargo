<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
requireLogin();

if (!isManager()) {
    setFlash('danger', 'Bạn không có quyền truy cập chức năng này.');
    header('Location: ' . BASE_URL . 'operations/manifest/index.php');
    exit;
}

session_write_close();
set_time_limit(120);
ini_set('memory_limit', '512M');

require_once __DIR__ . '/../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

$db  = getDB();
$mid = (int)($_GET['id'] ?? 0);

function failExport(int $mid, string $msg): void {
    setFlash('danger', $msg);
    header('Location: ' . BASE_URL . 'operations/manifest/edit.php?id=' . $mid);
    exit;
}

if (!$mid) failExport(0, 'Thiếu ID manifest hợp lệ.');

// ── Load manifest ──────────────────────────────────────────────────────────────
$stmt = $db->prepare("
    SELECT m.*,
           al.code AS airline_code, al.name AS airline_name,
           ap1.iata_code AS origin_code, ap1.name AS origin_name,
           ap2.iata_code AS dest_code,   ap2.name AS dest_name,
           c.name    AS customer_name,
           c.address AS customer_address,
           c.phone   AS customer_phone,
           c.fax     AS customer_fax
    FROM manifests m
    LEFT JOIN airlines  al  ON m.airline_id     = al.id
    LEFT JOIN airports  ap1 ON m.origin_id      = ap1.id
    LEFT JOIN airports  ap2 ON m.destination_id = ap2.id
    LEFT JOIN customers c   ON m.customer_id    = c.id
    WHERE m.id = ?
");
$stmt->bind_param('i', $mid);
$stmt->execute();
$m = $stmt->get_result()->fetch_assoc();
$stmt->close();
if (!$m) failExport($mid, 'Không tìm thấy manifest.');

// ── Load HAWBs ──────────────────────────────────────────────────────────────────
$hawbs = $db->query("
    SELECT h.*,
           s.name    AS shipper_name,    s.address AS shipper_address,
           s.city    AS shipper_city,    s.phone   AS shipper_phone,
           cn.name   AS cnee_name,       cn.address AS cnee_address,
           cn.city   AS cnee_city,       cn.phone  AS cnee_phone,
           cn.usci_no AS cnee_usci,      cn.account_no AS cnee_acct,
           ap1.iata_code AS origin_code, ap1.name AS origin_fullname,
           ap2.iata_code AS dest_code,   ap2.name AS dest_fullname
    FROM hawbs h
    LEFT JOIN shippers   s   ON h.shipper_id    = s.id
    LEFT JOIN consignees cn  ON h.consignee_id  = cn.id
    LEFT JOIN airports   ap1 ON h.origin_id     = ap1.id
    LEFT JOIN airports   ap2 ON h.destination_id= ap2.id
    WHERE h.manifest_id = {$mid}
    ORDER BY h.seq_number ASC
")->fetch_all(MYSQLI_ASSOC);

if (empty($hawbs)) failExport($mid, 'Manifest này chưa có HAWB nào.');

// ── Load dim groups ────────────────────────────────────────────────────────────
$dimByHawb = [];
foreach ($hawbs as $hw) {
    $hid = (int)$hw['id'];
    $dimByHawb[$hid] = $db->query(
        "SELECT length, width, height, qty_pieces FROM hawb_dim_groups WHERE hawb_id={$hid} ORDER BY id"
    )->fetch_all(MYSQLI_ASSOC);
}

// ── Cell map ───────────────────────────────────────────────────────────────────
$cellMap = require __DIR__ . '/../config/hawb_excel_map.php';

// ── Template ───────────────────────────────────────────────────────────────────
$templateFile = __DIR__ . '/../assets/templates/hawb_template.xlsx';
if (!file_exists($templateFile)) failExport($mid, 'Không tìm thấy template HAWB Excel.');

// ── Helpers ────────────────────────────────────────────────────────────────────
function fmtNum($v): string {
    if ($v === null || $v === '') return '';
    $f = (float)$v;
    return ($f == floor($f)) ? (string)(int)$f : (string)round($f, 2);
}

function buildHawbCellData(array $h, array $m, array $dimGroups): array
{
    $dimParts = [];
    foreach ($dimGroups as $dg) {
        $l  = fmtNum($dg['length']); $w = fmtNum($dg['width']);
        $hv = fmtNum($dg['height']); $q  = (int)$dg['qty_pieces'];
        if ($l && $w && $hv && $q) $dimParts[] = "{$l}x{$w}x{$hv}/{$q}PCS";
    }
    $hp = [];
    if (!empty($h['notify_party']))  $hp[] = $h['notify_party'];
    if (!empty($h['handling_info'])) $hp[] = $h['handling_info'];

    return [
        'mawb_no'               => $m['mawb_no']          ?? '',
        'hawb_no'               => $h['hawb_no']           ?? '',
        'hawb_no_footer'        => $h['hawb_no']           ?? '',
        'shipper_name'          => strtoupper($h['shipper_name']    ?? ''),
        'shipper_address'       => strtoupper($h['shipper_address'] ?? ''),
        'shipper_city'          => strtoupper($h['shipper_city']    ?? ''),
        'shipper_phone'         => $h['shipper_phone']     ?? '',
        'consignee_name'        => strtoupper($h['cnee_name']    ?? ''),
        'consignee_address'     => strtoupper($h['cnee_address'] ?? ''),
        'consignee_city'        => strtoupper($h['cnee_city']    ?? ''),
        'consignee_phone'       => $h['cnee_phone']        ?? '',
        'consignee_acct'        => $h['cnee_acct']         ?? '',
        'consignee_usci'        => $h['cnee_usci']         ?? '',
        'notify_party'          => $h['notify_party']      ?? '',
        'issuing_carrier'       => strtoupper($m['airline_code'] ?? ''),
        'agent_name'            => defined('COMPANY_NAME') ? COMPANY_NAME : '',
        'agent_iata'            => defined('COMPANY_IATA') ? (string)COMPANY_IATA : '',
        'airport_departure'     => $m['origin_name']       ?? '',
        'airport_dest'          => $m['dest_code']         ?? '',
        'airport_dest_fullname' => $m['dest_name']         ?? '',
        'routing_by1'           => $m['airline_code']      ?? '',
        'flight_no'             => $m['flight_no']         ?? '',
        'flight_date'           => !empty($m['flight_date']) ? date('d-M-y', strtotime($m['flight_date'])) : '',
        'flight_date_footer'    => !empty($m['flight_date']) ? date('d-M-y', strtotime($m['flight_date'])) : '',
        'payment_term'          => $h['payment_term']      ?: 'PP',
        'currency'              => $h['currency']          ?? 'USD',
        'rate_class'            => $h['rate_class']        ?? 'Q',
        'commodity_item_no'     => $h['commodity_item_no'] ?? '',
        'declared_carriage'     => $h['declared_value_carriage'] ?: 'NVD',
        'declared_customs'      => $h['declared_value_customs']  ?: 'AS PER INV',
        'amount_insurance'      => $h['amount_insurance']        ?: 'XXX',
        'accounting_info'       => $h['accounting_info']         ?: 'FREIGHT PREPAID',
        'no_of_pieces'          => (string)(int)$h['no_of_pieces'],
        'no_of_pieces_footer'   => (string)(int)$h['no_of_pieces'],
        'gross_weight'          => (float)$h['gross_weight']      > 0 ? fmtNum($h['gross_weight'])      : '',
        'gross_weight_footer'   => (float)$h['gross_weight']      > 0 ? fmtNum($h['gross_weight'])      : '',
        'gross_weight_unit'     => 'K',
        'volume_weight'         => (float)$h['volume_weight']     > 0 ? fmtNum($h['volume_weight'])     : '',
        'chargeable_weight'     => (float)$h['chargeable_weight'] > 0 ? fmtNum($h['chargeable_weight']) : '',
        'commodity_line1'       => $h['commodity']         ?? '',
        'commodity_line2'       => '', 'commodity_line3'   => '',
        'commodity_line4'       => '', 'commodity_line5'   => '',
        'dim_info'              => implode('  ', $dimParts),
        'handling_info'         => implode("\n", $hp),
        'execution_place'       => $m['origin_code']       ?? '',
        'execution_date'        => date('d-M-Y'),
        'signature_origin'      => defined('COMPANY_NAME') ? COMPANY_NAME : '',
    ];
}

function fillHawbSheet(Worksheet $sheet, array $cellData, array $cellMap): void
{
    foreach ($cellMap as $field => $cell) {
        if (!isset($cellData[$field])) continue;
        $value = (string)$cellData[$field];
        if ($value === '') continue;
        $cells = is_array($cell) ? $cell : [$cell];
        foreach ($cells as $c) {
            $c = strtoupper(trim((string)$c));
            if ($c === '') continue;
            try {
                $sheet->setCellValue($c, is_numeric($value) ? (float)$value : $value);
            } catch (\Exception $e) { /* skip invalid ref */ }
        }
    }
}

function buildManifestSheet(Worksheet $sheet, array $m, array $hawbs): void
{
    $darkBlue = '1A3A6B';

    // ── Column widths ──────────────────────────────────────────────────────────
    foreach (['A' => 18, 'B' => 9, 'C' => 10, 'D' => 26, 'E' => 8, 'F' => 30, 'G' => 35, 'H' => 13] as $col => $w) {
        $sheet->getColumnDimension($col)->setWidth($w);
    }

    // ── ROW 1: Title ───────────────────────────────────────────────────────────
    $sheet->mergeCells('A1:H1');
    $sheet->setCellValue('A1', 'AIR CARGO MANIFEST');
    $sheet->getStyle('A1')->applyFromArray([
        'font'      => ['bold' => true, 'size' => 20, 'color' => ['rgb' => 'FFFFFF']],
        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
        'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $darkBlue]],
    ]);
    $sheet->getRowDimension(1)->setRowHeight(38);

    // ── INFO BLOCK Rows 2-9 ────────────────────────────────────────────────────
    $sheet->setCellValue('A2', 'MASTER AIR WAYBILL No :');
    $sheet->getStyle('A2')->getFont()->setBold(true)->setSize(8);
    $sheet->mergeCells('B2:D2');
    $sheet->setCellValue('B2', $m['mawb_no'] ?? '');
    $sheet->getStyle('B2')->getFont()->setBold(true)->setSize(12);

    $sheet->setCellValue('A3', 'FLIGHT NO :');
    $sheet->getStyle('A3')->getFont()->setBold(true)->setSize(8);
    $sheet->setCellValue('B3', $m['flight_no'] ?? '');
    $sheet->getStyle('B3')->getFont()->setBold(true)->setSize(10);
    $sheet->setCellValue('C3', !empty($m['flight_date']) ? date('d-M', strtotime($m['flight_date'])) : '');
    $sheet->getStyle('C3')->getFont()->setBold(true)->setSize(10);

    $sheet->setCellValue('A4', 'FROM :');
    $sheet->getStyle('A4')->getFont()->setBold(true)->setSize(8);
    $sheet->setCellValue('B4', $m['origin_code'] ?? '');
    $sheet->getStyle('B4')->getFont()->setBold(true)->setSize(11);
    $sheet->setCellValue('C4', 'TO :');
    $sheet->getStyle('C4')->getFont()->setBold(true)->setSize(8);
    $sheet->setCellValue('D4', $m['dest_code'] ?? '');
    $sheet->getStyle('D4')->getFont()->setBold(true)->setSize(11);

    $sheet->setCellValue('A5', 'CONSIGNEE :');
    $sheet->getStyle('A5')->getFont()->setBold(true)->setSize(8);

    $sheet->mergeCells('A6:D9');
    $cnee = strtoupper($m['customer_name'] ?? '— Not specified —');
    if (!empty($m['customer_address'])) $cnee .= "\n" . $m['customer_address'];
    if (!empty($m['customer_phone'])) {
        $tel  = 'T:' . $m['customer_phone'];
        if (!empty($m['customer_fax'])) $tel .= '  (DIR: ' . $m['customer_fax'] . ')';
        $cnee .= "\n" . $tel;
    }
    $sheet->setCellValue('A6', $cnee);
    $sheet->getStyle('A6')->applyFromArray([
        'font'      => ['bold' => true, 'size' => 9, 'color' => ['rgb' => $darkBlue]],
        'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT, 'vertical' => Alignment::VERTICAL_TOP, 'wrapText' => true],
    ]);
    for ($r = 6; $r <= 9; $r++) $sheet->getRowDimension($r)->setRowHeight(15);

    // Right: Company block (E2:H9 merged)
    $sheet->mergeCells('E2:H9');
    $co  = (defined('COMPANY_NAME')    ? COMPANY_NAME    : '');
    $co .= (defined('COMPANY_ADDRESS') ? "\nAddress: " . COMPANY_ADDRESS : '');
    $co .= (defined('COMPANY_TEL')     ? "\nTel: "     . COMPANY_TEL     : '');
    $co .= (defined('COMPANY_FAX')     ? "\nFax: "     . COMPANY_FAX     : '');
    $co .= (defined('COMPANY_TAX')     ? "\nMST: "     . COMPANY_TAX     : '');
    $sheet->setCellValue('E2', $co);
    $sheet->getStyle('E2')->applyFromArray([
        'font'      => ['size' => 9],
        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
    ]);

    // Borders on info block
    $thin = ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'AAAAAA']];
    $sheet->getStyle('A2:D9')->applyFromArray(['borders' => ['allBorders' => $thin]]);
    $sheet->getStyle('E2:H9')->applyFromArray(['borders' => ['allBorders' => $thin]]);

    // ── Row 10: spacer ─────────────────────────────────────────────────────────
    $sheet->getRowDimension(10)->setRowHeight(4);

    // ── Row 11: Table header ───────────────────────────────────────────────────
    $hRow = 11;
    $hdrs = ['A' => 'HAWB NO.', 'B' => 'NO.OF CTNS', 'C' => 'GW (KG)', 'D' => 'COMMODITY',
             'E' => 'DEST',     'F' => 'SHIPPER',     'G' => 'CONSIGNEE', 'H' => 'PAYMENT TERM'];
    foreach ($hdrs as $col => $label) $sheet->setCellValue($col . $hRow, $label);
    $sheet->getStyle("A{$hRow}:H{$hRow}")->applyFromArray([
        'font'      => ['bold' => true, 'size' => 8.5],
        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
        'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'F0F0F0']],
        'borders'   => [
            'allBorders' => ['borderStyle' => Border::BORDER_THIN,   'color' => ['rgb' => '555555']],
            'outline'    => ['borderStyle' => Border::BORDER_MEDIUM,  'color' => ['rgb' => '333333']],
        ],
    ]);
    $sheet->getRowDimension($hRow)->setRowHeight(28);

    // ── Data rows ─────────────────────────────────────────────────────────────
    $row = $hRow + 1;
    $totalPcs = 0; $totalGW = 0.0;

    foreach ($hawbs as $h) {
        $h = array_map(fn($v) => $v ?? '', $h);

        $sheet->setCellValue("A{$row}", $h['hawb_no']);
        $sheet->setCellValue("B{$row}", (int)$h['no_of_pieces']);
        if ((float)$h['gross_weight'] > 0) {
            $sheet->setCellValue("C{$row}", (float)$h['gross_weight']);
            $sheet->getStyle("C{$row}")->getNumberFormat()->setFormatCode('#,##0.0');
        }

        // COMMODITY: hiển thị toàn bộ các dòng (giống manifest_print)
        $commodityLines = array_filter(array_map('trim', explode("\n", $h['commodity'])));
        $sheet->setCellValue("D{$row}", implode("\n", $commodityLines));

        $sheet->setCellValue("E{$row}", $h['dest_code']);

        // SHIPPER: tên + địa chỉ + điện thoại (giống manifest_print)
        $shipperParts = [];
        if (!empty($h['shipper_name']))    $shipperParts[] = strtoupper($h['shipper_name']);
        if (!empty($h['shipper_address'])) $shipperParts[] = strtoupper($h['shipper_address']);
        if (!empty($h['shipper_phone']))   $shipperParts[] = 'T:' . $h['shipper_phone'];
        $sheet->setCellValue("F{$row}", implode("\n", $shipperParts));

        // CONSIGNEE: tên + địa chỉ + điện thoại (giống manifest_print)
        $cneeParts = [];
        if (!empty($h['cnee_name']))    $cneeParts[] = strtoupper($h['cnee_name']);
        if (!empty($h['cnee_address'])) $cneeParts[] = strtoupper($h['cnee_address']);
        if (!empty($h['cnee_phone']))   $cneeParts[] = 'TEL:' . $h['cnee_phone'];
        $sheet->setCellValue("G{$row}", implode("\n", $cneeParts));

        $sheet->setCellValue("H{$row}", $h['payment_term'] ?: 'PP');

        $sheet->getStyle("A{$row}:H{$row}")->applyFromArray([
            'font'      => ['size' => 8],
            'alignment' => ['vertical' => Alignment::VERTICAL_TOP, 'wrapText' => true],
            'borders'   => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => '888888']]],
        ]);
        foreach (['B', 'C', 'E', 'H'] as $c)
            $sheet->getStyle("{$c}{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle("A{$row}")->applyFromArray([
            'font'      => ['bold' => true, 'size' => 8.5],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);
        $termColor = ($h['payment_term'] === 'PP') ? $darkBlue : '856404';
        $sheet->getStyle("H{$row}")->getFont()->setBold(true)->getColor()->setRGB($termColor);
        $sheet->getRowDimension($row)->setRowHeight(-1); // auto height để vừa nội dung nhiều dòng

        $totalPcs += (int)$h['no_of_pieces'];
        $totalGW  += (float)$h['gross_weight'];
        $row++;
    }

    // ── Total row ─────────────────────────────────────────────────────────────
    $sheet->setCellValue("A{$row}", 'Total');
    $sheet->setCellValue("B{$row}", $totalPcs);
    if ($totalGW > 0) {
        $sheet->setCellValue("C{$row}", round($totalGW, 1));
        $sheet->getStyle("C{$row}")->getNumberFormat()->setFormatCode('#,##0.0');
    }
    $sheet->getStyle("A{$row}:H{$row}")->applyFromArray([
        'font'      => ['bold' => true, 'size' => 9],
        'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
        'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'F9F9F9']],
        'borders'   => [
            'allBorders' => ['borderStyle' => Border::BORDER_THIN,   'color' => ['rgb' => '888888']],
            'top'        => ['borderStyle' => Border::BORDER_MEDIUM,  'color' => ['rgb' => '333333']],
        ],
    ]);
    foreach (['B', 'C'] as $c)
        $sheet->getStyle("{$c}{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    $sheet->getRowDimension($row)->setRowHeight(22);

    // ── Footer ────────────────────────────────────────────────────────────────
    $fRow = $row + 2;
    $sheet->mergeCells("A{$fRow}:H{$fRow}");
    $footerParts = [];
    if (defined('COMPANY_NAME'))    $footerParts[] = COMPANY_NAME;
    if (defined('COMPANY_ADDRESS')) $footerParts[] = COMPANY_ADDRESS;
    if (defined('COMPANY_TEL'))     $footerParts[] = 'Tel: ' . COMPANY_TEL;
    $footerParts[] = 'Printed: ' . date('d-M-Y H:i');
    $sheet->setCellValue("A{$fRow}", implode(' · ', $footerParts));
    $sheet->getStyle("A{$fRow}")->applyFromArray([
        'font'      => ['size' => 7, 'color' => ['rgb' => '888888']],
        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        'borders'   => ['top' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'DDDDDD']]],
    ]);

    // ── Page setup (A4 landscape) ──────────────────────────────────────────────
    $sheet->getPageSetup()
          ->setOrientation(PageSetup::ORIENTATION_LANDSCAPE)
          ->setPaperSize(PageSetup::PAPERSIZE_A4)
          ->setFitToPage(true)
          ->setFitToWidth(1)
          ->setFitToHeight(0);
    $sheet->getPageMargins()->setTop(0.3)->setBottom(0.3)->setLeft(0.3)->setRight(0.3);
    $sheet->getSheetView()->setZoomScale(85);
    $sheet->freezePane('A' . ($hRow + 1));
}

// ── Build workbook ─────────────────────────────────────────────────────────────
$spreadsheet = IOFactory::load($templateFile);
$blankTpl    = $spreadsheet->getActiveSheet();
$blankTpl->setTitle('_tpl_');

// Insert MANIFEST sheet at index 0
$manifestSheet = new Worksheet($spreadsheet, 'MANIFEST');
$spreadsheet->addSheet($manifestSheet, 0);
buildManifestSheet($manifestSheet, $m, $hawbs);

// For each HAWB: clone blank template, add to workbook, fill data
foreach ($hawbs as $hw) {
    $clone = clone $blankTpl;
    $title = preg_replace('/[^A-Z0-9\-]/', '', strtoupper((string)($hw['hawb_no'] ?? '')));
    $title = $title ?: ('HAWB' . $hw['id']);
    $clone->setTitle(substr($title, 0, 31));
    $spreadsheet->addSheet($clone);
    fillHawbSheet(
        $clone,
        buildHawbCellData(
            array_map(fn($v) => $v ?? '', $hw),
            $m,
            $dimByHawb[(int)$hw['id']] ?? []
        ),
        $cellMap
    );
}

// Remove blank template sheet
foreach ($spreadsheet->getAllSheets() as $idx => $s) {
    if ($s->getTitle() === '_tpl_') { $spreadsheet->removeSheetByIndex($idx); break; }
}

$spreadsheet->setActiveSheetIndex(0);

// ── Stream to browser ──────────────────────────────────────────────────────────
$safeMawb   = preg_replace('/[^A-Z0-9\-]/', '', strtoupper((string)($m['mawb_no'] ?? 'MANIFEST')));
$dlFilename = $safeMawb . '_FULL.xlsx';
$outputDir  = __DIR__ . '/../assets/outputs/';
if (!is_dir($outputDir)) mkdir($outputDir, 0755, true);
$outputFile = $outputDir . $safeMawb . '_' . date('YmdHis') . '_FULL.xlsx';

// Giải phóng RAM trước khi ghi Excel
unset($hawbs, $dimByHawb);
gc_collect_cycles();

$writer = new Xlsx($spreadsheet);
$writer->setPreCalculateFormulas(false);
$writer->save($outputFile);

while (ob_get_level() > 0) ob_end_clean();
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="' . $dlFilename . '"');
header('Content-Length: ' . filesize($outputFile));
header('Cache-Control: no-store, no-cache, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');
$fp = fopen($outputFile, 'rb');
if ($fp) {
    while (!feof($fp)) { echo fread($fp, 8192); flush(); }
    fclose($fp);
}
@unlink($outputFile);
exit;
