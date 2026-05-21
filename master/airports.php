<?php
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
requireRole([ROLE_ADMIN, ROLE_MANAGER]);

$db = getDB();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'create') {
        $iata    = strtoupper(trim($_POST['iata_code'] ?? ''));
        $name    = trim($_POST['name'] ?? '');
        $city    = trim($_POST['city'] ?? '');
        $country = trim($_POST['country'] ?? '');
        if ($iata && $name) {
            $stmt = $db->prepare("INSERT INTO airports (iata_code,name,city,country) VALUES (?,?,?,?)");
            $stmt->bind_param('ssss', $iata, $name, $city, $country);
            $stmt->execute() ? setFlash('success', "Airport <strong>$iata</strong> created.") : setFlash('danger', $db->error);
            $stmt->close();
        } else { setFlash('danger', 'IATA Code and Name are required.'); }
        redirect(BASE_URL . 'master/airports.php');
    }

    if ($action === 'update') {
        $id      = (int)$_POST['id'];
        $iata    = strtoupper(trim($_POST['iata_code'] ?? ''));
        $name    = trim($_POST['name'] ?? '');
        $city    = trim($_POST['city'] ?? '');
        $country = trim($_POST['country'] ?? '');
        $active  = isset($_POST['is_active']) ? 1 : 0;
        $stmt    = $db->prepare("UPDATE airports SET iata_code=?,name=?,city=?,country=?,is_active=? WHERE id=?");
        $stmt->bind_param('sssiii', $iata, $name, $city, $country, $active, $id);
        $stmt->execute(); $stmt->close();
        setFlash('success', 'Airport updated.');
        redirect(BASE_URL . 'master/airports.php');
    }

    if ($action === 'delete') {
        $id   = (int)$_POST['id'];
        $stmt = $db->prepare("DELETE FROM airports WHERE id=?");
        $stmt->bind_param('i', $id);
        $stmt->execute() ? setFlash('success', 'Airport deleted.') : setFlash('danger', 'Cannot delete — airport is in use.');
        $stmt->close();
        redirect(BASE_URL . 'master/airports.php');
    }
}

$search = trim($_GET['q'] ?? '');
$sql    = "SELECT * FROM airports";
if ($search) $sql .= " WHERE iata_code LIKE '%$search%' OR name LIKE '%$search%' OR city LIKE '%$search%' OR country LIKE '%$search%'";
$sql .= " ORDER BY iata_code ASC";
$rows  = $db->query($sql);
$total = $db->query("SELECT COUNT(*) FROM airports")->fetch_row()[0];
$pageTitle = 'Airports';
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
        <h4 class="fw-bold mb-0"><i class="bi bi-geo-alt text-primary me-2"></i>Airports</h4>
        <small class="text-muted"><?= $total ?> airports in database</small>
    </div>
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalCreate">
        <i class="bi bi-plus-circle me-1"></i>Add Airport
    </button>
</div>

