<?php
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../config/database.php';
$hawbMap = require_once __DIR__ . '/../config/hawb_excel_map.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

requireLogin();
if (!isManager()) {
    setFlash('danger', 'You do not have permission to access HAWB Template.');
    redirect(BASE_URL . 'index.php');
}

$uploadError = null;
$targetDir = __DIR__ . '/../assets/templates/';
$targetFile = $targetDir . 'hawb_template.xlsx';

if (isset($_GET['download']) && $_GET['download'] === '1') {
    if (!file_exists($targetFile)) {
        setFlash('danger', 'Template file does not exist.');
        redirect(BASE_URL . 'admin/hawb_template.php');
    }

    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="hawb_template.xlsx"');
    header('Content-Length: ' . (string)filesize($targetFile));
    readfile($targetFile);
    exit;
}

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
        if (!is_dir($targetDir)) mkdir($targetDir, 0755, true);

        if (move_uploaded_file($file['tmp_name'], $targetFile)) {
            setFlash('success', 'HAWB Template uploaded successfully.');
            redirect(BASE_URL . 'admin/hawb_template.php');
        } else {
            $uploadError = 'Failed to save file. Check folder permissions.';
        }
    }
}

$templateExists = file_exists($targetFile);
$templateSizeKb = $templateExists ? round(filesize($targetFile) / 1024, 2) : null;
$templateMtime  = $templateExists ? date('d/m/Y H:i:s', filemtime($targetFile)) : null;
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
<?php $flash = getFlash(); if ($flash): ?>
<div class="alert alert-<?= e($flash['type']) ?> alert-dismissible fade show">
    <i class="bi bi-<?= $flash['type']==='success'?'check-circle':'exclamation-triangle' ?> me-2"></i>
    <?= $flash['message'] ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<?php if ($uploadError): ?>
<div class="alert alert-danger alert-dismissible fade show">
    <i class="bi bi-exclamation-triangle me-2"></i>
    <?= e($uploadError) ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="fw-bold mb-0"><i class="bi bi-file-earmark-excel text-success me-2"></i>HAWB Template</h4>
        <small class="text-muted">Manage HAWB Excel template used for export.</small>
    </div>
</div>

<div class="row g-3">
    <div class="col-12 col-lg-6">
        <div class="card shadow-sm h-100">
            <div class="card-header bg-white fw-semibold">
                <i class="bi bi-info-circle me-2 text-primary"></i>Current Template Status
            </div>
            <div class="card-body">
                <?php if ($templateExists): ?>
                    <span class="badge bg-success-subtle text-success-emphasis border border-success-subtle">✅ File exists</span>
                    <ul class="list-unstyled mt-3 mb-3">
                        <li class="mb-1"><strong>Path:</strong> <code>assets/templates/hawb_template.xlsx</code></li>
                        <li class="mb-1"><strong>Size:</strong> <?= number_format((float)$templateSizeKb, 2) ?> KB</li>
                        <li><strong>Updated:</strong> <?= e($templateMtime) ?></li>
                    </ul>
                    <a href="<?= BASE_URL ?>admin/hawb_template.php?download=1" class="btn btn-outline-primary btn-sm">
                        <i class="bi bi-download me-1"></i>Download Current Template
                    </a>
                <?php else: ?>
                    <span class="badge bg-warning-subtle text-warning-emphasis border border-warning-subtle">⚠️ No template uploaded</span>
                    <p class="text-muted mt-3 mb-0">
                        Excel export will not work until <code>hawb_template.xlsx</code> is uploaded.
                    </p>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="col-12 col-lg-6">
        <div class="card shadow-sm h-100">
            <div class="card-header bg-white fw-semibold">
                <i class="bi bi-upload me-2 text-primary"></i>Upload Template
            </div>
            <div class="card-body">
                <p class="text-muted mb-3">
                    Upload file <code>hawb_template.xlsx</code>. File này sẽ được dùng khi xuất HAWB ra Excel.
                </p>
                <form method="POST" enctype="multipart/form-data" id="uploadForm">
                    <div class="mb-3">
                        <input type="file" class="form-control" id="templateFile" name="template_file" accept=".xlsx" required>
                        <div class="form-text">Accepted format: <code>.xlsx</code> (max 10MB)</div>
                    </div>
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-cloud-arrow-up me-1"></i>Upload Template
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
document.getElementById('uploadForm').addEventListener('submit', function (event) {
    const fileInput = document.getElementById('templateFile');
    const file = fileInput.files[0];
    if (!file) return;

    if (!file.name.toLowerCase().endsWith('.xlsx')) {
        event.preventDefault();
        fileInput.setCustomValidity('Only .xlsx files are accepted.');
        fileInput.reportValidity();
        return;
    }

    fileInput.setCustomValidity('');
});
</script>
</body>
</html>
