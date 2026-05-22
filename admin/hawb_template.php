<?php
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
requireLogin();

if (!isManager()) {
    setFlash('danger', 'Access denied.');
    redirect(BASE_URL . 'index.php');
}

$templateDir  = __DIR__ . '/../assets/templates/';
$templateFile = $templateDir . 'hawb_template.xlsx';
$uploadError  = '';

// ── DOWNLOAD ─────────────────────────────────────────────
if (isset($_GET['download']) && $_GET['download'] === '1') {
    if (!file_exists($templateFile)) {
        setFlash('danger', 'Template file does not exist.');
        redirect(BASE_URL . 'admin/hawb_template.php');
    }
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="hawb_template.xlsx"');
    header('Content-Length: ' . filesize($templateFile));
    readfile($templateFile);
    exit;
}

// ── UPLOAD ───────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['template_file'])) {
    $file = $_FILES['template_file'];
    $ext  = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

    if ($file['error'] !== UPLOAD_ERR_OK) {
        $uploadError = 'Upload failed. Error code: ' . $file['error'];
    } elseif ($ext !== 'xlsx') {
        $uploadError = 'Only .xlsx files are accepted.';
    } elseif ($file['size'] > 10 * 1024 * 1024) {
        $uploadError = 'File size must be under 10MB.';
    } else {
        if (!is_dir($templateDir)) {
            mkdir($templateDir, 0755, true);
        }
        if (move_uploaded_file($file['tmp_name'], $templateFile)) {
            setFlash('success', 'HAWB Template uploaded successfully.');
            redirect(BASE_URL . 'admin/hawb_template.php');
        } else {
            $uploadError = 'Failed to save file. Check folder permissions on assets/templates/.';
        }
    }
}

// ── FILE STATUS ──────────────────────────────────────────
$fileExists   = file_exists($templateFile);
$fileSizeKb   = $fileExists ? round(filesize($templateFile) / 1024, 1) : 0;
$fileModified = $fileExists ? date('d-M-Y H:i', filemtime($templateFile)) : null;

$pageTitle = 'HAWB Template';
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
</head>
<body style="background:#f0f2f5;">
<?php require_once __DIR__ . '/../includes/navbar.php'; ?>
<div class="d-flex" style="margin-top:56px;min-height:calc(100vh - 56px);">
<?php require_once __DIR__ . '/../includes/sidebar.php'; ?>

<main class="flex-grow-1 p-4">

<!-- Flash -->
<?php $flash = getFlash(); if ($flash): ?>
<div class="alert alert-<?= e($flash['type']) ?> alert-dismissible fade show">
    <i class="bi bi-<?= $flash['type']==='success'?'check-circle':'exclamation-triangle' ?> me-2"></i>
    <?= e($flash['message']) ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<!-- Upload error -->
<?php if ($uploadError): ?>
<div class="alert alert-danger alert-dismissible fade show">
    <i class="bi bi-x-circle me-2"></i><?= e($uploadError) ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<!-- Page header -->
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="fw-bold mb-0">
            <i class="bi bi-file-earmark-excel text-success me-2"></i>HAWB Template
        </h4>
        <small class="text-muted">Upload file Excel template dùng để xuất HAWB</small>
    </div>
</div>