<div class="card mb-3">
    <div class="card-body py-2">
        <form class="d-flex gap-2" method="GET">
            <input type="text" name="q" class="form-control form-control-sm" style="max-width:320px;"
                   placeholder="Search IATA, name, city, country..." value="<?= e($search) ?>">
            <button class="btn btn-sm btn-outline-secondary"><i class="bi bi-search"></i></button>
            <?php if ($search): ?><a href="?" class="btn btn-sm btn-outline-danger"><i class="bi bi-x"></i></a><?php endif; ?>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead>
                    <tr>
                        <th class="ps-3">#</th>
                        <th>IATA Code</th>
                        <th>Airport Name</th>
                        <th>City</th>
                        <th>Country</th>
                        <th>Status</th>
                        <th class="text-end pe-3">Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php $i=1; while ($row = $rows->fetch_assoc()): ?>
                <tr>
                    <td class="ps-3 text-muted small"><?= $i++ ?></td>
                    <td><span class="badge bg-info text-dark fw-bold"><?= e($row['iata_code']) ?></span></td>
                    <td class="fw-semibold"><?= e($row['name']) ?></td>
                    <td><?= e($row['city'] ?: '—') ?></td>
                    <td><?= e($row['country'] ?: '—') ?></td>
                    <td>
                        <?= $row['is_active']
                            ? '<span class="badge bg-success-subtle text-success border border-success-subtle">Active</span>'
                            : '<span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle">Inactive</span>' ?>
                    </td>
                    <td class="text-end pe-3">
                        <button class="btn btn-sm btn-outline-primary btn-action"
                                onclick="editAirport(<?= htmlspecialchars(json_encode($row)) ?>)">
                            <i class="bi bi-pencil"></i>
                        </button>
                        <form method="POST" class="d-inline"
                              onsubmit="return confirm('Delete <?= e($row['iata_code']) ?>?')">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="id" value="<?= $row['id'] ?>">
                            <button class="btn btn-sm btn-outline-danger btn-action"><i class="bi bi-trash"></i></button>
                        </form>
                    </td>
                </tr>
                <?php endwhile; ?>
                <?php if ($rows->num_rows === 0): ?>
                <tr><td colspan="7" class="text-center text-muted py-5">
                    <i class="bi bi-inbox fs-2 d-block mb-2 opacity-25"></i>No airports found.
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
    <div class="modal-dialog">
        <form method="POST" class="modal-content">
            <input type="hidden" name="action" value="create">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-plus-circle me-2"></i>Add Airport</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body row g-3">
                <div class="col-4">
                    <label class="form-label required">IATA Code</label>
                    <input type="text" name="iata_code" class="form-control text-uppercase"
                           placeholder="HAN" maxlength="3" required>
                </div>
                <div class="col-8">
                    <label class="form-label required">Airport Name</label>
                    <input type="text" name="name" class="form-control"
                           placeholder="NOI BAI INTERNATIONAL AIRPORT" required>
                </div>
                <div class="col-6">
                    <label class="form-label">City</label>
                    <input type="text" name="city" class="form-control" placeholder="Hanoi">
                </div>
                <div class="col-6">
                    <label class="form-label">Country</label>
                    <input type="text" name="country" class="form-control" placeholder="Vietnam">
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
    <div class="modal-dialog">
        <form method="POST" class="modal-content">
            <input type="hidden" name="action" value="update">
            <input type="hidden" name="id" id="e_id">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-pencil me-2"></i>Edit Airport</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body row g-3">
                <div class="col-4">
                    <label class="form-label required">IATA Code</label>
                    <input type="text" name="iata_code" id="e_iata" class="form-control text-uppercase" maxlength="3" required>
                </div>
                <div class="col-8">
                    <label class="form-label required">Airport Name</label>
                    <input type="text" name="name" id="e_name" class="form-control" required>
                </div>
                <div class="col-6">
                    <label class="form-label">City</label>
                    <input type="text" name="city" id="e_city" class="form-control">
                </div>
                <div class="col-6">
                    <label class="form-label">Country</label>
                    <input type="text" name="country" id="e_country" class="form-control">
                </div>
                <div class="col-12">
                    <div class="form-check">
                        <input type="checkbox" name="is_active" id="e_active" class="form-check-input" value="1">
                        <label class="form-check-label" for="e_active">Active</label>
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

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
function editAirport(r) {
    document.getElementById('e_id').value      = r.id;
    document.getElementById('e_iata').value    = r.iata_code;
    document.getElementById('e_name').value    = r.name;
    document.getElementById('e_city').value    = r.city ?? '';
    document.getElementById('e_country').value = r.country ?? '';
    document.getElementById('e_active').checked = r.is_active == 1;
    new bootstrap.Modal(document.getElementById('modalEdit')).show();
}
setTimeout(() => {
    document.querySelectorAll('.alert-dismissible').forEach(el =>
        bootstrap.Alert.getOrCreateInstance(el).close());
}, 4000);
</script>
</body>
</html>