<?php
/**
 * Zebra Label Print — 80×110mm
 * URL: print/label_print.php?manifest_id=ID
 *   or print/label_print.php?hawb_id=ID  (single label)
 */
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
requireLogin();

$db = getDB();

// Load HAWBs
$hawbs = [];
if (!empty($_GET['manifest_id'])) {
    $mid   = (int)$_GET['manifest_id'];
    $res   = $db->query("
        SELECT h.*,
               s.name AS shipper_name,   s.address AS shipper_address,
               s.phone AS shipper_phone,
               cn.name AS cnee_name,     cn.address AS cnee_address,
               cn.phone AS cnee_phone,   cn.usci_no AS cnee_usci,
               ap1.iata_code AS origin_code,
               ap2.iata_code AS dest_code,
               m.mawb_no, m.flight_no, m.flight_date,
               al.code AS airline_code
        FROM hawbs h
        LEFT JOIN shippers   s   ON h.shipper_id    = s.id
        LEFT JOIN consignees cn  ON h.consignee_id  = cn.id
        LEFT JOIN airports   ap1 ON h.origin_id     = ap1.id
        LEFT JOIN airports   ap2 ON h.destination_id= ap2.id
        LEFT JOIN manifests  m   ON h.manifest_id   = m.id
        LEFT JOIN airlines   al  ON m.airline_id    = al.id
        WHERE h.manifest_id = $mid
        ORDER BY h.seq_number ASC
    ");
    $hawbs = $res->fetch_all(MYSQLI_ASSOC);
} elseif (!empty($_GET['hawb_id'])) {
    $hid = (int)$_GET['hawb_id'];
    $res = $db->query("
        SELECT h.*,
               s.name AS shipper_name,   s.address AS shipper_address,
               s.phone AS shipper_phone,
               cn.name AS cnee_name,     cn.address AS cnee_address,
               cn.phone AS cnee_phone,   cn.usci_no AS cnee_usci,
               ap1.iata_code AS origin_code,
               ap2.iata_code AS dest_code,
               m.mawb_no, m.flight_no, m.flight_date,
               al.code AS airline_code
        FROM hawbs h
        LEFT JOIN shippers   s   ON h.shipper_id    = s.id
        LEFT JOIN consignees cn  ON h.consignee_id  = cn.id
        LEFT JOIN airports   ap1 ON h.origin_id     = ap1.id
        LEFT JOIN airports   ap2 ON h.destination_id= ap2.id
        LEFT JOIN manifests  m   ON h.manifest_id   = m.id
        LEFT JOIN airlines   al  ON m.airline_id    = al.id
        WHERE h.id = $hid
    ");
    $hawbs = $res->fetch_all(MYSQLI_ASSOC);
}

if (!$hawbs) die('No HAWBs found.');

// Count total labels to print (one per piece)
$totalLabels = 0;
foreach ($hawbs as $hw) $totalLabels += max(1, (int)$hw['no_of_pieces']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Labels — <?= count($hawbs) ?> HAWBs · <?= $totalLabels ?> labels</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: Arial, sans-serif;
            background: #f0f0f0;
        }

        /* ── Print: Zebra 80×110mm ── */
        @page {
            size: 80mm 110mm;
            margin: 2mm;
        }
        @media print {
            body { background: #fff; }
            .no-print { display: none !important; }
            .label-wrapper { padding: 0; gap: 0; }
            .label {
                width: 76mm !important;
                height: 106mm !important;
                page-break-after: always;
                page-break-inside: avoid;
                border: none !important;
                box-shadow: none !important;
                margin: 0 !important;
            }
            .label:last-child { page-break-after: auto; }
        }

        /* ── Screen preview ── */
        .print-bar {
            background: #6f42c1; color: #fff;
            padding: 10px 16px;
            display: flex; align-items: center; gap: 12px;
            position: sticky; top: 0; z-index: 100;
        }
        .print-bar button {
            background: #fff; color: #6f42c1;
            border: none; border-radius: 6px;
            padding: 6px 18px; font-weight: 700; cursor: pointer;
        }
        .print-bar span { font-size: .85rem; }
        .print-bar a { color: rgba(255,255,255,.8); font-size: .85rem; text-decoration: none; }

        .label-wrapper {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            padding: 16px;
            justify-content: flex-start;
        }

        /* ── LABEL DESIGN 80×110mm ── */
        .label {
            width: 80mm;
            height: 110mm;
            background: #fff;
            border: 1px solid #ccc;
            border-radius: 3mm;
            padding: 2.5mm;
            display: flex;
            flex-direction: column;
            box-shadow: 0 2px 8px rgba(0,0,0,.15);
            overflow: hidden;
            position: relative;
        }

        /* Top stripe */
        .label-stripe {
            background: #1a1d23;
            color: #fff;
            padding: 1.5mm 2mm;
            border-radius: 1.5mm;
            margin-bottom: 1.5mm;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .label-company {
            font-size: 7pt;
            font-weight: 700;
            letter-spacing: .5px;
        }
        .label-route {
            font-size: 8pt;
            font-weight: 900;
            letter-spacing: 1px;
        }

        /* HAWB Number — prominent */
        .label-hawbno {
            text-align: center;
            font-size: 12pt;
            font-weight: 900;
            letter-spacing: 1.5px;
            border: 1.5px solid #000;
            border-radius: 2mm;
            padding: 1mm 0;
            margin-bottom: 1.5mm;
            background: #f8f9ff;
        }

        /* Piece counter */
        .label-piece {
            position: absolute;
            top: 3.5mm;
            right: 3mm;
            background: #dc3545;
            color: #fff;
            border-radius: 50%;
            width: 9mm; height: 9mm;
            display: flex; flex-direction: column;
            align-items: center; justify-content: center;
            font-size: 5.5pt; font-weight: 900;
            line-height: 1;
        }
        .label-piece .pn { font-size: 8pt; }

        /* Shipper / Consignee blocks */
        .label-section {
            font-size: 6pt;
            line-height: 1.35;
            border: .5px solid #ddd;
            border-radius: 1.5mm;
            padding: 1mm 1.5mm;
            margin-bottom: 1mm;
            flex-shrink: 0;
        }
        .label-section-title {
            font-size: 5pt;
            font-weight: 700;
            text-transform: uppercase;
            color: #888;
            letter-spacing: .5px;
            margin-bottom: .5mm;
        }
        .label-section-name {
            font-size: 7pt;
            font-weight: 700;
        }

        /* Flight info */
        .label-flight {
            display: flex;
            gap: 2mm;
            margin-bottom: 1mm;
        }
        .label-flight-item {
            flex: 1;
            border: .5px solid #ddd;
            border-radius: 1.5mm;
            padding: 1mm 1.5mm;
            text-align: center;
        }
        .label-flight-item .lfi-label { font-size: 5pt; color: #888; text-transform: uppercase; }
        .label-flight-item .lfi-value { font-size: 7.5pt; font-weight: 900; }

        /* Weight row */
        .label-weights {
            display: flex;
            gap: 1.5mm;
            margin-bottom: 1mm;
        }
        .label-weight-item {
            flex: 1;
            background: #f0f4ff;
            border-radius: 1.5mm;
            padding: 1mm;
            text-align: center;
        }
        .label-weight-item.cw {
            background: #e8f5e9;
            border: 1px solid #a5d6a7;
        }
        .label-weight-item .lwi-label { font-size: 5pt; color: #888; }
        .label-weight-item .lwi-value { font-size: 8pt; font-weight: 900; }

        /* Commodity */
        .label-commodity {
            font-size: 5.5pt;
            line-height: 1.3;
            color: #444;
            border-top: .5px dashed #ccc;
            padding-top: 1mm;
            margin-top: auto;
            overflow: hidden;
        }

        /* MAWB ref + term */
        .label-footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: auto;
            padding-top: 1mm;
            border-top: .5px solid #ddd;
        }
        .label-mawb { font-size: 5.5pt; color: #666; }
        .label-term {
            font-size: 8pt; font-weight: 900;
            padding: .5mm 2mm;
            border-radius: 2mm;
        }
        .term-pp { background: #dbeafe; color: #1d4ed8; }
        .term-cc { background: #fee2e2; color: #dc2626; }
    </style>
</head>
<body>

<!-- Print bar -->
<div class="print-bar no-print">
    <button onclick="window.print()">🖨 Print <?= $totalLabels ?> Labels (80×110mm Zebra)</button>
    <span>📦 <?= count($hawbs) ?> HAWBs · <?= $totalLabels ?> labels total</span>
    <?php if (!empty($_GET['manifest_id'])): ?>
    <a href="../operations/manifest/edit.php?id=<?= (int)$_GET['manifest_id'] ?>" style="margin-left:auto;">
        ← Back to Manifest
    </a>
    <?php endif; ?>
</div>

<div class="label-wrapper">
<?php foreach ($hawbs as $hw):
    $numPieces = max(1, (int)$hw['no_of_pieces']);
    // One label per piece
    for ($p = 1; $p <= $numPieces; $p++):
        $commodityShort = substr($hw['commodity'] ?? '', 0, 80);
?>
<div class="label">

    <!-- Top stripe: Company + Route -->
    <div class="label-stripe">
        <span class="label-company"><?= strtoupper(COMPANY_SHORT_NAME ?? APP_NAME) ?></span>
        <span class="label-route">
            <?= e($hw['origin_code']) ?> → <?= e($hw['dest_code']) ?>
        </span>
    </div>

    <!-- Piece counter badge -->
    <div class="label-piece">
        <span class="pn"><?= $p ?></span>
        <span>/<?= $numPieces ?></span>
    </div>

    <!-- HAWB Number -->
    <div class="label-hawbno"><?= e($hw['hawb_no']) ?></div>

    <!-- Shipper -->
    <div class="label-section">
        <div class="label-section-title">From (Shipper)</div>
        <div class="label-section-name"><?= e(strtoupper(substr($hw['shipper_name'] ?? '—', 0, 35))) ?></div>
        <?php if ($hw['shipper_address']): ?>
        <div><?= e(substr($hw['shipper_address'], 0, 50)) ?></div>
        <?php endif; ?>
        <?php if ($hw['shipper_phone']): ?>
        <div>Tel: <?= e($hw['shipper_phone']) ?></div>
        <?php endif; ?>
    </div>

    <!-- Consignee -->
    <div class="label-section">
        <div class="label-section-title">To (Consignee)</div>
        <div class="label-section-name"><?= e(strtoupper(substr($hw['cnee_name'] ?? '—', 0, 35))) ?></div>
        <?php if ($hw['cnee_address']): ?>
        <div><?= e(substr($hw['cnee_address'], 0, 55)) ?></div>
        <?php endif; ?>
        <?php if ($hw['cnee_phone']): ?>
        <div>Tel: <?= e($hw['cnee_phone']) ?></div>
        <?php endif; ?>
        <?php if ($hw['cnee_usci']): ?>
        <div>USCI: <?= e($hw['cnee_usci']) ?></div>
        <?php endif; ?>
    </div>

    <!-- Flight info -->
    <div class="label-flight">
        <div class="label-flight-item">
            <div class="lfi-label">Airline</div>
            <div class="lfi-value"><?= e($hw['airline_code']) ?></div>
        </div>
        <div class="label-flight-item">
            <div class="lfi-label">Flight</div>
            <div class="lfi-value"><?= e($hw['flight_no']) ?></div>
        </div>
        <div class="label-flight-item">
            <div class="lfi-label">Date</div>
            <div class="lfi-value">
                <?= $hw['flight_date'] ? date('d-M-y', strtotime($hw['flight_date'])) : '—' ?>
            </div>
        </div>
    </div>

    <!-- Weights (only if weighed) -->
    <?php if ($hw['is_weighed'] && $hw['gross_weight'] > 0): ?>
    <div class="label-weights">
        <div class="label-weight-item">
            <div class="lwi-label">GW (kg)</div>
            <div class="lwi-value"><?= number_format($hw['gross_weight'], 1) ?></div>
        </div>
        <?php if ($hw['volume_weight'] > 0): ?>
        <div class="label-weight-item">
            <div class="lwi-label">VW (kg)</div>
            <div class="lwi-value"><?= number_format($hw['volume_weight'], 2) ?></div>
        </div>
        <?php endif; ?>
        <div class="label-weight-item cw">
            <div class="lwi-label">CW (kg)</div>
            <div class="lwi-value"><?= number_format($hw['chargeable_weight'], 1) ?></div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Commodity -->
    <?php if ($commodityShort): ?>
    <div class="label-commodity">
        <?= nl2br(e($commodityShort)) ?>
    </div>
    <?php endif; ?>

    <!-- Footer: MAWB + Payment Term -->
    <div class="label-footer">
        <div class="label-mawb">
            MAWB: <?= e($hw['mawb_no']) ?>
        </div>
        <span class="label-term <?= $hw['payment_term']==='PP'?'term-pp':'term-cc' ?>">
            <?= e($hw['payment_term']) ?>
        </span>
    </div>

</div><!-- /label -->
<?php endfor; ?>
<?php endforeach; ?>
</div><!-- /label-wrapper -->

<script>
if (new URLSearchParams(location.search).get('print') === '1') {
    window.addEventListener('load', () => window.print());
}
</script>
</body>
</html>