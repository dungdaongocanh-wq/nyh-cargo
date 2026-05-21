<?php
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
requireRole([ROLE_ADMIN, ROLE_MANAGER]);

$db = getDB();

// ── HELPERS ─────────────────────────────────────────────
function shipperFields($post) {
    return [
        strtoupper(trim($post['code']    ?? '')),
        trim($post['name']    ?? ''),
        trim($post['address'] ?? ''),
        trim($post['city']    ?? ''),
        trim($post['country'] ?? ''),
        trim($post['phone']   ?? ''),
        trim($post['fax']     ?? ''),
        trim($post['email']   ?? ''),
        trim($post['tax_id']  ?? ''),
    ];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // CREATE
    if ($action === 'create') {
        [$code,$name,$addr,$city,$country,$phone,$fax,$email,$tax] = shipperFields($_POST);
        if ($code && $name) {
            $stmt = $db->prepare("INSERT INTO shippers (code,name,address,city,country,phone,fax,email,tax_id) VALUES (?,?,?,?,?,?,?,?,?)");
            $stmt->bind_param('sssssssss', $code,$name,$addr,$city,$country,$phone,$fax,$email,$tax);
            $stmt->execute() ? setFlash('success', "Shipper <strong>$code</strong> created.") : setFlash('danger', $db->error);
            $stmt->close();
        } else { setFlash('danger', 'Code and Name are required.'); }
        redirect(BASE_URL . 'master/shippers.php');
    }

    // UPDATE
    if ($action === 'update') {
        $id = (int)$_POST['id'];
        [$code,$name,$addr,$city,$country,$phone,$fax,$email,$tax] = shipperFields($_POST);
        $active = isset($_POST['is_active']) ? 1 : 0;
        $stmt   = $db->prepare("UPDATE shippers SET code=?,name=?,address=?,city=?,country=?,phone=?,fax=?,email=?,tax_id=?,is_active=? WHERE id=?");
        $stmt->bind_param('sssssssssii', $code,$name,$addr,$city,$country,$phone,$fax,$email,$tax,$active,$id);
        $stmt->execute(); $stmt->close();
        setFlash('success', 'Shipper updated.');
        redirect(BASE_URL . 'master/shippers.php');
    }

    // DELETE
    if ($action === 'delete') {
        $id = (int)$_POST['id'];
        $stmt = $db->prepare("DELETE FROM shippers WHERE id=?");
        $stmt->bind_param('i', $id);
        $stmt->execute() ? setFlash('success', 'Shipper deleted.') : setFlash('danger', 'Cannot delete — shipper is in use.');
        $stmt->close();
        redirect(BASE_URL . 'master/shippers.php');
    }

    // IMPORT EXCEL
    if ($action === 'import') {
        require_once __DIR__ . '/../includes/excel_import.php';
        $result = importShippers($_FILES['excel_file'] ?? null, $db);
        setFlash($result['type'], $result['message']);
        redirect(BASE_URL . 'master/shippers.php');
    }
}

// ── FETCH ──────────────────────────────────────────────
$search  = trim($_GET['q'] ?? '');
$sql     = "SELECT * FROM shippers";
if ($search) $sql .= " WHERE code LIKE '%$search%' OR name LIKE '%$search%' OR country LIKE '%$search%'";
$sql    .= " ORDER BY code ASC";
$rows    = $db->query($sql);
$total   = $db->query("SELECT COUNT(*) FROM shippers")->fetch_row()[0];
$pageTitle = 'Shippers';
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
<?php require_once __DIR__ . '/../includes/navbar.php'; ?>
<div class="d-flex" style="margin-top:56px;min-height:calc(100vh - 56px);">
<?php require_once __DIR__ . '/../includes/sidebar.php'; ?>
<main class="flex-grow-1 p-4">

<?php $flash = getFlash(); if ($flash): ?>
<div class="alert alert-<?= e($flash['type']) ?> alert-dismissible fade show">
    <?= $flash['message'] ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="fw-bold mb-0"><i class="bi bi-box-seam text-primary me-2"></i>Shippers</h4>
        <small class="text-muted"><?= $total ?> shippers in database</small>
    </div>
    <div class="d-flex gap-2">
        <a href="<?= BASE_URL ?>master/download_template.php?type=shippers"
           class="btn btn-outline-success btn-sm">
            <i class="bi bi-download me-1"></i>Download Template
        </a>
        <button class="btn btn-outline-primary" data-bs-toggle="modal" data-bs-target="#modalImport">
            <i class="bi bi-file-earmark-excel me-1"></i>Import Excel
        </button>
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalCreate">
            <i class="bi bi-plus-circle me-1"></i>Add Shipper
        </button>
    </div>
