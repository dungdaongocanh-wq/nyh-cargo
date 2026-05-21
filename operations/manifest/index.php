<?php
require_once __DIR__ . '/../../config/constants.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';
requireLogin();

$db = getDB();

// ── CONFIRM MANIFEST (Manager/Admin) ──────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isManager()) {
    $action = $_POST['action'] ?? '';
    $id     = (int)($_POST['id'] ?? 0);

    if ($action === 'confirm' && $id) {
        $db->query("UPDATE manifests SET status='confirmed', confirmed_at=NOW() WHERE id=$id AND status='draft'");
        setFlash('success', 'Manifest confirmed. Staff can now weigh HAWBs.');
        redirect(BASE_URL . 'operations/manifest/index.php');
    }
    if ($action === 'delete' && $id) {
        $stmt = $db->prepare("DELETE FROM manifests WHERE id=? AND status='draft'");
        $stmt->bind_param('i', $id);
        $stmt->execute() ? setFlash('success', 'Manifest deleted.') : setFlash('danger', 'Cannot delete confirmed/completed manifest.');
        $stmt->close();
        redirect(BASE_URL . 'operations/manifest/index.php');
    }
}

// ── FILTERS ───────────────────────────────────────────
$search     = trim($_GET['q']      ?? '');
$filterStat = trim($_GET['status'] ?? '');
$filterDate = trim($_GET['date']   ?? '');

$where = ["1=1"];
if ($search)     $where[] = "(m.mawb_no LIKE '%$search%' OR m.flight_no LIKE '%$search%' OR c.name LIKE '%$search%')";
if ($filterStat) $where[] = "m.status='$filterStat'";
if ($filterDate) $where[] = "m.flight_date='$filterDate'";
$whereStr = implode(' AND ', $where);

$sql = "
    SELECT m.*,
           al.code       AS airline_code,
           al.name       AS airline_name,
           ap1.iata_code AS origin_code,
           ap2.iata_code AS dest_code,
           c.name        AS customer_name,
           c.code        AS customer_code,
           u.full_name   AS created_by_name,
           COUNT(h.id)   AS hawb_count,
           SUM(h.no_of_pieces)       AS total_pcs,
           SUM(h.gross_weight)       AS total_gw,
           SUM(h.chargeable_weight)  AS total_cw,
           SUM(CASE WHEN h.is_weighed=1 THEN 1 ELSE 0 END) AS weighed_count
    FROM manifests m
    LEFT JOIN airlines  al  ON m.airline_id      = al.id
    LEFT JOIN airports  ap1 ON m.origin_id        = ap1.id
    LEFT JOIN airports  ap2 ON m.destination_id   = ap2.id
    LEFT JOIN customers c   ON m.customer_id      = c.id
    LEFT JOIN users     u   ON m.created_by       = u.id
    LEFT JOIN hawbs     h   ON m.id               = h.manifest_id
    WHERE $whereStr
    GROUP BY m.id
    ORDER BY m.created_at DESC
";
$rows = $db->query($sql);

// Stats
$stats = [
    'total'     => $db->query("SELECT COUNT(*) FROM manifests")->fetch_row()[0],
    'draft'     => $db->query("SELECT COUNT(*) FROM manifests WHERE status='draft'")->fetch_row()[0],
    'confirmed' => $db->query("SELECT COUNT(*) FROM manifests WHERE status='confirmed'")->fetch_row()[0],
    'completed' => $db->query("SELECT COUNT(*) FROM manifests WHERE status='completed'")->fetch_row()[0],
];
$pageTitle = 'Manifests';
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
<?php require_once __DIR__ . '/../../includes/navbar.php'; ?>
<div class="d-flex" style="margin-top:56px;min-height:calc(100vh - 56px);">
<?php require_once __DIR__ . '/../../includes/sidebar.php'; ?>
<main class="flex-grow-1 p-4">

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
        <h4 class="fw-bold mb-0"><i class="bi bi-file-earmark-text text-primary me-2"></i>Air Cargo Manifests</h4>
        <small class="text-muted">Manage MAWB manifests and HAWB bills</small>
    </div>
    <?php if (isManager()): ?>
    <a href="<?= BASE_URL ?>operations/manifest/create.php" class="btn btn-primary">
        <i class="bi bi-plus-circle me-1"></i>New Manifest
    </a>
    <?php endif; ?>
