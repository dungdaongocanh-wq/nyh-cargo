<?php
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
requireRole([ROLE_ADMIN, ROLE_MANAGER]);

$db  = getDB();
$msg = '';

// ── CREATE ──────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'create') {
    $code   = strtoupper(trim($_POST['code'] ?? ''));
    $name   = trim($_POST['name'] ?? '');
    $prefix = trim($_POST['mawb_prefix'] ?? '');
    if ($code && $name) {
        $stmt = $db->prepare("INSERT INTO airlines (code,name,mawb_prefix) VALUES (?,?,?)");
        $stmt->bind_param('sss', $code, $name, $prefix);
        if ($stmt->execute()) setFlash('success', "Airline <strong>$code</strong> created.");
        else setFlash('danger', 'Error: ' . $db->error);
        $stmt->close();
    } else {
        setFlash('danger', 'Code and Name are required.');
    }
    redirect(BASE_URL . 'master/airlines.php');
}

// ── UPDATE ──────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'update') {
    $id     = (int)$_POST['id'];
    $code   = strtoupper(trim($_POST['code'] ?? ''));
    $name   = trim($_POST['name'] ?? '');
    $prefix = trim($_POST['mawb_prefix'] ?? '');
    $active = isset($_POST['is_active']) ? 1 : 0;
    $stmt   = $db->prepare("UPDATE airlines SET code=?,name=?,mawb_prefix=?,is_active=? WHERE id=?");
    $stmt->bind_param('sssii', $code, $name, $prefix, $active, $id);
    $stmt->execute(); $stmt->close();
    setFlash('success', 'Airline updated.');
    redirect(BASE_URL . 'master/airlines.php');
}

// ── DELETE ──────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete') {
    $id   = (int)$_POST['id'];
    $stmt = $db->prepare("DELETE FROM airlines WHERE id=?");
    $stmt->bind_param('i', $id);
    if ($stmt->execute()) setFlash('success', 'Airline deleted.');
    else setFlash('danger', 'Cannot delete — airline is in use.');
    $stmt->close();
    redirect(BASE_URL . 'master/airlines.php');
}

// ── FETCH ──────────────────────────────────────────────
$search = trim($_GET['q'] ?? '');
$sql    = "SELECT * FROM airlines";
if ($search) $sql .= " WHERE code LIKE '%$search%' OR name LIKE '%$search%'";
$sql .= " ORDER BY code ASC";
$rows = $db->query($sql);
$pageTitle = 'Airlines';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
    <title><?= $pageTitle ?> | <?= APP_NAME ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="<?= BASE_URL ?>assets/css/app.css" rel="stylesheet">
</head>
<body style="background:#f0f2f5;">

<!-- NAVBAR -->
<?php require_once __DIR__ . '/../includes/navbar.php'; ?>

<div class="d-flex" style="margin-top:56px;min-height:calc(100vh - 56px);">
<?php require_once __DIR__ . '/../includes/sidebar.php'; ?>

<main class="flex-grow-1 p-4">

<!-- Flash -->
<?php $flash = getFlash(); if ($flash): ?>
<div class="alert alert-<?= e($flash['type']) ?> alert-dismissible fade show">
    <i class="bi bi-<?= $flash['type']==='success'?'check-circle':'exclamation-triangle' ?> me-2"></i>
    <?= $flash['message'] ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<!-- Header -->
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="fw-bold mb-0"><i class="bi bi-airplane text-primary me-2"></i>Airlines</h4>
        <small class="text-muted">Manage airline master data</small>
    </div>
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalCreate">
        <i class="bi bi-plus-circle me-1"></i>Add Airline
    </button>
</div>

<!-- Search -->
<div class="card mb-3">
    <div class="card-body py-2">
        <form class="d-flex gap-2" method="GET">
            <input type="text" name="q" class="form-control form-control-sm" style="max-width:300px;"
                   placeholder="Search code or name..." value="<?= e($search) ?>">
            <button class="btn btn-sm btn-outline-secondary"><i class="bi bi-search"></i></button>
            <?php if ($search): ?>
            <a href="?" class="btn btn-sm btn-outline-danger"><i class="bi bi-x"></i></a>
            <?php endif; ?>
        </form>
    </div>
</div>