</div>

<!-- Search -->
<div class="card mb-3">
    <div class="card-body py-2">
        <form class="d-flex gap-2" method="GET">
            <input type="text" name="q" class="form-control form-control-sm" style="max-width:320px;"
                   placeholder="Search code, name, country..." value="<?= e($search) ?>">
            <button class="btn btn-sm btn-outline-secondary"><i class="bi bi-search"></i></button>
            <?php if ($search): ?><a href="?" class="btn btn-sm btn-outline-danger"><i class="bi bi-x"></i></a><?php endif; ?>
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
                        <th>Name</th>
                        <th>Address</th>
                        <th>Country</th>
                        <th>Phone</th>
                        <th>Status</th>
                        <th class="text-end pe-3">Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php $i=1; while ($row = $rows->fetch_assoc()): ?>
                <tr>
                    <td class="ps-3 text-muted small"><?= $i++ ?></td>
                    <td><span class="badge bg-primary"><?= e($row['code']) ?></span></td>
                    <td>
                        <div class="fw-semibold"><?= e($row['name']) ?></div>
                        <?php if ($row['email']): ?>
                        <small class="text-muted"><?= e($row['email']) ?></small>
                        <?php endif; ?>
                    </td>
                    <td>
                        <small class="text-muted" style="max-width:200px;display:block;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">
                            <?= e($row['address'] ?: '—') ?>
                        </small>
                    </td>
                    <td><?= e($row['country'] ?: '—') ?></td>
                    <td><small><?= e($row['phone'] ?: '—') ?></small></td>
                    <td>
                        <?= $row['is_active']
                            ? '<span class="badge bg-success-subtle text-success border border-success-subtle">Active</span>'
                            : '<span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle">Inactive</span>' ?>
                    </td>
                    <td class="text-end pe-3">
                        <button class="btn btn-sm btn-outline-primary btn-action"
                                onclick='editShipper(<?= json_encode($row) ?>)'>
                            <i class="bi bi-pencil"></i>
                        </button>
                        <form method="POST" class="d-inline"
                              onsubmit="return confirm('Delete <?= e($row['code']) ?>?')">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="id" value="<?= $row['id'] ?>">
                            <button class="btn btn-sm btn-outline-danger btn-action"><i class="bi bi-trash"></i></button>
                        </form>
                    </td>
                </tr>
                <?php endwhile; ?>
                <?php if ($rows->num_rows === 0): ?>
                <tr><td colspan="8" class="text-center text-muted py-5">
                    <i class="bi bi-inbox fs-2 d-block mb-2 opacity-25"></i>No shippers found.
                </td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
</main>
</div>

<!-- MODAL CREATE -->
<div class="modal fade" id="modalCreate" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <form method="POST" class="modal-content">
            <input type="hidden" name="action" value="create">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-plus-circle me-2"></i>Add Shipper</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body row g-3">
                <div class="col-md-3">
                    <label class="form-label required">Code</label>
                    <input type="text" name="code" class="form-control text-uppercase" placeholder="SEMCO" required>
                    <small class="text-muted">Short code for search</small>
                </div>
                <div class="col-md-9">
                    <label class="form-label required">Company Name</label>
                    <input type="text" name="name" class="form-control"
                           placeholder="SAMSUNG ELECTRO-MECHANICS CO.,LTD" required>
                </div>
                <div class="col-12">
                    <label class="form-label">Address</label>
                    <textarea name="address" class="form-control" rows="2"
                              placeholder="150, MAEYEONG-RO, YEONGTONGU-GU, SUWON-SI, GYEONGGI-DO, 16674 KOREA"></textarea>
                </div>
                <div class="col-md-4">
                    <label class="form-label">City</label>
                    <input type="text" name="city" class="form-control" placeholder="Suwon">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Country</label>
                    <input type="text" name="country" class="form-control" placeholder="South Korea">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Tax ID</label>
                    <input type="text" name="tax_id" class="form-control">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Phone</label>
                    <input type="text" name="phone" class="form-control" placeholder="+82-31-XXX-XXXX">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Fax</label>
                    <input type="text" name="fax" class="form-control">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Email</label>
                    <input type="email" name="email" class="form-control">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary"><i class="bi bi-check-circle me-1"></i>Save</button>
            </div>
        </form>
    </div>