</div>

<!-- Stats -->
<div class="row g-3 mb-4">
    <?php foreach ([
        ['total',     'Total',     'primary',   'file-earmark-text'],
        ['draft',     'Draft',     'warning',   'pencil-square'],
        ['confirmed', 'Confirmed', 'info',      'check-circle'],
        ['completed', 'Completed', 'success',   'check-all'],
    ] as [$key,$label,$color,$icon]): ?>
    <div class="col-6 col-md-3">
        <a href="?status=<?= $key==='total'?'':$key ?>" class="text-decoration-none">
            <div class="card shadow-sm text-center p-3 h-100 <?= $filterStat===$key?'border-'.$color:'' ?>">
                <div class="fs-2 fw-bold text-<?= $color ?>"><?= $stats[$key] ?></div>
                <div class="small text-muted"><i class="bi bi-<?= $icon ?> me-1"></i><?= $label ?></div>
            </div>
        </a>
    </div>
    <?php endforeach; ?>
</div>

<!-- Filters -->
<div class="card mb-3">
    <div class="card-body py-2">
        <form class="d-flex flex-wrap gap-2 align-items-center" method="GET">
            <input type="text" name="q" class="form-control form-control-sm"
                   style="max-width:240px;" placeholder="MAWB No, Flight, Customer..."
                   value="<?= e($search) ?>">
            <select name="status" class="form-select form-select-sm" style="max-width:140px;">
                <option value="">All Status</option>
                <option value="draft"     <?= $filterStat==='draft'     ?'selected':'' ?>>Draft</option>
                <option value="confirmed" <?= $filterStat==='confirmed' ?'selected':'' ?>>Confirmed</option>
                <option value="completed" <?= $filterStat==='completed' ?'selected':'' ?>>Completed</option>
            </select>
            <input type="date" name="date" class="form-control form-control-sm"
                   style="max-width:160px;" value="<?= e($filterDate) ?>">
            <button class="btn btn-sm btn-outline-secondary"><i class="bi bi-search"></i></button>
            <?php if ($search||$filterStat||$filterDate): ?>
            <a href="?" class="btn btn-sm btn-outline-danger"><i class="bi bi-x"></i> Clear</a>
            <?php endif; ?>
        </form>
    </div>
</div>