<div class="row g-4">

    <!-- ── STATUS CARD ───────────────────────────────── -->
    <div class="col-md-5">
        <div class="card shadow-sm h-100">
            <div class="card-header fw-bold py-3">
                <i class="bi bi-info-circle text-primary me-2"></i>Current Template Status
            </div>
            <div class="card-body">
                <?php if ($fileExists): ?>
                <div class="mb-3">
                    <span class="badge bg-success fs-6 py-2 px-3">
                        <i class="bi bi-check-circle me-1"></i>File exists
                    </span>
                </div>
                <table class="table table-sm table-borderless mb-4">
                    <tr>
                        <td class="text-muted fw-semibold" style="width:130px;">File name</td>
                        <td><code>hawb_template.xlsx</code></td>
                    </tr>
                    <tr>
                        <td class="text-muted fw-semibold">Size</td>
                        <td><?= $fileSizeKb ?> KB</td>
                    </tr>
                    <tr>
                        <td class="text-muted fw-semibold">Last updated</td>
                        <td><?= $fileModified ?></td>
                    </tr>
                    <tr>
                        <td class="text-muted fw-semibold">Location</td>
                        <td><code>assets/templates/</code></td>
                    </tr>
                </table>
                <a href="<?= BASE_URL ?>admin/hawb_template.php?download=1"
                   class="btn btn-outline-success w-100">
                    <i class="bi bi-download me-2"></i>Download Current Template
                </a>
                <?php else: ?>
                <div class="mb-3">
                    <span class="badge bg-warning text-dark fs-6 py-2 px-3">
                        <i class="bi bi-exclamation-triangle me-1"></i>No template uploaded
                    </span>
                </div>
                <div class="alert alert-warning py-2 small mb-0">
                    <i class="bi bi-exclamation-triangle-fill me-1"></i>
                    File <code>assets/templates/hawb_template.xlsx</code> chưa tồn tại.<br>
                    Chức năng <strong>Export HAWB to Excel</strong> sẽ không hoạt động cho đến khi upload template.
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- ── UPLOAD CARD ───────────────────────────────── -->
    <div class="col-md-7">
        <div class="card shadow-sm h-100">
            <div class="card-header fw-bold py-3">
                <i class="bi bi-file-earmark-arrow-up text-primary me-2"></i>
                <?= $fileExists ? 'Replace Template' : 'Upload Template' ?>
            </div>
            <div class="card-body">
                <div class="alert alert-info py-2 small mb-4">
                    <i class="bi bi-lightbulb me-1"></i>
                    Upload file <strong>hawb_template.xlsx</strong> — đây là file Excel template dùng khi xuất HAWB bill.
                    <?= $fileExists ? 'File cũ sẽ bị thay thế hoàn toàn.' : '' ?>
                </div>

                <form method="POST" enctype="multipart/form-data" id="uploadForm">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">
                            <i class="bi bi-file-earmark-excel text-success me-1"></i>
                            Chọn file Template (.xlsx)
                        </label>
                        <input type="file"
                               name="template_file"
                               id="templateFile"
                               class="form-control"
                               accept=".xlsx"
                               required>
                        <div class="form-text">
                            Chỉ chấp nhận file <code>.xlsx</code>. Kích thước tối đa: <strong>10 MB</strong>.
                        </div>
                    </div>

                    <!-- File preview -->
                    <div id="filePreview" class="alert alert-light border d-none mb-3 py-2 small">
                        <i class="bi bi-file-earmark-excel text-success me-1"></i>
                        <span id="previewName"></span>
                        <span class="text-muted ms-2" id="previewSize"></span>
                    </div>

                    <button type="submit" class="btn btn-primary w-100 py-2 fw-semibold">
                        <i class="bi bi-cloud-upload me-2"></i>Upload Template
                    </button>
                </form>
            </div>
        </div>
    </div>

</div>
</main>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.getElementById('templateFile').addEventListener('change', function () {
    const file = this.files[0];
    const preview = document.getElementById('filePreview');
    if (!file) { preview.classList.add('d-none'); return; }

    document.getElementById('previewName').textContent = file.name;
    document.getElementById('previewSize').textContent = '(' + (file.size / 1024).toFixed(1) + ' KB)';
    preview.classList.remove('d-none', 'alert-danger', 'alert-light');

    if (!file.name.toLowerCase().endsWith('.xlsx')) {
        preview.classList.add('alert-danger');
    } else {
        preview.classList.add('alert-light');
    }
});

document.getElementById('uploadForm').addEventListener('submit', function (e) {
    const input = document.getElementById('templateFile');
    if (input.files[0] && !input.files[0].name.toLowerCase().endsWith('.xlsx')) {
        e.preventDefault();
        input.setCustomValidity('Only .xlsx files are accepted.');
        input.reportValidity();
    } else {
        input.setCustomValidity('');
    }
});

setTimeout(() => {
    document.querySelectorAll('.alert-dismissible').forEach(el =>
        bootstrap.Alert.getOrCreateInstance(el).close());
}, 5000);
</script>
</body>
</html>
