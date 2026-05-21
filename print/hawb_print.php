<?php
/**
 * HAWB A4 Print — HTML/CSS printable
 * URL: print/hawb_print.php?id=HAWB_ID
 */
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
requireLogin();

$db  = getDB();
$hid = (int)($_GET['id'] ?? 0);
if (!$hid) die('Invalid ID');

$stmt = $db->prepare("
    SELECT h.*,
           s.name  AS shipper_name,  s.address AS shipper_address,
           s.city  AS shipper_city,  s.country AS shipper_country,
           s.phone AS shipper_phone, s.fax     AS shipper_fax,
           cn.name AS cnee_name,     cn.address AS cnee_address,
           cn.city AS cnee_city,     cn.country AS cnee_country,
           cn.phone AS cnee_phone,   cn.fax     AS cnee_fax,
           cn.usci_no AS cnee_usci,  cn.account_no AS cnee_acct,
           ap1.iata_code AS origin_code, ap1.name AS origin_name,
           ap2.iata_code AS dest_code,   ap2.name AS dest_name,
           m.mawb_no, m.flight_no, m.flight_date,
           al.code AS airline_code,  al.name AS airline_name,
           m.shipper_name  AS mawb_shipper_name,
           m.shipper_address AS mawb_shipper_address,
           m.shipper_tel   AS mawb_shipper_tel,
           m.shipper_fax   AS mawb_shipper_fax,
           m.shipper_tax   AS mawb_shipper_tax
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
if (!$h) die('HAWB not found.');

// Load pieces
$pieces = $db->query("SELECT * FROM hawb_pieces WHERE hawb_id=$hid ORDER BY piece_no")->fetch_all(MYSQLI_ASSOC);

// Mark printed
$db->query("UPDATE hawbs SET is_printed=1, printed_at=NOW() WHERE id=$hid");

// Commodity lines
$commodityLines = array_values(array_filter(array_map('trim', explode("\n", $h['commodity'] ?? ''))));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>HAWB <?= e($h['hawb_no']) ?></title>
    <style>
        * { margin:0; padding:0; box-sizing:border-box; }
        body {
            font-family: Arial, sans-serif;
            font-size: 7.5pt;
            color: #000;
            background: #fff;
        }
        @page { size: A4 portrait; margin: 8mm 8mm 8mm 8mm; }
        @media print {
            body { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
            .no-print { display: none !important; }
            .page-break { page-break-after: always; }
        }

        /* ── Print button bar ── */
        .print-bar {
            background: #1a56db; color: #fff;
            padding: 8px 16px;
            display: flex; align-items: center; gap: 12px;
        }
        .print-bar button {
            background: #fff; color: #1a56db;
            border: none; border-radius: 6px;
            padding: 6px 18px; font-weight: 700; cursor: pointer;
        }
        .print-bar a {
            color: rgba(255,255,255,.8); font-size: .85rem; text-decoration: none;
        }

        /* ── HAWB Layout ── */
        .hawb-page {
            width: 210mm;
            min-height: 297mm;
            padding: 6mm;
            margin: 0 auto;
        }
        .hawb-title {
            text-align: center;
            font-size: 10pt;
            font-weight: bold;
            letter-spacing: 2px;
            text-transform: uppercase;
            border-bottom: 2px solid #000;
            padding-bottom: 3px;
            margin-bottom: 4px;
        }
        .hawb-subtitle {
            text-align: center;
            font-size: 7pt;
            color: #555;
            margin-bottom: 4px;
        }

        /* ── Tables ── */
        table { width: 100%; border-collapse: collapse; }
        td, th {
            border: 1px solid #333;
            padding: 2px 3px;
            vertical-align: top;
        }
        .cell-label {
            font-size: 6pt;
            color: #555;
            display: block;
            margin-bottom: 1px;
        }
        .cell-value {
            font-size: 7.5pt;
            font-weight: bold;
        }
        .cell-value-lg {
            font-size: 10pt;
            font-weight: bold;
        }
        .no-border { border: none !important; }
        .border-top { border-top: 2px solid #000 !important; }
        .bg-light { background: #f5f5f5; }
        .text-center { text-align: center; }
        .text-right  { text-align: right; }

        /* ── Header row ── */
        .header-table td { border: 1px solid #333; }
        .hawb-no-box {
            font-size: 13pt;
            font-weight: 900;
            letter-spacing: 1.5px;
            text-align: center;
            padding: 4px;
            border: 2px solid #000;
        }

        /* ── Pieces detail ── */
        .pieces-table th {
            background: #e8e8e8;
            font-size: 6.5pt;
            text-align: center;
        }
        .pieces-table td { text-align: center; font-size: 7pt; }

        /* ── Signature area ── */
        .sig-box {
            border: 1px solid #333;
            height: 18mm;
            padding: 3px;
        }
    </style>
</head>
<body>

<!-- Print Bar (screen only) -->
<div class="print-bar no-print">
    <button onclick="window.print()">🖨 Print HAWB A4</button>
    <a href="hawb_excel.php?id=<?= $hid ?>">📥 Download Excel</a>
    <a href="../operations/manifest/edit.php?id=<?= $h['manifest_id'] ?>&weigh=<?= $hid ?>"
       style="margin-left:auto;">← Back to Manifest</a>
</div>

<div class="hawb-page">

    <!-- ── TITLE ── -->
    <div class="hawb-title">HOUSE AIR WAYBILL (HAWB)</div>
    <div class="hawb-subtitle">Non-Negotiable · <?= COMPANY_NAME ?></div>

    <!-- ── ROW 1: HAWB No + MAWB No ── -->
    <table style="margin-bottom:2px;">
        <tr>
            <td width="65%">
                <span class="cell-label">Shipper's Name and Address</span>
                <span class="cell-value">
                    <?= e(strtoupper($h['shipper_name'] ?? '—')) ?><br>
                    <?= e($h['shipper_address'] ?? '') ?><br>
                    <?= e(trim(($h['shipper_city']??'') . ' ' . ($h['shipper_country']??''))) ?>
                </span>
                <?php if ($h['shipper_phone']): ?>
                <br><span style="font-size:6.5pt;">Tel: <?= e($h['shipper_phone']) ?></span>
                <?php endif; ?>
            </td>
            <td width="35%">
                <span class="cell-label">House Air Waybill No.</span>
                <div class="hawb-no-box"><?= e($h['hawb_no']) ?></div>
                <div style="margin-top:4px;">
                    <span class="cell-label">Ref. Master Air Waybill No.</span>
                    <span class="cell-value"><?= e($h['mawb_no'] ?? '') ?></span>
                </div>
            </td>
        </tr>
    </table>

    <!-- ── ROW 2: CONSIGNEE ── -->
    <table style="margin-bottom:2px;">
        <tr>
            <td width="65%">
                <span class="cell-label">Consignee's Name and Address</span>
                <span class="cell-value">
                    <?= e(strtoupper($h['cnee_name'] ?? '—')) ?><br>
                    <?= e($h['cnee_address'] ?? '') ?><br>
                    <?= e(trim(($h['cnee_city']??'') . ' ' . ($h['cnee_country']??''))) ?>
                </span>
                <?php if ($h['cnee_phone']): ?>
                <br><span style="font-size:6.5pt;">Tel: <?= e($h['cnee_phone']) ?></span>
                <?php endif; ?>
                <?php if ($h['cnee_usci']): ?>
                <br><span style="font-size:6.5pt;">USCI: <?= e($h['cnee_usci']) ?></span>
                <?php endif; ?>
                <?php if ($h['cnee_acct']): ?>
                <br><span style="font-size:6.5pt;">Acct No: <?= e($h['cnee_acct']) ?></span>
                <?php endif; ?>
            </td>
            <td width="35%">
                <span class="cell-label">Issuing Carrier's Agent Name and City</span>
                <span class="cell-value"><?= e(COMPANY_NAME) ?></span><br>
                <span style="font-size:6.5pt;"><?= e(COMPANY_ADDRESS ?? '') ?></span>
                <div style="margin-top:4px;">
                    <span class="cell-label">Accounting Information</span>
                    <span class="cell-value"><?= e($h['accounting_info'] ?? 'FREIGHT PREPAID') ?></span>
                </div>
            </td>
        </tr>
    </table>

    <!-- ── ROW 3: ROUTING ── -->
    <table style="margin-bottom:2px;">
        <tr>
            <td width="16%" class="text-center">
                <span class="cell-label">Airport of Departure</span>
                <span class="cell-value-lg"><?= e($h['origin_code']) ?></span>
            </td>
            <td width="8%" class="text-center">
                <span class="cell-label">To</span>
                <span class="cell-value"><?= e($h['dest_code']) ?></span>
            </td>
            <td width="12%" class="text-center">
                <span class="cell-label">By First Carrier</span>
                <span class="cell-value"><?= e($h['airline_code']) ?></span>
            </td>
            <td width="8%" class="text-center">
                <span class="cell-label">To</span>
                <span class="cell-value"></span>
            </td>
            <td width="10%" class="text-center">
                <span class="cell-label">By</span>
                <span class="cell-value"></span>
            </td>
            <td width="12%" class="text-center">
                <span class="cell-label">Flight/Date</span>
                <span class="cell-value">
                    <?= e($h['flight_no']) ?><br>
                    <?= $h['flight_date'] ? date('d-M-y', strtotime($h['flight_date'])) : '' ?>
                </span>
            </td>
            <td width="16%" class="text-center">
                <span class="cell-label">Airport of Destination</span>
                <span class="cell-value-lg"><?= e($h['dest_code']) ?></span>
            </td>
            <td width="18%">
                <span class="cell-label">Requested Flight/Date</span>
                <span class="cell-value">
                    <?= e($h['flight_no']) ?> /
                    <?= $h['flight_date'] ? date('d-M-y', strtotime($h['flight_date'])) : '' ?>
                </span>
            </td>
        </tr>
    </table>

    <!-- ── ROW 4: PAYMENT / DECLARED VALUES ── -->
    <table style="margin-bottom:2px;">
        <tr>
            <td width="8%" class="text-center">
                <span class="cell-label">Payment</span>
                <span class="cell-value"><?= e($h['payment_term'] ?? 'PP') ?></span>
            </td>
            <td width="8%" class="text-center">
                <span class="cell-label">Currency</span>
                <span class="cell-value"><?= e($h['currency'] ?? 'USD') ?></span>
            </td>
            <td width="8%" class="text-center">
                <span class="cell-label">Rate Class</span>
                <span class="cell-value"><?= e($h['rate_class'] ?? 'Q') ?></span>
            </td>
            <td width="12%">
                <span class="cell-label">Commodity Item No.</span>
                <span class="cell-value"><?= e($h['commodity_item_no'] ?? '') ?></span>
            </td>
            <td width="22%">
                <span class="cell-label">Declared Value for Carriage</span>
                <span class="cell-value"><?= e($h['declared_value_carriage'] ?? 'NVD') ?></span>
            </td>
            <td width="22%">
                <span class="cell-label">Declared Value for Customs</span>
                <span class="cell-value"><?= e($h['declared_value_customs'] ?? 'AS PER INV') ?></span>
            </td>
            <td width="20%">
                <span class="cell-label">Amount of Insurance</span>
                <span class="cell-value"><?= e($h['amount_insurance'] ?? 'XXX') ?></span>
            </td>
        </tr>
    </table>

    <!-- ── ROW 5: WEIGHT & CHARGES ── -->
    <table style="margin-bottom:2px;">
        <tr>
            <td width="10%" class="text-center bg-light">
                <span class="cell-label">No. of Pieces</span>
                <span class="cell-value-lg"><?= $h['no_of_pieces'] ?></span>
            </td>
            <td width="14%" class="text-center bg-light">
                <span class="cell-label">Gross Weight</span>
                <span class="cell-value-lg">
                    <?= $h['gross_weight'] > 0 ? number_format($h['gross_weight'], 1) : '—' ?>
                    <span style="font-size:7pt;"> K</span>
                </span>
            </td>
            <td width="14%" class="text-center bg-light">
                <span class="cell-label">Volume Weight</span>
                <span class="cell-value">
                    <?= $h['volume_weight'] > 0 ? number_format($h['volume_weight'], 2) : '—' ?>
                    <span style="font-size:7pt;"> K</span>
                </span>
            </td>
            <td width="14%" class="text-center bg-light" style="border:2px solid #000;">
                <span class="cell-label">Chargeable Weight</span>
                <span class="cell-value-lg" style="color:#1a56db;">
                    <?= $h['chargeable_weight'] > 0 ? number_format($h['chargeable_weight'], 1) : '—' ?>
                    <span style="font-size:7pt;"> K</span>
                </span>
            </td>
            <td width="12%" class="text-center">
                <span class="cell-label">Rate / Charge</span>
                <span class="cell-value"></span>
            </td>
            <td width="12%" class="text-center">
                <span class="cell-label">Total Charge</span>
                <span class="cell-value"></span>
            </td>
            <td width="24%">
                <span class="cell-label">Charges at Destination</span>
                <span class="cell-value"></span>
            </td>
        </tr>
    </table>

    <!-- ── ROW 6: HANDLING / NOTIFY ── -->
    <table style="margin-bottom:2px;">
        <tr>
            <td>
                <span class="cell-label">Handling Information / Notify Party</span>
                <span class="cell-value">
                    <?php if ($h['notify_party']): ?>
                    <?= nl2br(e($h['notify_party'])) ?>
                    <?php endif; ?>
                    <?php if ($h['handling_info']): ?>
                    <br><?= nl2br(e($h['handling_info'])) ?>
                    <?php endif; ?>
                </span>
            </td>
        </tr>
    </table>

    <!-- ── ROW 7: COMMODITY DESCRIPTION ── -->
    <table style="margin-bottom:2px;">
        <tr>
            <td>
                <span class="cell-label">Nature and Quantity of Goods (incl. Dimensions or Volume)</span>
                <div style="margin-top:2px; line-height:1.6;">
                    <?php foreach ($commodityLines as $line): ?>
                    <span class="cell-value"><?= e($line) ?></span><br>
                    <?php endforeach; ?>
                    <?php if (!$commodityLines): ?>
                    <span class="cell-value">—</span>
                    <?php endif; ?>
                </div>
            </td>
        </tr>
    </table>

    <!-- ── ROW 8: PIECES BREAKDOWN (if weighed) ── -->
    <?php if ($pieces): ?>
    <table class="pieces-table" style="margin-bottom:2px;">
        <thead>
            <tr>
                <th>Piece#</th>
                <th>GW (kg)</th>
                <th>L (cm)</th>
                <th>W (cm)</th>
                <th>H (cm)</th>
                <th>VW (kg)</th>
                <th>L×W×H / 6000</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($pieces as $p): ?>
        <tr>
            <td><?= $p['piece_no'] ?></td>
            <td><?= number_format($p['gross_weight'], 2) ?></td>
            <td><?= $p['length'] > 0 ? number_format($p['length'], 1) : '' ?></td>
            <td><?= $p['width']  > 0 ? number_format($p['width'],  1) : '' ?></td>
            <td><?= $p['height'] > 0 ? number_format($p['height'], 1) : '' ?></td>
            <td><?= $p['volume_weight'] > 0 ? number_format($p['volume_weight'], 2) : '' ?></td>
            <td style="font-size:6pt;color:#555;">
                <?php if ($p['length'] > 0): ?>
                <?= number_format($p['length'],1) ?>×<?= number_format($p['width'],1) ?>×<?= number_format($p['height'],1) ?>/6000
                <?php endif; ?>
            </td>
        </tr>
        <?php endforeach; ?>
        <tr style="background:#f5f5f5;font-weight:bold;">
            <td>TOTAL</td>
            <td><?= number_format(array_sum(array_column($pieces,'gross_weight')), 2) ?></td>
            <td colspan="3"></td>
            <td><?= number_format(array_sum(array_column($pieces,'volume_weight')), 2) ?></td>
            <td></td>
        </tr>
        </tbody>
    </table>
    <?php endif; ?>

    <!-- ── ROW 9: SIGNATURE ── -->
    <table>
        <tr>
            <td width="50%">
                <span class="cell-label">
                    Signature of Shipper or his Agent
                </span>
                <div class="sig-box">
                    <div style="margin-top:8mm;">
                        <?= e(COMPANY_NAME) ?> — <?= date('d-M-Y') ?>
                    </div>
                </div>
            </td>
            <td width="25%" class="text-center">
                <span class="cell-label">Executed on (Date)</span>
                <div class="sig-box">
                    <div style="margin-top:8mm; text-align:center;">
                        <?= date('d-M-Y') ?>
                    </div>
                </div>
            </td>
            <td width="25%" class="text-center">
                <span class="cell-label">at (Place)</span>
                <div class="sig-box">
                    <div style="margin-top:8mm; text-align:center;">
                        <?= e($h['origin_code']) ?>
                    </div>
                </div>
            </td>
        </tr>
    </table>

    <!-- Footer -->
    <div style="margin-top:3px; font-size:5.5pt; color:#888; text-align:center; border-top:1px solid #ccc; padding-top:2px;">
        <?= COMPANY_NAME ?> · <?= COMPANY_ADDRESS ?? '' ?> · Tel: <?= COMPANY_TEL ?? '' ?>
        · Printed: <?= date('d-M-Y H:i') ?>
    </div>

</div><!-- /hawb-page -->

<script>
// Auto print if ?print=1
if (new URLSearchParams(location.search).get('print') === '1') {
    window.addEventListener('load', () => window.print());
}
</script>
</body>
</html>