<!-- Table -->
<div class="card">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th class="ps-3">MAWB No</th>
                        <th>Flight</th>
                        <th>Date</th>
                        <th>Route</th>
                        <th>Consignee (MAWB)</th>
                        <th>HAWBs</th>
                        <th>GW / CW</th>
                        <th>Status</th>
                        <th>Weighed</th>
                        <th class="text-end pe-3">Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php while ($row = $rows->fetch_assoc()):
                    $hawbCount    = (int)$row['hawb_count'];
                    $weighedCount = (int)$row['weighed_count'];
                    $allWeighed   = $hawbCount > 0 && $weighedCount === $hawbCount;
                ?>
                <tr>
                    <td class="ps-3">
                        <div class="fw-bold text-primary"><?= e($row['mawb_no']) ?></div>
                        <small class="text-muted"><?= e($row['airline_code']) ?> · <?= e($row['airline_name']) ?></small>
                    </td>
                    <td><span class="badge bg-light text-dark border fw-semibold"><?= e($row['flight_no']) ?></span></td>
                    <td><small class="fw-semibold"><?= fmtDate($row['flight_date'],'d-M-Y') ?></small></td>
                    <td>
                        <span class="badge bg-secondary"><?= e($row['origin_code']) ?></span>
                        <i class="bi bi-arrow-right text-muted mx-1 small"></i>
                        <span class="badge bg-dark"><?= e($row['dest_code']) ?></span>
                    </td>
                    <td>
                        <div class="fw-semibold small"><?= e($row['customer_name'] ?: '—') ?></div>
                        <?php if ($row['customer_code']): ?>
                        <small class="text-muted"><?= e($row['customer_code']) ?></small>
                        <?php endif; ?>
                    </td>
                    <td>
                        <span class="badge bg-primary"><?= $hawbCount ?></span>
                    </td>
                    <td>
                        <small>
                            GW: <strong><?= number_format($row['total_gw'] ?? 0, 1) ?></strong><br>
                            CW: <strong class="text-success"><?= number_format($row['total_cw'] ?? 0, 1) ?></strong>
                        </small>
                    </td>
                    <td><?= statusBadge($row['status']) ?></td>
                    <td>
                        <?php if ($hawbCount === 0): ?>
                        <span class="text-muted small">—</span>
                        <?php elseif ($allWeighed): ?>
                        <span class="badge bg-success-subtle text-success border border-success-subtle">
                            <i class="bi bi-check-all me-1"></i>Done
                        </span>
                        <?php else: ?>
                        <span class="badge bg-warning-subtle text-warning border border-warning-subtle">
                            <?= $weighedCount ?>/<?= $hawbCount ?>
                        </span>
                        <?php endif; ?>
                    </td>
                    <td class="text-end pe-3">
                        <div class="d-flex gap-1 justify-content-end">
                            <!-- View/Edit -->
                            <a href="<?= BASE_URL ?>operations/manifest/edit.php?id=<?= $row['id'] ?>"
                               class="btn btn-sm btn-outline-primary btn-action" title="Open">
                                <i class="bi bi-folder2-open"></i>
                            </a>
                            <?php if (isManager() && $row['status'] === 'draft'): ?>
                            <!-- Confirm -->
                            <form method="POST" class="d-inline"
                                  onsubmit="return confirm('Confirm manifest <?= e($row['mawb_no']) ?>?\nStaff will be able to see and weigh HAWBs.')">
                                <input type="hidden" name="action" value="confirm">
                                <input type="hidden" name="id" value="<?= $row['id'] ?>">
                                <button class="btn btn-sm btn-outline-success btn-action" title="Confirm">
                                    <i class="bi bi-check-circle"></i>
                                </button>
                            </form>
                            <!-- Delete -->
                            <form method="POST" class="d-inline"
                                  onsubmit="return confirm('Delete manifest <?= e($row['mawb_no']) ?>?')">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="id" value="<?= $row['id'] ?>">
                                <button class="btn btn-sm btn-outline-danger btn-action" title="Delete">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </form>
                            <?php endif; ?>
                            <!-- Print Manifest -->
                            <?php if ($row['status'] !== 'draft'): ?>
                            <a href="<?= BASE_URL ?>print/manifest_print.php?id=<?= $row['id'] ?>"
                               target="_blank"
                               class="btn btn-sm btn-outline-secondary btn-action" title="Print Manifest">
                                <i class="bi bi-printer"></i>
                            </a>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
                <?php endwhile; ?>
                <?php if ($rows->num_rows === 0): ?>
                <tr><td colspan="10" class="text-center py-5 text-muted">
                    <i class="bi bi-inbox fs-2 d-block mb-2 opacity-25"></i>
                    No manifests found.
                    <?php if (isManager()): ?>
                    <br><a href="<?= BASE_URL ?>operations/manifest/create.php" class="btn btn-primary btn-sm mt-2">
                        <i class="bi bi-plus-circle me-1"></i>Create First Manifest
                    </a>
                    <?php endif; ?>
                </td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
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