</div>

<!-- MODAL EDIT -->
<div class="modal fade" id="modalEdit" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <form method="POST" class="modal-content">
            <input type="hidden" name="action" value="update">
            <input type="hidden" name="id" id="se_id">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-pencil me-2"></i>Edit Shipper</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body row g-3">
                <div class="col-md-3">
                    <label class="form-label required">Code</label>
                    <input type="text" name="code" id="se_code" class="form-control text-uppercase" required>
                </div>
                <div class="col-md-9">
                    <label class="form-label required">Company Name</label>
                    <input type="text" name="name" id="se_name" class="form-control" required>
                </div>
                <div class="col-12">
                    <label class="form-label">Address</label>
                    <textarea name="address" id="se_address" class="form-control" rows="2"></textarea>
                </div>
                <div class="col-md-4">
                    <label class="form-label">City</label>
                    <input type="text" name="city" id="se_city" class="form-control">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Country</label>
                    <input type="text" name="country" id="se_country" class="form-control">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Tax ID</label>
                    <input type="text" name="tax_id" id="se_tax" class="form-control">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Phone</label>
                    <input type="text" name="phone" id="se_phone" class="form-control">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Fax</label>
                    <input type="text" name="fax" id="se_fax" class="form-control">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Email</label>
                    <input type="email" name="email" id="se_email" class="form-control">
                </div>
                <div class="col-12">
                    <div class="form-check">
                        <input type="checkbox" name="is_active" id="se_active" class="form-check-input" value="1">
                        <label class="form-check-label" for="se_active">Active</label>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary"><i class="bi bi-check-circle me-1"></i>Update</button>
            </div>
        </form>
    </div>
</div>

<!-- MODAL IMPORT -->
<div class="modal fade" id="modalImport" tabindex="-1">
    <div class="modal-dialog">
        <form method="POST" enctype="multipart/form-data" class="modal-content">
            <input type="hidden" name="action" value="import">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-file-earmark-excel me-2 text-success"></i>Import Shippers from Excel</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-info small">
                    <i class="bi bi-info-circle me-1"></i>
                    Download the template first. Columns: <br>
                    <code>code | name | address | city | country | phone | fax | email | tax_id</code>
                </div>
                <a href="<?= BASE_URL ?>master/download_template.php?type=shippers"
                   class="btn btn-outline-success btn-sm mb-3">
                    <i class="bi bi-download me-1"></i>Download Template (.xlsx)
                </a>
                <div class="mb-3">
                    <label class="form-label required">Select Excel File (.xlsx / .xls)</label>
                    <input type="file" name="excel_file" class="form-control"
                           accept=".xlsx,.xls" required>
                </div>
                <div class="form-check">
                    <input type="checkbox" name="skip_existing" id="skip_existing"
                           class="form-check-input" value="1" checked>
                    <label class="form-check-label" for="skip_existing">
                        Skip existing codes (don't overwrite)
                    </label>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-success">
                    <i class="bi bi-upload me-1"></i>Import
                </button>
            </div>
        </form>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
function editShipper(r) {
    const f = {
        se_id:'id', se_code:'code', se_name:'name', se_address:'address',
        se_city:'city', se_country:'country', se_tax:'tax_id',
        se_phone:'phone', se_fax:'fax', se_email:'email'
    };
    for (const [elId, key] of Object.entries(f)) {
        document.getElementById(elId).value = r[key] ?? '';
    }
    document.getElementById('se_active').checked = r.is_active == 1;
    new bootstrap.Modal(document.getElementById('modalEdit')).show();
}
setTimeout(() => {
    document.querySelectorAll('.alert-dismissible').forEach(el =>
        bootstrap.Alert.getOrCreateInstance(el).close());
}, 4000);
</script>
</body>
</html>