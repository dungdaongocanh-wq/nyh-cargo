<?php
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
requireRole([ROLE_ADMIN, ROLE_MANAGER]);

// ── Thư mục lưu template ──────────────────────────────────
$templateDir = __DIR__ . '/../assets/weight_slip_templates/';
if (!is_dir($templateDir)) mkdir($templateDir, 0755, true);

$warehouses = ['ALSC', 'NCTS', 'ACSV'];

// Các field có thể map vào cell Excel
$fields = [
    'namyang_name'      => 'Namyang — Tên công ty',
    'namyang_address'   => 'Namyang — Địa chỉ',
    'namyang_tel'       => 'Namyang — Tel/Fax/MST',
    'customer_name'     => 'Customer — Tên (MAWB)',
    'customer_address'  => 'Customer — Địa chỉ',
    'customer_phone'    => 'Customer — Tel/Fax',
    'mawb_no'           => 'MAWB No',
    'flight_no'         => 'Flight No',
    'flight_date'       => 'Flight Date',
    'origin'            => 'Origin (IATA)',
    'dest'              => 'Destination (IATA)',
    'route'             => 'Route (Origin — Dest)',
    'commodity'         => 'Commodity / Description',
];

$msg   = '';
$mtype = 'success';

// ── UPLOAD template ───────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['upload_wh'])) {
    $wh = $_POST['upload_wh'];
    if (in_array($wh, $warehouses) && isset($_FILES['template_file']) && $_FILES['template_file']['error'] === 0) {
        $ext = strtolower(pathinfo($_FILES['template_file']['name'], PATHINFO_EXTENSION));
        if ($ext === 'xlsx') {
            move_uploaded_file($_FILES['template_file']['tmp_name'], $templateDir . $wh . '_template.xlsx');
            $msg = "Template cho kho <strong>$wh</strong> đã được tải lên.";
        } else {
            $msg = 'Chỉ chấp nhận file .xlsx'; $mtype = 'danger';
        }
    } else {
        $msg = 'Upload thất bại.'; $mtype = 'danger';
    }
}

// ── LƯU mapping ───────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_map_wh'])) {
    $wh  = $_POST['save_map_wh'];
    $map = $_POST['map'] ?? [];
    if (in_array($wh, $warehouses)) {
        file_put_contents($templateDir . $wh . '_map.json', json_encode($map, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        $msg = "Mapping cho kho <strong>$wh</strong> đã được lưu.";
    }
}

// ── XÓA template ──────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_wh'])) {
    $wh = $_POST['delete_wh'];
    if (in_array($wh, $warehouses)) {
        @unlink($templateDir . $wh . '_template.xlsx');
        @unlink($templateDir . $wh . '_map.json');
        $msg = "Đã xóa template kho <strong>$wh</strong>."; $mtype = 'warning';
    }
}

// ── Load mapping hiện tại ─────────────────────────────────
$maps = [];
foreach ($warehouses as $wh) {
    $mapFile    = $templateDir . $wh . '_map.json';
    $maps[$wh]  = file_exists($mapFile) ? json_decode(file_get_contents($mapFile), true) : [];
}

$activeTab = $_GET['tab'] ?? $warehouses[0];
if (!in_array($activeTab, $warehouses)) $activeTab = $warehouses[0];

