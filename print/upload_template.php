<?php
/**
 * Upload HAWB Excel Template
 * URL: print/upload_template.php
 */
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
requireRole([ROLE_ADMIN, ROLE_MANAGER]);

$db         = getDB();
$targetDir  = __DIR__ . '/../assets/templates/';
$targetFile = $targetDir . 'hawb_template.xlsx';
$mapFile    = __DIR__ . '/../config/hawb_excel_map.php';
$map        = require $mapFile;

$msg = null;

// ── UPLOAD ────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['template_file'])) {
    $file = $_FILES['template_file'];
    $ext  = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

    if ($file['error'] !== UPLOAD_ERR_OK) {
        $msg = ['type' => 'danger', 'text' => 'Upload error: code ' . $file['error']];
    } elseif (!in_array($ext, ['xlsx', 'xls'])) {
        $msg = ['type' => 'danger', 'text' => 'Only .xlsx or .xls files are accepted.'];
    } elseif ($file['size'] > 5 * 1024 * 1024) {
        $msg = ['type' => 'danger', 'text' => 'File too large (max 5MB).'];
    } else {
        if (!is_dir($targetDir)) mkdir($targetDir, 0755, true);
        // Backup old template
        if (file_exists($targetFile)) {
            rename($targetFile, $targetDir . 'hawb_template_backup_' . date('YmdHis') . '.xlsx');
        }
        if (move_uploaded_file($file['tmp_name'], $targetFile)) {
            $msg = ['type' => 'success', 'text' => 'Template uploaded successfully! File: <strong>hawb_template.xlsx</strong>'];
        } else {
            $msg = ['type' => 'danger', 'text' => 'Cannot save file. Check folder permissions: <code>' . $targetDir . '</code>'];
        }
    }
}

// ── UPDATE CELL MAP ───────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_map'])) {
    $newMap = $_POST['cell_map'] ?? [];

    // Build PHP file content
    $lines = ["<?php\n"];
    $lines[] = "define('HAWB_TEMPLATE_FILE', __DIR__ . '/../assets/templates/hawb_template.xlsx');\n\n";
    $lines[] = "return [\n\n";

    // Group by section
    $sections = [
        'SHIPPER'    => ['shipper_name','shipper_address','shipper_city','shipper_phone'],
        'CONSIGNEE'  => ['consignee_name','consignee_address','consignee_city','consignee_phone','consignee_acct','consignee_usci'],
        'CARRIER'    => ['issuing_carrier','agent_name','agent_iata'],
        'HAWB/MAWB'  => ['hawb_no','mawb_no'],
        'ROUTING'    => ['airport_departure','airport_dest','routing_to1','routing_by1','routing_to2','routing_by2','flight_no','flight_date'],
        'PAYMENT'    => ['payment_term','currency','rate_class','commodity_item_no'],
        'DECLARED'   => ['declared_carriage','declared_customs','amount_insurance','accounting_info'],
        'WEIGHT'     => ['no_of_pieces','gross_weight','gross_weight_unit','rate_charge','total_charge','volume_weight','chargeable_weight'],
        'COMMODITY'  => ['commodity_line1','commodity_line2','commodity_line3','commodity_line4','commodity_line5'],
        'HANDLING'   => ['handling_info'],
        'SIGNATURE'  => ['signature_origin','execution_date','execution_place'],
    ];

    foreach ($sections as $secName => $fields) {
        $lines[] = "    // " . str_pad("── $secName ", 44, '─') . "\n";
        foreach ($fields as $field) {
            $cell = strtoupper(trim($newMap[$field] ?? ($map[$field] ?? '')));
            $lines[] = "    '" . str_pad($field, 22) . "' => '" . $cell . "',\n";
        }
        $lines[] = "\n";
    }
    $lines[] = "];\n";

    if (file_put_contents($mapFile, implode('', $lines))) {
        $msg = ['type' => 'success', 'text' => 'Cell map saved successfully! Changes take effect immediately.'];
        $map = require $mapFile; // reload
    } else {
        $msg = ['type' => 'danger', 'text' => 'Cannot write config file. Check permissions.'];
    }
}

