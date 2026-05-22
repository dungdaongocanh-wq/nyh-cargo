<?php
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
requireLogin();
session_write_close();

set_time_limit(60);
ini_set('memory_limit', '128M');

$db  = getDB();
$hid = (int)($_GET['id'] ?? 0);

function failExport(int $hid, string $message, ?string $outputFile = null): void {
    if ($outputFile && is_file($outputFile)) @unlink($outputFile);
    setFlash('danger', $message);
    header('Location: ../operations/hawb/edit.php?id=' . $hid . '&err=' . urlencode($message));
    exit;
}
if (!$hid) failExport(0, 'Thiếu ID HAWB hợp lệ.');

// ── Query ────────────────────────────────────────────────────────────────────
$stmt = $db->prepare("
    SELECT h.*,
           s.name    AS shipper_name,    s.address  AS shipper_address,
           s.city    AS shipper_city,    s.phone    AS shipper_phone,
           cn.name   AS cnee_name,       cn.address AS cnee_address,
           cn.city   AS cnee_city,       cn.phone   AS cnee_phone,
           cn.usci_no AS cnee_usci,      cn.account_no AS cnee_acct,
           ap1.iata_code AS origin_code,
           ap2.iata_code AS dest_code,
           m.mawb_no, m.flight_no, m.flight_date,
           al.code AS airline_code, al.name AS airline_name
    FROM hawbs h
    LEFT JOIN shippers   s   ON h.shipper_id    = s.id
    LEFT JOIN consignees cn  ON h.consignee_id  = cn.id
    LEFT JOIN airports   ap1 ON h.origin_id     = ap1.id
    LEFT JOIN airports   ap2 ON h.destination_id= ap2.id
    LEFT JOIN manifests  m   ON h.manifest_id   = m.id
    LEFT JOIN airlines   al  ON m.airline_id    = al.id
    WHERE h.id = ?
");
$stmt->bind_param('i', $hid);
$stmt->execute();
$h = $stmt->get_result()->fetch_assoc();
$stmt->close();
if (!$h) failExport($hid, 'Không tìm thấy HAWB.');
$h = array_map(fn($v) => $v === null ? '' : (string)$v, $h);

if ((int)$h['is_weighed'] === 0 || (float)$h['gross_weight'] <= 0)
    failExport($hid, 'HAWB chưa cân. Vui lòng cân hàng trước khi xuất Excel.');

$dimGroups = $db->query("
    SELECT length, width, height, qty_pieces
    FROM hawb_dim_groups WHERE hawb_id={$hid} ORDER BY id
")->fetch_all(MYSQLI_ASSOC);

// ── Template & map ───────────────────────────────────────────────────────────
$templateFile = __DIR__ . '/../assets/templates/hawb_template.xlsx';
if (!file_exists($templateFile)) failExport($hid, 'Không tìm thấy template HAWB Excel.');
$map = require __DIR__ . '/../config/hawb_excel_map.php';
if (!is_array($map)) failExport($hid, 'Cell map HAWB không hợp lệ.');

// ── Helpers ──────────────────────────────────────────────────────────────────
function fmtNum($v): string {
    if ($v === null || $v === '') return '';
    $f = (float)$v;
    return ($f == floor($f)) ? (string)(int)$f : (string)round($f, 2);
}
function colToNum(string $col): int {
    $r = 0;
    for ($i = 0, $len = strlen($col); $i < $len; $i++)
        $r = $r * 26 + (ord($col[$i]) - 64);
    return $r;
}

// ── Build cellData ───────────────────────────────────────────────────────────
$dimParts = [];
foreach ($dimGroups as $dg) {
    $l  = fmtNum($dg['length']); $w = fmtNum($dg['width']);
    $hv = fmtNum($dg['height']); $q = (int)$dg['qty_pieces'];
    if ($l && $w && $hv && $q) $dimParts[] = "{$l}x{$w}x{$hv}/{$q}PCS";
}
$hp = [];
if ($h['notify_party']  !== '') $hp[] = $h['notify_party'];
if ($h['handling_info'] !== '') $hp[] = $h['handling_info'];

$cellData = [
    'mawb_no'             => $h['mawb_no'],
    'hawb_no'             => $h['hawb_no'],
    'hawb_no_footer'      => $h['hawb_no'],
    'shipper_name'        => strtoupper($h['shipper_name']),
    'shipper_address'     => strtoupper($h['shipper_address']),
    'shipper_city'        => strtoupper($h['shipper_city']),
    'shipper_phone'       => $h['shipper_phone'],
    'consignee_name'      => strtoupper($h['cnee_name']),
    'consignee_address'   => strtoupper($h['cnee_address']),
    'consignee_city'      => strtoupper($h['cnee_city']),
    'consignee_phone'     => $h['cnee_phone'],
    'consignee_acct'      => $h['cnee_acct'],
    'consignee_usci'      => $h['cnee_usci'],
    'notify_party'        => $h['notify_party'],
    'issuing_carrier'     => strtoupper($h['airline_name']),
    'agent_name'          => COMPANY_NAME,
    'agent_iata'          => defined('COMPANY_IATA') ? (string)COMPANY_IATA : '',
    'airport_departure'   => $h['origin_code'],
    'airport_dest'        => $h['dest_code'],
    'routing_by1'         => $h['airline_code'],
    'flight_no'           => $h['flight_no'],
    'flight_date'         => $h['flight_date'] !== '' ? date('d-M-y', strtotime($h['flight_date'])) : '',
    'payment_term'        => $h['payment_term']            ?: 'PP',
    'currency'            => $h['currency']                ?: 'USD',
    'rate_class'          => $h['rate_class']              ?: 'Q',
    'commodity_item_no'   => $h['commodity_item_no'],
    'declared_carriage'   => $h['declared_value_carriage'] ?: 'NVD',
    'declared_customs'    => $h['declared_value_customs']  ?: 'AS PER INV',
    'amount_insurance'    => $h['amount_insurance']        ?: 'XXX',
    'accounting_info'     => $h['accounting_info']         ?: 'FREIGHT PREPAID',
    'no_of_pieces'        => (string)(int)$h['no_of_pieces'],
    'no_of_pieces_footer' => (string)(int)$h['no_of_pieces'],
    'gross_weight'        => (float)$h['gross_weight']      > 0 ? fmtNum($h['gross_weight'])      : '',
    'gross_weight_footer' => (float)$h['gross_weight']      > 0 ? fmtNum($h['gross_weight'])      : '',
    'gross_weight_unit'   => 'K',
    'volume_weight'       => (float)$h['volume_weight']     > 0 ? fmtNum($h['volume_weight'])     : '',
    'chargeable_weight'   => (float)$h['chargeable_weight'] > 0 ? fmtNum($h['chargeable_weight']) : '',
    'commodity_line1'     => $h['commodity'],
    'commodity_line2' => '', 'commodity_line3' => '',
    'commodity_line4' => '', 'commodity_line5' => '',
    'dim_info'            => implode('  ', $dimParts),
    'handling_info'       => implode("\n", $hp),
    'execution_place'     => $h['origin_code'],
    'execution_date'      => date('d-M-Y'),
    'signature_origin'    => COMPANY_NAME,
];

// Build finalCellMap (UPPERCASE ref => value, chỉ giữ cell có giá trị)
// Hỗ trợ cả string lẫn array trong map (1 field → nhiều ô)
$finalCellMap = [];
foreach ($map as $field => $cell) {
    $cells = is_array($cell) ? $cell : [$cell];
    foreach ($cells as $c) {
        $c = strtoupper(trim((string)$c));
        if ($c === '' || !isset($cellData[$field])) continue;
        $val = (string)$cellData[$field];
        if ($val !== '') $finalCellMap[$c] = $val;
    }
}
if (empty($finalCellMap)) failExport($hid, 'Không có cell map hợp lệ để ghi dữ liệu HAWB.');

// ── Copy template ─────────────────────────────────────────────────────────────
$outputDir = __DIR__ . '/../assets/outputs/';
if (!is_dir($outputDir)) mkdir($outputDir, 0755, true);
$safeNo     = preg_replace('/[^A-Z0-9]/', '', strtoupper($h['hawb_no']));
$outputFile = $outputDir . 'HAWB_' . $safeNo . '_' . date('YmdHis') . '.xlsx';
if (!copy($templateFile, $outputFile)) failExport($hid, 'Không thể tạo file export từ template.');

// ── Open ZIP ──────────────────────────────────────────────────────────────────
$zip = new ZipArchive();
if ($zip->open($outputFile) !== true) failExport($hid, 'Không thể mở file Excel để ghi dữ liệu.', $outputFile);

// ── Find active sheet ─────────────────────────────────────────────────────────
$wbXml  = (string)$zip->getFromName('xl/workbook.xml');
$wbRels = (string)$zip->getFromName('xl/_rels/workbook.xml.rels');
if ($wbXml === '' || $wbRels === '') {
    $zip->close();
    failExport($hid, 'Template HAWB không hợp lệ (thiếu workbook metadata).', $outputFile);
}
$relMap = [];
preg_match_all('/<Relationship\s[^>]*Id="([^"]*)"[^>]*Target="([^"]*)"/i', $wbRels, $rp, PREG_SET_ORDER);
foreach ($rp as $r) {
    $t = $r[2];
    if (strpos($t, 'xl/') !== 0) $t = 'xl/' . ltrim($t, '/');
    $relMap[$r[1]] = $t;
}
preg_match('/<workbookView[^>]*activeTab="(\d+)"/i', $wbXml, $am);
$activeTab = isset($am[1]) ? (int)$am[1] : 0;
preg_match_all('/<sheet\b[^>]*r:id="([^"]*)"/i', $wbXml, $sr, PREG_SET_ORDER);
$activeRid   = $sr[$activeTab][1] ?? ($sr[0][1] ?? '');
$activeSheet = $relMap[$activeRid] ?? 'xl/worksheets/sheet1.xml';
if (!$zip->getFromName($activeSheet)) $activeSheet = 'xl/worksheets/sheet1.xml';
if (!$zip->getFromName($activeSheet)) {
    $zip->close();
    failExport($hid, 'Không tìm thấy worksheet trong template HAWB.', $outputFile);
}

// ── Load raw XML ─────────────────────────────────────────────────────────────
$sheetRaw = (string)$zip->getFromName($activeSheet);
$ssRaw    = $zip->getFromName('xl/sharedStrings.xml');
$hasSS    = (is_string($ssRaw) && strlen($ssRaw) > 0);
$ssRaw    = $hasSS ? (string)$ssRaw : '';

// ── Parse sharedStrings bằng DOMDocument ─────────────────────────────────────
$sharedStrings = [];
$ssXml         = null;
$ssValueToIdx  = [];

if ($hasSS) {
    $ssXml = new DOMDocument();
    $ssXml->loadXML($ssRaw);
    foreach ($ssXml->getElementsByTagName('si') as $idx => $si) {
        $text = '';
        foreach ($si->getElementsByTagName('t') as $t) $text .= $t->nodeValue;
        $sharedStrings[$idx] = $text;
    }
    $ssValueToIdx = array_flip($sharedStrings);
}

// ── Helper: thêm value vào sharedStrings ─────────────────────────────────────
function addSharedString(string $value, array &$sharedStrings, array &$ssValueToIdx, ?DOMDocument $ssXml): int {
    if (isset($ssValueToIdx[$value])) return $ssValueToIdx[$value];
    $idx                  = count($sharedStrings);
    $sharedStrings[$idx]  = $value;
    $ssValueToIdx[$value] = $idx;
    if ($ssXml) {
        $sst = $ssXml->getElementsByTagName('sst')->item(0);
        $si  = $ssXml->createElement('si');
        $t   = $ssXml->createElement('t');
        $t->appendChild($ssXml->createTextNode($value));
        if (strpos($value, "\n") !== false || substr($value, 0, 1) === ' ' || substr($value, -1) === ' ')
            $t->setAttribute('xml:space', 'preserve');
        $si->appendChild($t);
        $sst->appendChild($si);
        $total = count($sharedStrings);
        $sst->setAttribute('count',       $total);
        $sst->setAttribute('uniqueCount', $total);
    }
    return $idx;
}

// ── Parse sheet XML bằng DOMDocument ─────────────────────────────────────────
$sheetDom = new DOMDocument();
$sheetDom->loadXML($sheetRaw);

// ── Bước 1: Ghi các cell ĐÃ TỒN TẠI trong sheet ─────────────────────────────
$writtenRefs = [];
$cellNodes   = $sheetDom->getElementsByTagName('c');

// Snapshot vào mảng để tránh lỗi live NodeList khi sửa DOM
$cellNodeList = [];
foreach ($cellNodes as $cn) $cellNodeList[] = $cn;

foreach ($cellNodeList as $cellNode) {
    $ref = strtoupper($cellNode->getAttribute('r'));
    if (!isset($finalCellMap[$ref])) continue;

    $value             = (string)$finalCellMap[$ref];
    $writtenRefs[$ref] = true;

    $toRemove = [];
    foreach ($cellNode->childNodes as $child) {
        if (in_array($child->nodeName, ['v', 'is'])) $toRemove[] = $child;
    }
    foreach ($toRemove as $node) $cellNode->removeChild($node);

    if ($value === '') { $cellNode->removeAttribute('t'); continue; }

    $isNumeric = is_numeric($value) && strpos($value, "\n") === false;
    if ($isNumeric) {
        $cellNode->removeAttribute('t');
        $cellNode->appendChild($sheetDom->createElement('v', $value));
    } elseif ($hasSS) {
        $idx = addSharedString($value, $sharedStrings, $ssValueToIdx, $ssXml);
        $cellNode->setAttribute('t', 's');
        $cellNode->appendChild($sheetDom->createElement('v', (string)$idx));
    } else {
        $cellNode->setAttribute('t', 'inlineStr');
        $is = $sheetDom->createElement('is');
        $t  = $sheetDom->createElement('t');
        $t->appendChild($sheetDom->createTextNode($value));
        if (strpos($value, "\n") !== false) $t->setAttribute('xml:space', 'preserve');
        $is->appendChild($t);
        $cellNode->appendChild($is);
    }
}

// ── Bước 2: Tạo mới các cell CHƯA TỒN TẠI (nhóm theo row, 1 lần) ────────────
$missingCells = [];
foreach ($finalCellMap as $ref => $value) {
    if (isset($writtenRefs[$ref])) continue;
    if ($value === '') continue;
    $missingCells[$ref] = (string)$value;
}

if (!empty($missingCells)) {
    // Nhóm theo row number
    $cellsByRow = [];
    foreach ($missingCells as $ref => $value) {
        preg_match('/^([A-Z]+)(\d+)$/', $ref, $m);
        if (!$m) continue;
        $cellsByRow[(int)$m[2]][$ref] = $value;
    }

    $sheetDataNodes = $sheetDom->getElementsByTagName('sheetData');
    if ($sheetDataNodes->length > 0) {
        $sheetData = $sheetDataNodes->item(0);

        // Snapshot danh sách row hiện có
        $existingRows = [];
        foreach ($sheetData->childNodes as $child) {
            if ($child->nodeName === 'row')
                $existingRows[(int)$child->getAttribute('r')] = $child;
        }

        foreach ($cellsByRow as $rowNum => $cells) {
            if (isset($existingRows[$rowNum])) {
                // Row đã tồn tại → chèn cell mới vào đúng vị trí theo thứ tự cột
                $rowNode    = $existingRows[$rowNum];
                $existingCs = [];
                foreach ($rowNode->childNodes as $cn) {
                    if ($cn->nodeName === 'c')
                        $existingCs[strtoupper($cn->getAttribute('r'))] = $cn;
                }

                foreach ($cells as $ref => $value) {
                    if (isset($existingCs[$ref])) {
                        $cn = $existingCs[$ref];
                    } else {
                        $cn = $sheetDom->createElement('c');
                        $cn->setAttribute('r', $ref);
                        preg_match('/^([A-Z]+)/', $ref, $colM);
                        $newColNum = colToNum($colM[1]);
                        $inserted  = false;
                        foreach ($rowNode->childNodes as $sibling) {
                            if ($sibling->nodeName !== 'c') continue;
                            preg_match('/^([A-Z]+)/', $sibling->getAttribute('r'), $sibColM);
                            if (colToNum($sibColM[1]) > $newColNum) {
                                $rowNode->insertBefore($cn, $sibling);
                                $inserted = true;
                                break;
                            }
                        }
                        if (!$inserted) $rowNode->appendChild($cn);
                    }

                    $isNumeric = is_numeric($value) && strpos($value, "\n") === false;
                    if ($isNumeric) {
                        $cn->removeAttribute('t');
                        $cn->appendChild($sheetDom->createElement('v', $value));
                    } elseif ($hasSS) {
                        $idx = addSharedString($value, $sharedStrings, $ssValueToIdx, $ssXml);
                        $cn->setAttribute('t', 's');
                        $cn->appendChild($sheetDom->createElement('v', (string)$idx));
                    } else {
                        $cn->setAttribute('t', 'inlineStr');
                        $is = $sheetDom->createElement('is');
                        $t  = $sheetDom->createElement('t');
                        $t->appendChild($sheetDom->createTextNode($value));
                        if (strpos($value, "\n") !== false) $t->setAttribute('xml:space', 'preserve');
                        $is->appendChild($t);
                        $cn->appendChild($is);
                    }
                }
            } else {
                // Row chưa tồn tại → tạo row mới
                $newRow = $sheetDom->createElement('row');
                $newRow->setAttribute('r', $rowNum);

                foreach ($cells as $ref => $value) {
                    $cn = $sheetDom->createElement('c');
                    $cn->setAttribute('r', $ref);
                    $isNumeric = is_numeric($value) && strpos($value, "\n") === false;
                    if ($isNumeric) {
                        $cn->appendChild($sheetDom->createElement('v', $value));
                    } elseif ($hasSS) {
                        $idx = addSharedString($value, $sharedStrings, $ssValueToIdx, $ssXml);
                        $cn->setAttribute('t', 's');
                        $cn->appendChild($sheetDom->createElement('v', (string)$idx));
                    } else {
                        $cn->setAttribute('t', 'inlineStr');
                        $is = $sheetDom->createElement('is');
                        $t  = $sheetDom->createElement('t');
                        $t->appendChild($sheetDom->createTextNode($value));
                        if (strpos($value, "\n") !== false) $t->setAttribute('xml:space', 'preserve');
                        $is->appendChild($t);
                        $cn->appendChild($is);
                    }
                    $newRow->appendChild($cn);
                }

                // Chèn row vào đúng vị trí theo thứ tự row number
                $inserted = false;
                foreach ($sheetData->childNodes as $sibling) {
                    if ($sibling->nodeName !== 'row') continue;
                    if ((int)$sibling->getAttribute('r') > $rowNum) {
                        $sheetData->insertBefore($newRow, $sibling);
                        $inserted = true;
                        break;
                    }
                }
                if (!$inserted) $sheetData->appendChild($newRow);
            }
        }
    }
}

// ── Save ZIP ──────────────────────────────────────────────────────────────────
$zip->addFromString($activeSheet, $sheetDom->saveXML());
if ($hasSS && $ssXml) $zip->addFromString('xl/sharedStrings.xml', $ssXml->saveXML());
// Xóa calcChain stale — Excel tự rebuild, tránh lỗi "#VALUE" khi mở
$zip->deleteName('xl/calcChain.xml');
$zip->close();
unset($zip);

$db->query("UPDATE hawbs SET is_printed=1, printed_at=NOW() WHERE id={$hid}");

// ── Stream to browser ─────────────────────────────────────────────────────────
$filename = 'HAWB_' . $safeNo . '.xlsx';
while (ob_get_level() > 0) ob_end_clean();
clearstatcache(true, $outputFile);
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Content-Length: ' . filesize($outputFile));
header('Cache-Control: no-store, no-cache, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');
$fp = fopen($outputFile, 'rb');
if (!$fp) { @unlink($outputFile); exit; }
while (!feof($fp)) { echo fread($fp, 8192); flush(); }
fclose($fp);
@unlink($outputFile);
exit;