<!-- Table -->
<div class="card">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead>
                    <tr>
                        <th class="ps-3">#</th>
                        <th>Code</th>
                        <th>Airline Name</th>
                        <th>MAWB Prefix</th>
                        <th>Status</th>
                        <th class="text-end pe-3">Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php $i = 1; while ($row = $rows->fetch_assoc()): ?>
                <tr>
                    <td class="ps-3 text-muted small"><?= $i++ ?></td>
                    <td><span class="badge bg-primary"><?= e($row['code']) ?></span></td>
                    <td class="fw-semibold"><?= e($row['name']) ?></td>
                    <td><code><?= e($row['mawb_prefix'] ?: '—') ?></code></td>
                    <td>
                        <?php if ($row['is_active']): ?>
                        <span class="badge bg-success-subtle text-success border border-success-subtle">Active</span>
                        <?php else: ?>
                        <span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle">Inactive</span>
                        <?php endif; ?>
                    </td>
                    <td class="text-end pe-3">
                        <button class="btn btn-sm btn-outline-primary btn-action"
                                onclick="editAirline(<?= htmlspecialchars(json_encode($row)) ?>)">
                            <i class="bi bi-pencil"></i>
                        </button>
                        <form method="POST" class="d-inline"
                              onsubmit="return confirm('Delete <?= e($row['code']) ?>?')">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="id" value="<?= $row['id'] ?>">
                            <button class="btn btn-sm btn-outline-danger btn-action">
                                <i class="bi bi-trash"></i>
                            </button>
                        </form>
                    </td>
                </tr>
                <?php endwhile; ?>
                <?php if ($rows->num_rows === 0): ?>
                <tr><td colspan="6" class="text-center text-muted py-5">
                    <i class="bi bi-inbox fs-2 d-block mb-2 opacity-25"></i>No airlines found.
                </td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

</main>
</div>

<!-- ── MODAL CREATE ───────────────────────────────── -->
<div class="modal fade" id="modalCreate" tabindex="-1">
    <div class="modal-dialog">
        <form method="POST" class="modal-content">
            <input type="hidden" name="action" value="create">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-plus-circle me-2"></i>Add Airline</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label required">Airline Code</label>
                    <input type="text" name="code" class="form-control text-uppercase"
                           placeholder="e.g. HO" maxlength="10" required>
                    <small class="text-muted">IATA 2-letter carrier code</small>
                </div>
                <div class="mb-3">
                    <label class="form-label required">Airline Name</label>
                    <input type="text" name="name" class="form-control"
                           placeholder="e.g. Juneyao Airlines" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">MAWB Prefix</label>
                    <input type="text" name="mawb_prefix" class="form-control"
                           placeholder="e.g. 018" maxlength="3">
                    <small class="text-muted">3-digit prefix on Master Air Waybill</small>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary"><i class="bi bi-check-circle me-1"></i>Save</button>
            </div>
        </form>
    </div>
</div>

<!-- ── MODAL EDIT ─────────────────────────────────── -->
<div class="modal fade" id="modalEdit" tabindex="-1">
    <div class="modal-dialog">
        <form method="POST" class="modal-content">
            <input type="hidden" name="action" value="update">
            <input type="hidden" name="id" id="edit_id">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-pencil me-2"></i>Edit Airline</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label required">Airline Code</label>
                    <input type="text" name="code" id="edit_code" class="form-control text-uppercase"
                           maxlength="10" required>
                </div>
                <div class="mb-3">
                    <label class="form-label required">Airline Name</label>
                    <input type="text" name="name" id="edit_name" class="form-control" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">MAWB Prefix</label>
                    <input type="text" name="mawb_prefix" id="edit_prefix" class="form-control" maxlength="3">
                </div>
                <div class="form-check">
                    <input type="checkbox" name="is_active" id="edit_active" class="form-check-input" value="1">
                    <label class="form-check-label" for="edit_active">Active</label>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary"><i class="bi bi-check-circle me-1"></i>Update</button>
            </div>
        </form>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
function editAirline(r) {
    document.getElementById('edit_id').value     = r.id;
    document.getElementById('edit_code').value   = r.code;
    document.getElementById('edit_name').value   = r.name;
    document.getElementById('edit_prefix').value = r.mawb_prefix ?? '';
    document.getElementById('edit_active').checked = r.is_active == 1;
    new bootstrap.Modal(document.getElementById('modalEdit')).show();
}
setTimeout(() => {
    document.querySelectorAll('.alert-dismissible').forEach(el =>
        bootstrap.Alert.getOrCreateInstance(el).close());
}, 4000);
</script>
</body>
</html>