// Field labels
$fieldLabels = [
    'shipper_name'      => 'Shipper Name',
    'shipper_address'   => 'Shipper Address',
    'shipper_city'      => 'Shipper City/Country',
    'shipper_phone'     => 'Shipper Phone',
    'consignee_name'    => 'Consignee Name',
    'consignee_address' => 'Consignee Address',
    'consignee_city'    => 'Consignee City/Country',
    'consignee_phone'   => 'Consignee Phone',
    'consignee_acct'    => 'Consignee Account No',
    'consignee_usci'    => 'Consignee USCI No',
    'issuing_carrier'   => 'Issuing Carrier Name',
    'agent_name'        => 'Agent Name (NYH)',
    'agent_iata'        => 'Agent IATA Code',
    'hawb_no'           => 'HAWB Number',
    'mawb_no'           => 'MAWB Number (Ref)',
    'airport_departure' => 'Airport of Departure',
    'airport_dest'      => 'Airport of Destination',
    'routing_to1'       => 'Routing: To 1',
    'routing_by1'       => 'Routing: By (Carrier 1)',
    'routing_to2'       => 'Routing: To 2',
    'routing_by2'       => 'Routing: By (Carrier 2)',
    'flight_no'         => 'Flight Number',
    'flight_date'       => 'Flight Date',
    'payment_term'      => 'Payment Term (PP/CC)',
    'currency'          => 'Currency',
    'rate_class'        => 'Rate Class',
    'commodity_item_no' => 'Commodity Item No.',
    'declared_carriage' => 'Declared Value (Carriage)',
    'declared_customs'  => 'Declared Value (Customs)',
    'amount_insurance'  => 'Amount of Insurance',
    'accounting_info'   => 'Accounting Info',
    'no_of_pieces'      => 'Number of Pieces',
    'gross_weight'      => 'Gross Weight',
    'gross_weight_unit' => 'GW Unit (K/L)',
    'rate_charge'       => 'Rate / Charge',
    'total_charge'      => 'Total Charge',
    'volume_weight'     => 'Volume Weight',
    'chargeable_weight' => 'Chargeable Weight',
    'commodity_line1'   => 'Commodity Line 1',
    'commodity_line2'   => 'Commodity Line 2',
    'commodity_line3'   => 'Commodity Line 3',
    'commodity_line4'   => 'Commodity Line 4',
    'commodity_line5'   => 'Commodity Line 5',
    'handling_info'     => 'Handling / Notify Info',
    'signature_origin'  => 'Signature / Agent Name',
    'execution_date'    => 'Execution Date',
    'execution_place'   => 'Execution Place',
];

$sections = [
    ['title' => 'Shipper',    'icon' => 'bi-box-seam',   'color' => 'primary',
     'fields' => ['shipper_name','shipper_address','shipper_city','shipper_phone']],
    ['title' => 'Consignee',  'icon' => 'bi-building',   'color' => 'success',
     'fields' => ['consignee_name','consignee_address','consignee_city','consignee_phone','consignee_acct','consignee_usci']],
    ['title' => 'Carrier / Agent', 'icon' => 'bi-airplane', 'color' => 'info',
     'fields' => ['issuing_carrier','agent_name','agent_iata']],
    ['title' => 'HAWB / MAWB No', 'icon' => 'bi-card-text', 'color' => 'dark',
     'fields' => ['hawb_no','mawb_no']],
    ['title' => 'Routing & Flight', 'icon' => 'bi-geo-alt', 'color' => 'secondary',
     'fields' => ['airport_departure','airport_dest','routing_to1','routing_by1','routing_to2','routing_by2','flight_no','flight_date']],
    ['title' => 'Payment',    'icon' => 'bi-credit-card', 'color' => 'warning',
     'fields' => ['payment_term','currency','rate_class','commodity_item_no']],
    ['title' => 'Declared Values', 'icon' => 'bi-shield-check', 'color' => 'secondary',
     'fields' => ['declared_carriage','declared_customs','amount_insurance','accounting_info']],
    ['title' => 'Weight & Charges', 'icon' => 'bi-speedometer2', 'color' => 'success',
     'fields' => ['no_of_pieces','gross_weight','gross_weight_unit','rate_charge','total_charge','volume_weight','chargeable_weight']],
    ['title' => 'Commodity Description', 'icon' => 'bi-list-ul', 'color' => 'primary',
     'fields' => ['commodity_line1','commodity_line2','commodity_line3','commodity_line4','commodity_line5']],
    ['title' => 'Handling / Notify', 'icon' => 'bi-info-circle', 'color' => 'info',
     'fields' => ['handling_info']],
    ['title' => 'Signature & Date', 'icon' => 'bi-pen', 'color' => 'dark',
     'fields' => ['signature_origin','execution_date','execution_place']],
];

