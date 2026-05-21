<?php
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
requireLogin();

$db  = getDB();
$mid = (int)($_GET['id'] ?? 0);
if (!$mid) die('Invalid ID');

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
if (!$m) die('Manifest not found.');

// Load HAWBs
$hawbs = $db->query("
    SELECT h.*,
           s.name    AS shipper_name,
           s.address AS shipper_address,
           s.phone   AS shipper_phone,
           cn.name   AS cnee_name,
           cn.address AS cnee_address,
           cn.phone  AS cnee_phone,
           ap2.iata_code AS dest_code
    FROM hawbs h
    LEFT JOIN shippers   s   ON h.shipper_id    = s.id
    LEFT JOIN consignees cn  ON h.consignee_id  = cn.id
    LEFT JOIN airports   ap2 ON h.destination_id= ap2.id
    WHERE h.manifest_id = $mid
    ORDER BY h.seq_number ASC
")->fetch_all(MYSQLI_ASSOC);

$totalPcs = array_sum(array_column($hawbs, 'no_of_pieces'));
$totalGW  = array_sum(array_column($hawbs, 'gross_weight'));

// Pagination: 8 HAWBs per page
$perPage    = 8;
$pages      = array_chunk($hawbs, $perPage);
$totalPages = count($pages);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>AIR CARGO MANIFEST — <?= e($m['mawb_no']) ?></title>
    <style>
        * { margin:0; padding:0; box-sizing:border-box; }

        body {
            font-family: Arial, sans-serif;
            font-size: 8.5pt;
            background: #e8e8e8;
            color: #000;
        }

        @page { size: A4 landscape; margin: 8mm; }
        @media print {
            body { background: #fff; }
            .print-bar  { display: none !important; }
            .page-wrap  { box-shadow: none; margin: 0; }
            .page-break { page-break-after: always; }
            .page-break:last-child { page-break-after: auto; }
        }

        /* ── Screen print bar ── */
        .print-bar {
            position: sticky; top: 0; z-index: 100;
            background: #1a3a6b; color: #fff;
            padding: 8px 20px;
            display: flex; align-items: center; gap: 14px;
        }
        .print-bar button {
            background: #fff; color: #1a3a6b;
            border: none; border-radius: 5px;
            padding: 6px 20px; font-weight: 700;
            cursor: pointer; font-size: .9rem;
        }
        .print-bar a { color: rgba(255,255,255,.8); font-size:.85rem; text-decoration:none; }

        /* ── Page wrapper ── */
        .page-wrap {
            width: 277mm;
            min-height: 190mm;
            background: #fff;
            margin: 12px auto;
            padding: 6mm 7mm;
            box-shadow: 0 2px 16px rgba(0,0,0,.18);
            position: relative;
        }

        /* ── Header ── */
        .header-row {
            display: flex;
            align-items: flex-end;
            margin-bottom: 4px;
        }
        .logo-cell {
            width: 52mm;
            flex-shrink: 0;
        }
        .logo-cell img {
            max-height: 18mm;
            max-width: 48mm;
        }
        .logo-placeholder {
            width: 44mm; height: 16mm;
            border: 2px solid #1a3a6b;
            border-radius: 4px;
            display: flex; align-items: center; justify-content: center;
            background: #1a3a6b;
        }
        .logo-placeholder svg { width: 36px; height: 36px; }
        .title-cell {
            flex: 1;
            text-align: center;
        }
        .manifest-title {
            font-size: 22pt;
            font-weight: 900;
            letter-spacing: 1px;
            text-transform: uppercase;
        }

        /* ── Info block ── */
        .info-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 3px;
        }
        .info-table td {
            border: 1px solid #aaa;
            padding: 2px 5px;
            vertical-align: top;
            font-size: 8pt;
        }
        .info-table .lbl {
            font-weight: 700;
            white-space: nowrap;
        }
        .info-table .val {
            font-weight: 700;
            font-size: 10pt;
        }
        .company-block {
            text-align: center;
            border: 1px solid #aaa;
            padding: 3px 8px;
        }
        .company-name {
            font-size: 13pt;
            font-weight: 900;
            letter-spacing: .5px;
        }
        .company-addr {
            font-size: 7pt;
            color: #333;
            line-height: 1.5;
        }
        .consignee-block td {
            border: 1px solid #aaa;
            padding: 2px 5px;
            font-size: 8pt;
        }
        .cnee-val {
            font-weight: 700;
            color: #1a3a6b;
            font-size: 8.5pt;
        }

        /* ── HAWB table ── */
        .hawb-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 4px;
        }
        .hawb-table th {
            border: 1.5px solid #555;
            background: #f0f0f0;
            font-size: 8pt;
            font-weight: 700;
            text-align: center;
            padding: 4px 3px;
            text-transform: uppercase;
            vertical-align: middle;
        }
        .hawb-table td {
            border: 1px solid #888;
            padding: 4px 4px;
            vertical-align: middle;
            font-size: 8pt;
        }
        .hawb-table tr:last-child td { border-bottom: 1.5px solid #555; }
        .hawb-table .td-hawbno {
            font-weight: 700;
            font-size: 8.5pt;
            text-align: center;
            white-space: nowrap;
        }
        .hawb-table .td-num { text-align: center; font-weight: 700; }
        .hawb-table .td-gw  { text-align: center; }
        .hawb-table .td-dest{ text-align: center; font-weight: 700; }
        .hawb-table .td-term{
            text-align: center; font-weight: 700;
            color: #1a3a6b;
        }
        .hawb-table .td-commodity { font-size: 7.5pt; line-height: 1.55; }
        .hawb-table .td-party    { font-size: 7.5pt; line-height: 1.55; }
        .hawb-table .party-name  { font-weight: 700; font-size: 8pt; }

        /* Total row */
        .hawb-table .tr-total td {
            font-weight: 700;
            background: #f9f9f9;
            border-top: 2px solid #333;
            font-size: 9pt;
        }

        /* ── Page number watermark ── */
        .page-watermark {
            position: absolute;
            top: 50%; left: 50%;
            transform: translate(-50%, -50%);
            font-size: 110pt;
            font-weight: 900;
            color: rgba(0,0,0,.06);
            pointer-events: none;
            white-space: nowrap;
            z-index: 0;
        }

        /* ── Footer ── */
        .page-footer {
            margin-top: 4px;
            font-size: 6.5pt;
            color: #888;
            text-align: center;
            border-top: 1px solid #ddd;
            padding-top: 2px;
        }
    </style>
</head>
<body>

<!-- Print bar (screen only) -->
<div class="print-bar">
    <button onclick="window.print()">🖨 Print Manifest (A4 Landscape)</button>
    <span style="opacity:.7;">
        <?= e($m['mawb_no']) ?> ·
        <?= count($hawbs) ?> HAWBs ·
        <?= $totalPcs ?> pcs ·
        GW: <?= number_format($totalGW,1) ?> kg ·
        <?= $totalPages ?> page(s)
    </span>
    <a href="../operations/manifest/edit.php?id=<?= $mid ?>"
       style="margin-left:auto;">← Back</a>
</div>

<?php foreach ($pages as $pageIdx => $pageHawbs):
    $pageNum  = $pageIdx + 1;
    $isLast   = ($pageNum === $totalPages);
?>
<div class="page-wrap <?= !$isLast ? 'page-break' : '' ?>">

    <!-- Watermark -->
    <div class="page-watermark">Page <?= $pageNum ?></div>

    <!-- ══ HEADER ══════════════════════════════════════ -->
    <div class="header-row">
        <!-- Logo -->
        <div class="logo-cell">
            <?php
            $logoPath = __DIR__ . '/../assets/images/logo.png';
            if (file_exists($logoPath)): ?>
            <img src="<?= BASE_URL ?>assets/images/logo.png" alt="Logo">
            <?php else: ?>
            <div class="logo-placeholder">
                <svg viewBox="0 0 40 30" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <polygon points="2,28 20,4 38,28" fill="none" stroke="white" stroke-width="3"/>
                    <polygon points="8,28 20,10 32,28" fill="none" stroke="white" stroke-width="2.5"/>
                    <polygon points="14,28 20,16 26,28" fill="white" stroke="white" stroke-width="1"/>
                </svg>
            </div>
            <?php endif; ?>
        </div>

        <!-- Title -->
        <div class="title-cell">
            <div class="manifest-title">AIR CARGO MANIFEST</div>
        </div>
    </div>

    <!-- ══ INFO BLOCK ══════════════════════════════════ -->
    <table class="info-table">
        <tr>
            <!-- Left: MAWB / Flight / Route / Consignee -->
            <td width="52%" style="padding:0;border:none;">
                <table style="width:100%;border-collapse:collapse;">
                    <!-- MAWB -->
                    <tr>
                        <td style="border:1px solid #aaa;padding:3px 5px;white-space:nowrap;"
                            width="36%">
                            <span class="lbl">MASTER AIR WAYBILL No :</span>
                        </td>
                        <td style="border:1px solid #aaa;padding:3px 8px;" colspan="3">
                            <span class="val" style="font-size:12pt;letter-spacing:.5px;">
                                <?= e($m['mawb_no']) ?>
                            </span>
                        </td>
                    </tr>
                    <!-- Flight -->
                    <tr>
                        <td style="border:1px solid #aaa;padding:2px 5px;">
                            <span class="lbl">FLIGHT NO:</span>
                        </td>
                        <td style="border:1px solid #aaa;padding:2px 6px;font-weight:700;">
                            <?= e($m['flight_no'] ?? '') ?>
                        </td>
                        <td style="border:1px solid #aaa;padding:2px 6px;font-weight:700;">
                            <?= $m['flight_date'] ? date('d-M', strtotime($m['flight_date'])) : '' ?>
                        </td>
                        <td style="border:1px solid #aaa;padding:2px 5px;"></td>
                    </tr>
                    <!-- Route -->
                    <tr>
                        <td style="border:1px solid #aaa;padding:2px 5px;">
                            <span class="lbl">FROM:</span>
                        </td>
                        <td style="border:1px solid #aaa;padding:2px 6px;font-weight:700;font-size:10pt;text-align:center;">
                            <?= e($m['origin_code'] ?? '') ?>
                        </td>
                        <td style="border:1px solid #aaa;padding:2px 5px;">
                            <span class="lbl">TO:</span>
                        </td>
                        <td style="border:1px solid #aaa;padding:2px 6px;font-weight:700;font-size:10pt;text-align:center;">
                            <?= e($m['dest_code'] ?? '') ?>
                        </td>
                    </tr>
                    <!-- Consignee label -->
                    <tr>
                        <td colspan="4" style="border:1px solid #aaa;padding:2px 5px;">
                            <span class="lbl">CONSIGNEE:</span>
                        </td>
                    </tr>
                    <!-- Consignee value -->
                    <tr>
                        <td colspan="4" style="border:1px solid #aaa;padding:3px 8px;min-height:18mm;vertical-align:top;">
                            <?php if (!empty($m['customer_name'])): ?>
                            <div class="cnee-val"><?= e(strtoupper($m['customer_name'])) ?></div>
                            <?php if (!empty($m['customer_address'])): ?>
                            <div class="cnee-val" style="font-weight:400;font-size:7.5pt;">
                                <?= e($m['customer_address']) ?>
                            </div>
                            <?php endif; ?>
                            <?php if (!empty($m['customer_phone'])): ?>
                            <div class="cnee-val" style="font-weight:400;font-size:7.5pt;">
                                T:<?= e($m['customer_phone']) ?>
                                <?= !empty($m['customer_fax']) ? '  (DIR: ' . e($m['customer_fax']) . ')' : '' ?>
                            </div>
                            <?php endif; ?>
                            <?php else: ?>
                            <span style="color:#999;font-style:italic;">— Not specified —</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                </table>
            </td>

            <!-- Right: Company block -->
            <td width="48%" style="border:1px solid #aaa;vertical-align:top;padding:4px 10px;">
                <div class="company-name"><?= e(COMPANY_NAME) ?></div>
                <div class="company-addr" style="margin-top:4px;">
                    Address: <?= e(COMPANY_ADDRESS) ?><br>
                    Tel: <?= e(COMPANY_TEL) ?><br>
                    Fax: <?= e(COMPANY_FAX) ?><br>
                    MST: <?= e(COMPANY_TAX) ?>
                </div>
            </td>
        </tr>
    </table>

    <!-- ══ HAWB TABLE ══════════════════════════════════ -->
    <table class="hawb-table">
        <thead>
            <tr>
                <th width="10%">HAWB NO.</th>
                <th width="5%">NO.OF<br>CTNS</th>
                <th width="6%">GW<br>(KG)</th>
                <th width="16%">COMMODITY</th>
                <th width="5%">DEST</th>
                <th width="22%">SHIPPER</th>
                <th width="28%">CONSIGNEE</th>
                <th width="8%">PAYMENT<br>TERM</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($pageHawbs as $hw): ?>
        <tr>
            <td class="td-hawbno"><?= e($hw['hawb_no']) ?></td>
            <td class="td-num"><?= (int)$hw['no_of_pieces'] ?></td>
            <td class="td-gw">
                <?= $hw['gross_weight'] > 0 ? number_format($hw['gross_weight'],1) : '' ?>
            </td>
            <td class="td-commodity">
                <?php
                $lines = array_filter(array_map('trim', explode("\n", $hw['commodity'] ?? '')));
                foreach ($lines as $line) echo e($line) . '<br>';
                ?>
            </td>
            <td class="td-dest"><?= e($hw['dest_code'] ?? '') ?></td>
            <td class="td-party">
                <?php if (!empty($hw['shipper_name'])): ?>
                <div class="party-name"><?= e(strtoupper($hw['shipper_name'])) ?></div>
                <?php endif; ?>
                <?php if (!empty($hw['shipper_address'])): ?>
                <div><?= e(strtoupper($hw['shipper_address'])) ?></div>
                <?php endif; ?>
                <?php if (!empty($hw['shipper_phone'])): ?>
                <div>T:<?= e($hw['shipper_phone']) ?></div>
                <?php endif; ?>
            </td>
            <td class="td-party">
                <?php if (!empty($hw['cnee_name'])): ?>
                <div class="party-name"><?= e(strtoupper($hw['cnee_name'])) ?></div>
                <?php endif; ?>
                <?php if (!empty($hw['cnee_address'])): ?>
                <div><?= e(strtoupper($hw['cnee_address'])) ?></div>
                <?php endif; ?>
                <?php if (!empty($hw['cnee_phone'])): ?>
                <div>TEL:<?= e($hw['cnee_phone']) ?></div>
                <?php endif; ?>
            </td>
            <td class="td-term"><?= e($hw['payment_term'] ?? 'PP') ?></td>
        </tr>
        <?php endforeach; ?>

        <!-- Total row (chỉ ở trang cuối) -->
        <?php if ($isLast): ?>
        <tr class="tr-total">
            <td style="font-weight:700;">Total</td>
            <td class="td-num"><?= $totalPcs ?></td>
            <td class="td-gw">
                <?= $totalGW > 0 ? number_format($totalGW, 1) : '' ?>
            </td>
            <td colspan="5"></td>
        </tr>
        <?php else: ?>
        <!-- Subtotal row cho các trang giữa -->
        <tr class="tr-total">
            <td style="color:#888;font-style:italic;font-size:7.5pt;">
                Subtotal (page <?= $pageNum ?>)
            </td>
            <td class="td-num">
                <?= array_sum(array_column($pageHawbs,'no_of_pieces')) ?>
            </td>
            <td class="td-gw">
                <?= number_format(array_sum(array_column($pageHawbs,'gross_weight')),1) ?>
            </td>
            <td colspan="5" style="font-size:7.5pt;color:#888;">
                continued on next page…
            </td>
        </tr>
        <?php endif; ?>
        </tbody>
    </table>

    <!-- Page footer -->
    <div class="page-footer">
        <?= e(COMPANY_NAME) ?> &nbsp;·&nbsp;
        <?= e(COMPANY_ADDRESS) ?> &nbsp;·&nbsp;
        Tel: <?= e(COMPANY_TEL) ?> &nbsp;·&nbsp;
        Printed: <?= date('d-M-Y H:i') ?> &nbsp;·&nbsp;
        Page <?= $pageNum ?> / <?= $totalPages ?>
    </div>

</div><!-- /page-wrap -->
<?php endforeach; ?>

<script>
if (new URLSearchParams(location.search).get('print') === '1') {
    window.addEventListener('load', () => window.print());
}
</script>
</body>
</html>