$pageTitle = 'Weight Slip Templates';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title><?= $pageTitle ?> | <?= APP_NAME ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="<?= BASE_URL ?>assets/css/app.css" rel="stylesheet">
    <style>
        .cell-input {
            width: 90px; font-size: .82rem; text-align: center;
            font-weight: 700; text-transform: uppercase; letter-spacing: .05em;
        }
        .field-key  { font-size: .72rem; color: #9ca3af; }
        .tpl-ok     { background:#d1fae5; color:#065f46; border:1px solid #6ee7b7;
                      border-radius:20px; padding:2px 10px; font-size:.75rem; font-weight:700; }
        .tpl-none   { background:#fee2e2; color:#991b1b; border:1px solid #fca5a5;
                      border-radius:20px; padding:2px 10px; font-size:.75rem; font-weight:700; }
        .nav-tabs .nav-link { font-weight: 600; }
    </style>
</head>
<body style="background:#f0f2f5;">

<?php require_once __DIR__ . '/../includes/navbar.php'; ?>
<div class="d-flex" style="margin-top:56px; min-height:calc(100vh - 56px);">
<?php require_once __DIR__ . '/../includes/sidebar.php'; ?>
<main class="flex-grow-1 p-4">

<!-- Flash -->
<?php if ($msg): ?>
<div class="alert alert-<?= $mtype ?> alert-dismissible fade show">
    <i class="bi bi-<?= $mtype==='success'?'check-circle':'exclamation-triangle' ?> me-2"></i>
    <?= $msg ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<!-- Header -->
<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h4 class="fw-bold mb-0">
            <i class="bi bi-file-earmark-spreadsheet text-success me-2"></i>Weight Slip Templates
        </h4>
        <small class="text-muted">Upload form mẫu Excel và cấu hình vị trí điền dữ liệu cho từng kho</small>
    </div>
    <a href="<?= BASE_URL ?>print/weight_slip.php?manifest_id=1" class="btn btn-outline-secondary btn-sm">
        <i class="bi bi-arrow-left me-1"></i>Quay lại
    </a>
</div>

<!-- Hướng dẫn -->
<div class="alert alert-info mb-4">
    <i class="bi bi-info-circle-fill me-2"></i>
    <strong>Cách dùng:</strong>
    <ol class="mb-0 mt-1 ps-3 small">
        <li>Upload file Excel template (.xlsx) của từng kho (ALSC / NCTS / ACSV).</li>
        <li>Điền <strong>địa chỉ ô Excel</strong> tương ứng với từng trường dữ liệu (VD: <code>C3</code>, <code>B11</code>).</li>
        <li>Nhấn <strong>Lưu Mapping</strong>. Khi xuất Excel từ Weight Slip, dữ liệu sẽ điền đúng ô đó.</li>
        <li>Nếu chưa upload template, hệ thống sẽ sinh file Excel cơ bản tự động.</li>
    </ol>
</div>

<!-- Tabs -->
<ul class="nav nav-tabs mb-0" id="whTabs" role="tablist">
    <?php foreach ($warehouses as $i => $wh):
        $hasT = file_exists($templateDir . $wh . '_template.xlsx');
        $hasM = file_exists($templateDir . $wh . '_map.json');
    ?>
    <li class="nav-item" role="presentation">
        <button class="nav-link <?= $activeTab===$wh?'active':'' ?>"
                data-bs-toggle="tab" data-bs-target="#tab_<?= $wh ?>" type="button">
            <?= $wh ?>
            &nbsp;
            <?php if ($hasT && $hasM): ?>
            <span class="tpl-ok" style="font-size:.65rem;padding:1px 7px;">✓ Ready</span>
            <?php elseif ($hasT): ?>
            <span style="background:#fef3c7;color:#92400e;border:1px solid #fcd34d;border-radius:20px;padding:1px 7px;font-size:.65rem;font-weight:700;">⚠ No Map</span>
            <?php else: ?>
            <span class="tpl-none" style="font-size:.65rem;padding:1px 7px;">✗ No File</span>
            <?php endif; ?>
        </button>
    </li>
    <?php endforeach; ?>
</ul>

<div class="tab-content border border-top-0 rounded-bottom bg-white p-4 mb-4">
<?php foreach ($warehouses as $i => $wh):
    $hasTemplate = file_exists($templateDir . $wh . '_template.xlsx');
    $curMap      = $maps[$wh] ?? [];
?>
<div class="tab-pane fade <?= $activeTab===$wh?'show active':'' ?>" id="tab_<?= $wh ?>">
    <div class="row g-4">

        <!-- ── Upload ──────────────────────────────────────── -->
        <div class="col-lg-4">
            <div class="card h-100">
                <div class="card-header py-2 fw-bold small">
                    <i class="bi bi-cloud-upload text-primary me-1"></i>File Template — <?= $wh ?>
                </div>
                <div class="card-body">

                    <!-- Status -->
                    <?php if ($hasTemplate): ?>
                    <div class="d-flex align-items-center gap-2 mb-3 p-2 rounded" style="background:#d1fae5;">
                        <i class="bi bi-check-circle-fill text-success fs-5"></i>
                        <div>
                            <div class="fw-semibold small text-success">Template đã tải lên</div>
                            <small class="text-muted">
                                <?= date('d/m/Y H:i', filemtime($templateDir.$wh.'_template.xlsx')) ?>
                                · <?= round(filesize($templateDir.$wh.'_template.xlsx')/1024, 1) ?> KB
                            </small>
                        </div>
                    </div>
                    <?php else: ?>
                    <div class="d-flex align-items-center gap-2 mb-3 p-2 rounded" style="background:#fee2e2;">
                        <i class="bi bi-x-circle-fill text-danger fs-5"></i>
                        <div class="fw-semibold small text-danger">Chưa có template</div>
                    </div>
                    <?php endif; ?>

                    <!-- Upload form -->
                    <form method="POST" enctype="multipart/form-data">
                        <input type="hidden" name="upload_wh" value="<?= $wh ?>">
                        <label class="form-label fw-semibold small">
                            <?= $hasTemplate ? 'Thay thế file .xlsx' : 'Upload file .xlsx' ?>
                        </label>
                        <input type="file" name="template_file" accept=".xlsx"
                               class="form-control form-control-sm mb-2" required>
                        <button type="submit" class="btn btn-primary btn-sm w-100">
                            <i class="bi bi-cloud-upload me-1"></i>Upload Template
                        </button>
                    </form>

                    <?php if ($hasTemplate): ?>
                    <hr class="my-3">
                    <a href="<?= BASE_URL ?>assets/weight_slip_templates/<?= $wh ?>_template.xlsx"
                       class="btn btn-outline-success btn-sm w-100 mb-2" download>
                        <i class="bi bi-download me-1"></i>Download Template hiện tại
                    </a>
                    <form method="POST" onsubmit="return confirm('Xóa toàn bộ template và mapping của <?= $wh ?>?')">
                        <input type="hidden" name="delete_wh" value="<?= $wh ?>">
                        <button type="submit" class="btn btn-outline-danger btn-sm w-100">
                            <i class="bi bi-trash me-1"></i>Xóa Template & Mapping
                        </button>
                    </form>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- ── Cell Mapping ────────────────────────────────── -->
        <div class="col-lg-8">
            <div class="card">
                <div class="card-header py-2 d-flex justify-content-between align-items-center">
                    <span class="fw-bold small">
                        <i class="bi bi-grid-3x3-gap text-warning me-1"></i>
                        Cell Mapping — <?= $wh ?>
                    </span>
                    <span class="text-muted small">Ví dụ: <code>B11</code>, <code>D3</code>, <code>F15</code></span>
                </div>
                <div class="card-body p-0">
                    <form method="POST" action="?tab=<?= $wh ?>">
                        <input type="hidden" name="save_map_wh" value="<?= $wh ?>">
                        <div class="table-responsive">
                            <table class="table table-sm table-hover align-middle mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th class="ps-3" style="width:45%">Trường dữ liệu</th>
                                        <th class="text-center" style="width:18%">Cell Excel</th>
                                        <th>Giá trị mẫu</th>
                                    </tr>
                                </thead>
                                <tbody>
                                <?php
                                $previews = [
                                    'namyang_name'    => 'NAMYANG GLOBAL CO.,LTD',
                                    'namyang_address' => 'Floor 14th, IDMC My Dinh Building...',
                                    'namyang_tel'     => 'Tel: +84-4-37946116~118  MST: 0108168022',
                                    'customer_name'   => 'EASYWAY LOGISTICS CO.,LTD',
                                    'customer_address'=> 'RM 502, BLOCK C, NO. 469...',
                                    'customer_phone'  => 'Tel: +86-21-6835 8521',
                                    'mawb_no'         => '988-13623676',
                                    'flight_no'       => 'OZ OZ734',
                                    'flight_date'     => '17-Jan',
                                    'origin'          => 'HAN',
                                    'dest'            => 'ICN',
                                    'route'           => 'HAN-ICN',
                                    'commodity'       => 'Consol',
                                ];
                                foreach ($fields as $key => $label):
                                ?>
                                <tr>
                                    <td class="ps-3">
                                        <div class="fw-semibold small"><?= htmlspecialchars($label) ?></div>
                                        <div class="field-key"><?= htmlspecialchars($key) ?></div>
                                    </td>
                                    <td class="text-center">
                                        <input type="text"
                                               name="map[<?= $key ?>]"
                                               class="form-control cell-input"
                                               value="<?= htmlspecialchars($curMap[$key] ?? '') ?>"
                                               placeholder="—"
                                               oninput="this.value=this.value.toUpperCase()">
                                    </td>
                                    <td class="text-muted small"><?= htmlspecialchars($previews[$key] ?? '') ?></td>
                                </tr>
                                <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <div class="p-3 border-top">
                            <button type="submit" class="btn btn-warning fw-bold w-100">
                                <i class="bi bi-floppy me-1"></i>Lưu Mapping — <?= $wh ?>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

    </div><!-- /row -->
</div><!-- /tab-pane -->
<?php endforeach; ?>
</div><!-- /tab-content -->

</main>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
setTimeout(() => {
    document.querySelectorAll('.alert-dismissible').forEach(el =>
        bootstrap.Alert.getOrCreateInstance(el).close());
}, 4000);
</script>
</body>
</html>