$templateExists = file_exists($targetFile);
$pageTitle = 'HAWB Excel Template';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
    <title><?= $pageTitle ?> | <?= APP_NAME ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="<?= BASE_URL ?>assets/css/app.css" rel="stylesheet">
    <style>
        .cell-input {
            width: 80px; font-family: monospace;
            text-transform: uppercase; font-weight: 700;
            text-align: center; font-size: .9rem;
        }
        .field-row { padding: .4rem .5rem; border-radius: 6px; transition: background .1s; }
        .field-row:hover { background: #f0f4ff; }
        .field-name { font-size: .78rem; color: #495057; }
        .section-card .card-header { padding: .6rem 1rem; }
        .template-status {
            border-radius: 12px; padding: 1rem 1.25rem;
        }
    </style>
</head>
<body style="background:#f0f2f5;">
<?php require_once __DIR__ . '/../includes/navbar.php'; ?>
<div class="d-flex" style="margin-top:56px;min-height:calc(100vh - 56px);">
<?php require_once __DIR__ . '/../includes/sidebar.php'; ?>

<main class="flex-grow-1 p-4">

<!-- Flash -->
<?php if ($msg): ?>
<div class="alert alert-<?= $msg['type'] ?> alert-dismissible fade show">
    <i class="bi bi-<?= $msg['type']==='success'?'check-circle':'exclamation-triangle' ?> me-2"></i>
    <?= $msg['text'] ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<!-- Header -->
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="fw-bold mb-0">
            <i class="bi bi-file-earmark-excel text-success me-2"></i>HAWB Excel Template Manager
        </h4>
        <small class="text-muted">Upload your HAWB Excel template and configure cell positions</small>
    </div>
    <?php if ($templateExists): ?>
    <a href="hawb_excel.php?id=<?= (int)($_GET['test_id'] ?? 1) ?>"
       class="btn btn-outline-success btn-sm">
        <i class="bi bi-download me-1"></i>Test Export HAWB #<?= (int)($_GET['test_id'] ?? 1) ?>
    </a>
    <?php endif; ?>
</div>

<!-- Template status -->
<div class="row g-3 mb-4">
    <div class="col-md-6">
        <div class="card template-status <?= $templateExists ? 'border-success' : 'border-warning' ?>">
            <div class="card-body d-flex align-items-center gap-3">
                <i class="bi bi-<?= $templateExists ? 'file-earmark-check text-success' : 'file-earmark-x text-warning' ?> fs-2"></i>
                <div>
                    <div class="fw-bold">
                        <?= $templateExists ? 'Template Uploaded ✅' : 'No Template Yet ⚠️' ?>
                    </div>
                    <small class="text-muted">
                        <?php if ($templateExists): ?>
                        <code>assets/templates/hawb_template.xlsx</code>
                        · Modified: <?= date('d-M-Y H:i', filemtime($targetFile)) ?>
                        · <?= number_format(filesize($targetFile)/1024, 1) ?> KB
                        <?php else: ?>
                        Upload your HAWB .xlsx template below
                        <?php endif; ?>
                    </small>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="card">
            <div class="card-body">
                <div class="fw-bold mb-2">
                    <i class="bi bi-upload text-primary me-2"></i>Upload New Template
                </div>
                <form method="POST" enctype="multipart/form-data"
                      class="d-flex gap-2 align-items-center flex-wrap">
                    <input type="file" name="template_file" class="form-control form-control-sm"
                           accept=".xlsx,.xls" required style="max-width:280px;">
                    <button type="submit" class="btn btn-success btn-sm">
                        <i class="bi bi-cloud-upload me-1"></i>Upload
                    </button>
                    <?php if ($templateExists): ?>
                    <a href="<?= BASE_URL ?>assets/templates/hawb_template.xlsx"
                       class="btn btn-outline-secondary btn-sm">
                        <i class="bi bi-download me-1"></i>Download Current
                    </a>
                    <?php endif; ?>
                </form>
                <small class="text-muted d-block mt-1">
                    Accepted: .xlsx / .xls · Max: 5MB
                </small>
            </div>
        </div>
    </div>
</div>

<!-- HOW TO -->
<div class="alert alert-info d-flex gap-3 mb-4">
    <i class="bi bi-lightbulb-fill fs-4 flex-shrink-0"></i>
    <div>
        <strong>How to configure:</strong>
        <ol class="mb-0 mt-1 small">
            <li>Open your HAWB Excel template in Excel/LibreOffice</li>
            <li>Click on each cell you want to fill → note the cell address (e.g. <code>B3</code>, <code>H14</code>)</li>
            <li>Enter those addresses in the table below</li>
            <li>Click <strong>Save Cell Map</strong></li>
            <li>Upload the template file above — system will write data into the exact cells</li>
        </ol>
    </div>
</div>

<!-- CELL MAP FORM -->
<form method="POST">
    <input type="hidden" name="save_map" value="1">

    <div class="d-flex justify-content-between align-items-center mb-3">
        <h5 class="fw-bold mb-0">
            <i class="bi bi-grid-3x3 text-primary me-2"></i>Cell Position Map
        </h5>
        <button type="submit" class="btn btn-primary">
            <i class="bi bi-floppy me-1"></i>Save Cell Map
        </button>
    </div>

    <div class="row g-3">
    <?php foreach ($sections as $sec): ?>
    <div class="col-md-6 col-xl-4">
        <div class="card section-card h-100">
            <div class="card-header d-flex align-items-center gap-2 py-2">
                <i class="bi <?= $sec['icon'] ?> text-<?= $sec['color'] ?>"></i>
                <span class="fw-bold small"><?= $sec['title'] ?></span>
            </div>
            <div class="card-body py-2">
                <?php foreach ($sec['fields'] as $field): ?>
                <div class="field-row d-flex align-items-center justify-content-between gap-2">
                    <div class="field-name flex-grow-1"><?= $fieldLabels[$field] ?? $field ?></div>
                    <div class="d-flex align-items-center gap-1">
                        <input type="text"
                               name="cell_map[<?= $field ?>]"
                               class="form-control cell-input"
                               value="<?= e($map[$field] ?? '') ?>"
                               placeholder="e.g. B3"
                               maxlength="6">
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
    </div>

    <div class="d-flex justify-content-end mt-3 mb-5">
        <button type="submit" class="btn btn-primary px-5">
            <i class="bi bi-floppy me-1"></i>Save Cell Map
        </button>
    </div>
</form>

<!-- VISUAL MAP TABLE (read-only reference) -->
<div class="card mb-4">
    <div class="card-header fw-bold py-2">
        <i class="bi bi-table me-2 text-secondary"></i>Current Cell Map Reference
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-sm table-hover mb-0">
                <thead class="table-dark">
                    <tr>
                        <th class="ps-3">#</th>
                        <th>Field</th>
                        <th>Description</th>
                        <th class="text-center">Cell Address</th>
                        <th>Sample Value</th>
                    </tr>
                </thead>
                <tbody>
                <?php
                $sampleData = [
                    'shipper_name'      => 'SAMSUNG ELECTRO-MECHANICS CO.,LTD',
                    'shipper_address'   => '150, MAEYEONG-RO, SUWON-SI, KOREA',
                    'consignee_name'    => 'JIANGXI JINGHAO OPTICAL CO., LTD',
                    'hawb_no'           => 'NYH2605001A',
                    'mawb_no'           => '018-92594106',
                    'airport_departure' => 'HAN',
                    'airport_dest'      => 'PVG',
                    'flight_no'         => 'HO1330',
                    'flight_date'       => '21-May-26',
                    'no_of_pieces'      => '1',
                    'gross_weight'      => '5.8',
                    'chargeable_weight' => '6.0',
                    'payment_term'      => 'PP',
                    'declared_carriage' => 'NVD',
                    'declared_customs'  => 'AS PER INV',
                    'commodity_line1'   => '1PKG OF AUTO-FOCUSING COMPONENTS (SC1C87)',
                ];
                $i = 1;
                foreach ($map as $field => $cell): ?>
                <tr>
                    <td class="ps-3 text-muted"><?= $i++ ?></td>
                    <td><code class="small"><?= e($field) ?></code></td>
                    <td class="small text-muted"><?= e($fieldLabels[$field] ?? $field) ?></td>
                    <td class="text-center">
                        <?php if ($cell): ?>
                        <span class="badge bg-primary" style="font-family:monospace;font-size:.85rem;">
                            <?= e($cell) ?>
                        </span>
                        <?php else: ?>
                        <span class="badge bg-secondary">—</span>
                        <?php endif; ?>
                    </td>
                    <td class="small text-muted fst-italic">
                        <?= e($sampleData[$field] ?? '') ?>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

</main>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Auto uppercase cell inputs
document.querySelectorAll('.cell-input').forEach(el => {
    el.addEventListener('input', function() {
        this.value = this.value.toUpperCase();
    });
});

// Highlight row when input is focused
document.querySelectorAll('.cell-input').forEach(el => {
    el.addEventListener('focus', function() {
        this.closest('.field-row')?.classList.add('bg-primary', 'bg-opacity-10');
    });
    el.addEventListener('blur', function() {
        this.closest('.field-row')?.classList.remove('bg-primary', 'bg-opacity-10');
    });
});

setTimeout(() => {
    document.querySelectorAll('.alert-dismissible').forEach(el =>
        bootstrap.Alert.getOrCreateInstance(el).close());
}, 5000);
</script>
</body>
</html>