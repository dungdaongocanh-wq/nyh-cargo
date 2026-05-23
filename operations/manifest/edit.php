<?php
require_once __DIR__ . '/../../config/constants.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';
requireLogin();

$db = getDB();
$id = (int)($_GET['id'] ?? 0);
if (!$id) redirect(BASE_URL . 'operations/manifest/index.php');

// ── Helper: load manifest đầy đủ JOIN ───────────────────
function loadManifest(mysqli $db, int $id): ?array {
    $stmt = $db->prepare("
        SELECT m.*,
               al.code        AS airline_code,
               al.name        AS airline_name,
               al.mawb_prefix AS mawb_prefix,
               ap1.iata_code  AS origin_code,
               ap1.name       AS origin_name,
               ap2.iata_code  AS dest_code,
               ap2.name       AS dest_name,
               c.code         AS customer_code,
               c.name         AS customer_name,
               c.address      AS customer_address,
               c.phone        AS customer_phone,
               c.fax          AS customer_fax,
               c.usci_no      AS customer_usci,
               c.contact_full AS customer_contact
        FROM manifests m
        LEFT JOIN airlines  al  ON m.airline_id     = al.id
        LEFT JOIN airports  ap1 ON m.origin_id      = ap1.id
        LEFT JOIN airports  ap2 ON m.destination_id = ap2.id
        LEFT JOIN customers c   ON m.customer_id    = c.id
        WHERE m.id = ?
    ");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $row ?: null;
}

// ── Helper: recalc totals ────────────────────────────────
function recalcManifestTotals(mysqli $db, int $mid): void {
    $db->query("
        UPDATE manifests SET
            total_pieces = (SELECT COALESCE(SUM(no_of_pieces),0) FROM hawbs WHERE manifest_id=$mid),
            total_gw     = (SELECT COALESCE(SUM(gross_weight),0)  FROM hawbs WHERE manifest_id=$mid)
        WHERE id = $mid
    ");
}

// ── Load lần đầu ─────────────────────────────────────────
$manifest = loadManifest($db, $id);
if (!$manifest) redirect(BASE_URL . 'operations/manifest/index.php');

// Staff chỉ xem confirmed/completed
if (currentUserRole() === ROLE_STAFF && $manifest['status'] === 'draft') {
    setFlash('danger', 'This manifest is not yet confirmed.');
    redirect(BASE_URL . 'operations/manifest/index.php');
}

// ════════════════════════════════════════════════════════
// POST ACTIONS
// ════════════════════════════════════════════════════════
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // ── Manager actions ──────────────────────────────────
    if (isManager()) {

        if ($action === 'confirm' && $manifest['status'] === 'draft') {
            $db->query("UPDATE manifests SET status='confirmed', confirmed_at=NOW() WHERE id=$id");
            setFlash('success', 'Manifest confirmed! Staff can now weigh HAWBs.');
            redirect(BASE_URL . 'operations/manifest/edit.php?id=' . $id);
        }

        if ($action === 'complete' && $manifest['status'] === 'confirmed') {
            $pending = $db->query("SELECT COUNT(*) FROM hawbs WHERE manifest_id=$id AND is_weighed=0")->fetch_row()[0];
            if ($pending > 0) {
                setFlash('danger', "$pending HAWB(s) not yet weighed. Cannot complete.");
            } else {
                $db->query("UPDATE manifests SET status='completed', completed_at=NOW() WHERE id=$id");
                setFlash('success', 'Manifest marked as completed.');
            }
            redirect(BASE_URL . 'operations/manifest/edit.php?id=' . $id);
        }

        if ($action === 'revert_draft' && isAdmin() && $manifest['status'] === 'confirmed') {
            $db->query("UPDATE manifests SET status='draft', confirmed_at=NULL WHERE id=$id");
            setFlash('warning', 'Manifest reverted to Draft.');
            redirect(BASE_URL . 'operations/manifest/edit.php?id=' . $id);
        }

        if ($action === 'add_hawb' && in_array($manifest['status'], ['draft','confirmed'])) {
            $manualNo  = strtoupper(trim($_POST['hawb_no_manual'] ?? ''));
            if ($manualNo !== '') {
                $hawbNoToUse = $manualNo;
                $seqYear     = '';
                $seqMonth    = '';
                $seqNumber   = 0;
            } else {
                $gen         = generateHawbNo();
                $hawbNoToUse = $gen['hawb_no'];
                $seqYear     = $gen['seq_year'];
                $seqMonth    = $gen['seq_month'];
                $seqNumber   = $gen['seq_number'];
            }
            $shipId    = (int)($_POST['shipper_id']   ?? 0) ?: null;
            $cneeId    = (int)($_POST['consignee_id'] ?? 0) ?: null;
            $commodity = trim($_POST['commodity']     ?? '');
            $pcs       = max(1, (int)($_POST['no_of_pieces'] ?? 1));
            $payment   = $_POST['payment_term']       ?? 'PP';
            $notify    = trim($_POST['notify_party']  ?? '');

            $stmt = $db->prepare("
                INSERT INTO hawbs
                    (hawb_no, manifest_id, seq_year, seq_month, seq_number,
                     shipper_id, consignee_id, origin_id, destination_id,
                     commodity, no_of_pieces, payment_term, notify_party)
                VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)
            ");
            $stmt->bind_param('ssissiiiisiss',
                $hawbNoToUse, $id, $seqYear, $seqMonth, $seqNumber,
                $shipId, $cneeId, $manifest['origin_id'], $manifest['destination_id'],
                $commodity, $pcs, $payment, $notify
            );
            $stmt->execute();
            $stmt->close();
            recalcManifestTotals($db, $id);
            setFlash('success', "HAWB <strong>{$hawbNoToUse}</strong> added.");
            redirect(BASE_URL . 'operations/manifest/edit.php?id=' . $id);
        }

        if ($action === 'delete_hawb' && $manifest['status'] !== 'completed') {
            $hid = (int)($_POST['hawb_id'] ?? 0);
            $stmt = $db->prepare("DELETE FROM hawbs WHERE id=? AND manifest_id=?");
            $stmt->bind_param('ii', $hid, $id);
            $stmt->execute();
            $stmt->close();
            recalcManifestTotals($db, $id);
            setFlash('success', 'HAWB removed.');
            redirect(BASE_URL . 'operations/manifest/edit.php?id=' . $id);
        }
    }

    // ── Save weighing (Manager + Staff) ──────────────────
    if ($action === 'save_weigh' && in_array($manifest['status'], ['confirmed','completed'])) {
        $hid     = (int)($_POST['hawb_id'] ?? 0);
        $totalGW = (float)($_POST['total_gw'] ?? 0);
        $dim_l   = $_POST['dim_l']   ?? [];
        $dim_w   = $_POST['dim_w']   ?? [];
        $dim_h   = $_POST['dim_h']   ?? [];
        $dim_qty = $_POST['dim_qty'] ?? [];

        // Xoá DIM groups cũ
        $db->query("DELETE FROM hawb_dim_groups WHERE hawb_id=$hid");

        $totalVW = 0.0;
        foreach ($dim_l as $i => $l) {
            $l       = (float)$l;
            $w       = (float)($dim_w[$i]   ?? 0);
            $h       = (float)($dim_h[$i]   ?? 0);
            $qty     = max(1, (int)($dim_qty[$i] ?? 1));
            $vwOne   = ($l && $w && $h) ? round($l * $w * $h / 6000, 4) : 0;
            $vwGroup = round($vwOne * $qty, 4);
            $totalVW += $vwGroup;

            $stmt = $db->prepare("
                INSERT INTO hawb_dim_groups
                    (hawb_id, length, width, height, qty_pieces, vw_per_piece, vw_group)
                VALUES (?,?,?,?,?,?,?)
            ");
            $stmt->bind_param('idddidd', $hid, $l, $w, $h, $qty, $vwOne, $vwGroup);
            $stmt->execute();
            $stmt->close();
        }

        $totalVW = round($totalVW, 2);
        $cw      = calcChargeableWeight($totalGW, $totalVW);
        $by      = currentUserId();

        $stmt = $db->prepare("
            UPDATE hawbs SET
                gross_weight      = ?,
                volume_weight     = ?,
                chargeable_weight = ?,
                is_weighed        = 1,
                weighed_by        = ?,
                weighed_at        = NOW()
            WHERE id = ?
        ");
        $stmt->bind_param('dddii', $totalGW, $totalVW, $cw, $by, $hid);
        $stmt->execute();
        $stmt->close();

        recalcManifestTotals($db, $id);
        setFlash('success',
            "Weight saved. GW=<strong>{$totalGW} kg</strong> · " .
            "VW=<strong>{$totalVW} kg</strong> · " .
            "CW=<strong>{$cw} kg</strong>"
        );
        redirect(BASE_URL . 'operations/manifest/edit.php?id=' . $id . '&weigh=' . $hid);
    }
}

// ── Reload sau POST ───────────────────────────────────────
$manifest = loadManifest($db, $id);

// ── Load HAWBs ───────────────────────────────────────────
$hawbList = $db->query("
    SELECT h.*,
           s.code  AS shipper_code,  s.name AS shipper_name,
           cn.code AS cnee_code,     cn.name AS cnee_name,
           ap1.iata_code AS origin_code,
           ap2.iata_code AS dest_code
    FROM hawbs h
    LEFT JOIN shippers   s   ON h.shipper_id    = s.id
    LEFT JOIN consignees cn  ON h.consignee_id  = cn.id
    LEFT JOIN airports   ap1 ON h.origin_id     = ap1.id
    LEFT JOIN airports   ap2 ON h.destination_id= ap2.id
    WHERE h.manifest_id = $id
    ORDER BY h.seq_number ASC
")->fetch_all(MYSQLI_ASSOC);

// ── Dropdown data ─────────────────────────────────────────
$shipperList   = $db->query("SELECT id,code,name FROM shippers   WHERE is_active=1 ORDER BY code")->fetch_all(MYSQLI_ASSOC);
$consigneeList = $db->query("SELECT id,code,name FROM consignees WHERE is_active=1 ORDER BY code")->fetch_all(MYSQLI_ASSOC);

// ── Active weigh panel ────────────────────────────────────
$activeWeighHawb = null;
$existingDims    = [];
if (!empty($_GET['weigh'])) {
    $wid   = (int)$_GET['weigh'];
    $wstmt = $db->prepare("
        SELECT h.*,
               s.name  AS shipper_name,
               cn.name AS cnee_name
        FROM hawbs h
        LEFT JOIN shippers   s  ON h.shipper_id   = s.id
        LEFT JOIN consignees cn ON h.consignee_id = cn.id
        WHERE h.id=? AND h.manifest_id=?
    ");
    $wstmt->bind_param('ii', $wid, $id);
    $wstmt->execute();
    $activeWeighHawb = $wstmt->get_result()->fetch_assoc();
    $wstmt->close();

    if ($activeWeighHawb) {
        $existingDims = $db->query("
            SELECT * FROM hawb_dim_groups
            WHERE hawb_id = $wid ORDER BY id ASC
        ")->fetch_all(MYSQLI_ASSOC);

        if (!$existingDims) {
            $existingDims = [[
                'length'     => 0,
                'width'      => 0,
                'height'     => 0,
                'qty_pieces' => $activeWeighHawb['no_of_pieces'],
            ]];
        }
    }
}

// ── Stats ─────────────────────────────────────────────────
$totalHawbs    = count($hawbList);
$weighedCount  = count(array_filter($hawbList, fn($h) => $h['is_weighed']));
$totalHawbPcs  = array_sum(array_column($hawbList, 'no_of_pieces'));
$canPrintLabel = $totalHawbPcs > 0 && $totalHawbPcs == ($manifest['total_pieces'] ?? 0);

$pageTitle = 'Manifest: ' . ($manifest['mawb_no'] ?? '');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title><?= e($pageTitle) ?> | <?= APP_NAME ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="<?= BASE_URL ?>assets/css/app.css" rel="stylesheet">
    <style>
        .mawb-header {
            background: linear-gradient(135deg,#1a56db,#1e3a8a);
            color: #fff; border-radius: 16px;
            padding: 1.5rem 2rem; margin-bottom: 1.5rem;
        }
        .mawb-no   { font-size:1.8rem; font-weight:900; letter-spacing:1px; }
        .meta-pill {
            background: rgba(255,255,255,.15); border-radius: 20px;
            padding: .25rem .85rem; font-size: .82rem;
            display: inline-flex; align-items: center; gap: .4rem;
        }
        /* Autocomplete */
        .autocomplete-list {
            position:absolute; z-index:9999; background:#fff;
            border:1px solid #dee2e6; border-radius:8px;
            max-height:200px; overflow-y:auto; width:100%;
            box-shadow:0 4px 16px rgba(0,0,0,.1);
        }
        .autocomplete-list .ac-item { padding:.45rem .75rem; cursor:pointer; font-size:.83rem; }
        .autocomplete-list .ac-item:hover { background:#f0f4ff; }
        .ac-code { font-weight:700; color:#0d6efd; margin-right:.35rem; }
        /* Combo field */
        .combo-field .form-control { border-bottom-left-radius:0; border-bottom-right-radius:0; }
        .combo-field .form-select  {
            border-top:none; border-top-left-radius:0; border-top-right-radius:0;
            font-size:.8rem; color:#6c757d;
        }
        /* DIM row */
        .dim-row input { font-size:.85rem; }
        .dim-header { font-size:.72rem; font-weight:700; text-transform:uppercase;
                      color:#6c757d; letter-spacing:.04em; }
    </style>
</head>
<body style="background:#f0f2f5;">
<?php require_once __DIR__ . '/../../includes/navbar.php'; ?>
<div class="d-flex" style="margin-top:56px; min-height:calc(100vh - 56px);">
<?php require_once __DIR__ . '/../../includes/sidebar.php'; ?>
<main class="flex-grow-1 p-4">

<!-- Flash -->
<?php $flash = getFlash(); if ($flash): ?>
<div class="alert alert-<?= e($flash['type']) ?> alert-dismissible fade show">
    <i class="bi bi-<?= $flash['type']==='success'?'check-circle':'exclamation-triangle' ?> me-2"></i>
    <?= $flash['message'] ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<!-- Breadcrumb -->
<nav aria-label="breadcrumb" class="mb-3">
    <ol class="breadcrumb">
        <li class="breadcrumb-item">
            <a href="<?= BASE_URL ?>operations/manifest/index.php">Manifests</a>
        </li>
        <li class="breadcrumb-item active"><?= e($manifest['mawb_no'] ?? '') ?></li>
    </ol>
</nav>

<!-- ══ MAWB HEADER ════════════════════════════════════════ -->
<div class="mawb-header">
    <div class="row align-items-center">
        <div class="col-lg-8">
            <div class="d-flex align-items-center gap-3 mb-2 flex-wrap">
                <div class="mawb-no"><?= e($manifest['mawb_no'] ?? '') ?></div>
                <?= statusBadge($manifest['status'] ?? 'draft') ?>
            </div>
            <div class="d-flex flex-wrap gap-2">
                <span class="meta-pill">
                    <i class="bi bi-airplane"></i>
                    <?= e($manifest['airline_code'] ?? '—') ?>
                    <?= e($manifest['flight_no']    ?? '') ?>
                </span>
                <span class="meta-pill">
                    <i class="bi bi-calendar3"></i>
                    <?= fmtDate($manifest['flight_date'] ?? null, 'd M Y') ?>
                </span>
                <span class="meta-pill">
                    <i class="bi bi-geo-alt"></i>
                    <?= e($manifest['origin_code'] ?? '—') ?> → <?= e($manifest['dest_code'] ?? '—') ?>
                </span>
                <?php if (!empty($manifest['customer_name'])): ?>
                <span class="meta-pill">
                    <i class="bi bi-building"></i><?= e($manifest['customer_name']) ?>
                </span>
                <?php endif; ?>
                <span class="meta-pill">
                    <i class="bi bi-boxes"></i><?= (int)($manifest['total_pieces'] ?? 0) ?> PCS
                </span>
                <span class="meta-pill">
                    <i class="bi bi-speedometer2"></i>GW: <?= number_format($manifest['total_gw'] ?? 0, 1) ?> kg
                </span>
                <?php if ($totalHawbs > 0): ?>
                <span class="meta-pill">
                    <i class="bi bi-<?= $weighedCount===$totalHawbs?'check-all':'scale' ?>"></i>
                    Weighed: <?= $weighedCount ?>/<?= $totalHawbs ?>
                </span>
                <?php endif; ?>
            </div>
        </div>

        <div class="col-lg-4 mt-3 mt-lg-0">
            <div class="d-flex flex-wrap gap-2 justify-content-lg-end">

                <?php if ($manifest['status'] !== 'draft'): ?>
                <a href="<?= BASE_URL ?>print/manifest_print.php?id=<?= $id ?>"
                   target="_blank" class="btn btn-light btn-sm">
                    <i class="bi bi-printer me-1"></i>Print Manifest
                </a>
                <?php endif; ?>

                <?php if ($manifest['status'] !== 'draft'): ?>
                    <?php if ($canPrintLabel): ?>
                    <a href="<?= BASE_URL ?>print/label_print.php?manifest_id=<?= $id ?>"
                       target="_blank" class="btn btn-warning btn-sm">
                        <i class="bi bi-tag me-1"></i>Print Labels
                    </a>
                    <?php else: ?>
                    <button class="btn btn-warning btn-sm" disabled
                            title="Pieces mismatch — cannot print labels">
                        <i class="bi bi-tag me-1"></i>Labels ⚠️
                    </button>
                    <?php endif; ?>
                <?php endif; ?>

                <?php if (isManager() && $manifest['status'] === 'draft'): ?>
                <form method="POST" class="d-inline">
                    <input type="hidden" name="action" value="confirm">
                    <button class="btn btn-success btn-sm"
                            onclick="return confirm('Confirm this manifest?\nStaff will be able to weigh HAWBs.')">
                        <i class="bi bi-check-circle me-1"></i>Confirm
                    </button>
                </form>
                <?php endif; ?>

                <?php if (isManager() && $manifest['status'] === 'confirmed'): ?>
                <form method="POST" class="d-inline">
                    <input type="hidden" name="action" value="complete">
                    <button class="btn btn-primary btn-sm"
                            onclick="return confirm('Mark manifest as Completed?')">
                        <i class="bi bi-check-all me-1"></i>Complete
                    </button>
                </form>
                <?php endif; ?>

                <?php if (isAdmin() && $manifest['status'] === 'confirmed'): ?>
                <form method="POST" class="d-inline">
                    <input type="hidden" name="action" value="revert_draft">
                    <button class="btn btn-outline-light btn-sm"
                            onclick="return confirm('Revert to Draft?')">
                        <i class="bi bi-arrow-counterclockwise me-1"></i>Revert
                    </button>
                </form>
                <?php endif; ?>

            </div>
        </div>
    </div>
</div>

<!-- ══ MAIN ROW ════════════════════════════════════════════ -->
<div class="row g-4">

<!-- ══ LEFT / FULL: HAWB LIST ════════════════════════════ -->
<div class="col-lg-<?= $activeWeighHawb ? '6' : '12' ?>">

    <?php if (!empty($manifest['customer_name'])): ?>
    <div class="card mb-3">
        <div class="card-body py-2 px-3">
            <div class="text-uppercase fw-bold text-muted mb-1"
                 style="font-size:.68rem;letter-spacing:.05em;">Consignee (MAWB)</div>
            <div class="fw-bold"><?= e($manifest['customer_name']) ?></div>
            <?php if (!empty($manifest['customer_address'])): ?>
            <small class="text-muted"><?= e($manifest['customer_address']) ?></small>
            <?php endif; ?>
            <?php if (!empty($manifest['customer_contact'])): ?>
            <br><small class="text-muted"><?= e($manifest['customer_contact']) ?></small>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>

    <div class="card">
        <div class="card-header py-3 d-flex justify-content-between align-items-center">
            <span class="fw-bold">
                <i class="bi bi-card-list text-success me-2"></i>HAWB Bills
                <span class="badge bg-secondary ms-1"><?= $totalHawbs ?></span>
            </span>
            <div class="d-flex align-items-center gap-2">
                <?php if (!$canPrintLabel && $totalHawbs > 0): ?>
                <span class="badge bg-danger small">
                    <i class="bi bi-exclamation-triangle me-1"></i>Pieces mismatch
                </span>
                <?php endif; ?>
                <?php if (isManager() && $manifest['status'] !== 'completed'): ?>
                <button class="btn btn-success btn-sm"
                        data-bs-toggle="modal" data-bs-target="#modalAddHawb">
                    <i class="bi bi-plus-circle me-1"></i>Add HAWB
                </button>
                <?php endif; ?>
            </div>
        </div>
        <div class="card-body p-0">
            <?php if (!$hawbList): ?>
            <div class="text-center py-5 text-muted">
                <i class="bi bi-inbox fs-2 d-block mb-2 opacity-25"></i>No HAWBs yet.
                <?php if (isManager()): ?>
                <br>
                <button class="btn btn-success btn-sm mt-2"
                        data-bs-toggle="modal" data-bs-target="#modalAddHawb">
                    <i class="bi bi-plus-circle me-1"></i>Add First HAWB
                </button>
                <?php endif; ?>
            </div>
            <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead>
                        <tr>
                            <th class="ps-3">HAWB No</th>
                            <th>Shipper</th>
                            <th>Consignee</th>
                            <th class="text-center">Pcs</th>
                            <th class="text-center">GW</th>
                            <th class="text-center">CW</th>
                            <th class="text-center">Term</th>
                            <th class="text-center">Status</th>
                            <th class="text-end pe-2">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($hawbList as $h):
                        $isActive = ($activeWeighHawb && $activeWeighHawb['id'] == $h['id']);
                    ?>
                    <tr class="<?= $isActive ? 'table-primary' : '' ?>">
                        <td class="ps-3">
                            <div class="fw-bold text-primary" style="font-size:.85rem;">
                                <?= e($h['hawb_no']) ?>
                            </div>
                            <small class="text-muted">
                                <?= e($h['origin_code'] ?? '') ?>→<?= e($h['dest_code'] ?? '') ?>
                            </small>
                        </td>
                        <td>
                            <div class="fw-semibold small"><?= e($h['shipper_code'] ?? '—') ?></div>
                            <small class="text-muted" style="font-size:.7rem;">
                                <?= e(substr($h['shipper_name'] ?? '', 0, 28)) ?>
                            </small>
                        </td>
                        <td>
                            <div class="fw-semibold small"><?= e($h['cnee_code'] ?? '—') ?></div>
                            <small class="text-muted" style="font-size:.7rem;">
                                <?= e(substr($h['cnee_name'] ?? '', 0, 28)) ?>
                            </small>
                        </td>
                        <td class="text-center">
                            <span class="badge bg-secondary"><?= (int)$h['no_of_pieces'] ?></span>
                        </td>
                        <td class="text-center small">
                            <?= $h['gross_weight'] > 0 ? number_format($h['gross_weight'], 1) : '—' ?>
                        </td>
                        <td class="text-center small fw-bold <?= $h['chargeable_weight']>0?'text-success':'' ?>">
                            <?= $h['chargeable_weight'] > 0 ? number_format($h['chargeable_weight'], 1) : '—' ?>
                        </td>
                        <td class="text-center">
                            <span class="badge bg-<?= $h['payment_term']==='PP'?'primary':'warning text-dark' ?>">
                                <?= e($h['payment_term']) ?>
                            </span>
                        </td>
                        <td class="text-center">
                            <?php if ($h['is_weighed']): ?>
                            <span class="badge bg-success-subtle text-success border border-success-subtle">
                                <i class="bi bi-check-circle me-1"></i>Done
                            </span>
                            <?php else: ?>
                            <span class="badge bg-warning-subtle text-warning border border-warning-subtle">
                                Pending
                            </span>
                            <?php endif; ?>
                        </td>
                        <td class="text-end pe-2">
                            <div class="d-flex gap-1 justify-content-end">
                                <!-- GW/CW Weigh -->
                                <a href="?id=<?= $id ?>&weigh=<?= $h['id'] ?>"
                                   class="btn btn-sm btn-action <?= $h['is_weighed']?'btn-outline-success':'btn-outline-warning' ?>"
                                   title="<?= $h['is_weighed']?'Edit Weight':'Weigh Now' ?>"
                                   style="font-size:.7rem;font-weight:700;padding:2px 7px;white-space:nowrap;">
                                    GW/CW
                                </a>
                                <!-- Print HAWB -->
                                <a href="<?= BASE_URL ?>print/hawb_print.php?id=<?= $h['id'] ?>"
                                   target="_blank"
                                   class="btn btn-sm btn-outline-secondary btn-action" title="Print HAWB">
                                    <i class="bi bi-printer"></i>
                                </a>
                                <!-- Excel -->
                                <a href="<?= BASE_URL ?>print/hawb_excel.php?id=<?= $h['id'] ?>"
                                   class="btn btn-sm btn-outline-success btn-action" title="Download Excel">
                                    <i class="bi bi-file-earmark-excel"></i>
                                </a>
                                <!-- Delete -->
                                <?php if (isManager() && $manifest['status'] !== 'completed'): ?>
                                <form method="POST" class="d-inline"
                                      onsubmit="return confirm('Remove HAWB <?= e($h['hawb_no']) ?>?')">
                                    <input type="hidden" name="action"   value="delete_hawb">
                                    <input type="hidden" name="hawb_id"  value="<?= $h['id'] ?>">
                                    <button class="btn btn-sm btn-outline-danger btn-action" title="Delete">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </form>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                    <tfoot class="table-light fw-bold">
                        <tr>
                            <td colspan="3" class="ps-3 small">TOTAL</td>
                            <td class="text-center"><?= array_sum(array_column($hawbList,'no_of_pieces')) ?></td>
                            <td class="text-center"><?= number_format(array_sum(array_column($hawbList,'gross_weight')),1) ?></td>
                            <td class="text-center text-success"><?= number_format(array_sum(array_column($hawbList,'chargeable_weight')),1) ?></td>
                            <td colspan="3"></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- ══ RIGHT: WEIGH PANEL ════════════════════════════════ -->
<?php if ($activeWeighHawb): ?>
<div class="col-lg-6">
    <div class="card">
        <div class="card-header py-3 d-flex justify-content-between align-items-center">
            <span class="fw-bold">
                <i class="bi bi-scale text-warning me-2"></i>
                Weighing: <span class="text-primary"><?= e($activeWeighHawb['hawb_no']) ?></span>
            </span>
            <a href="?id=<?= $id ?>" class="btn btn-sm btn-outline-secondary">
                <i class="bi bi-x"></i>
            </a>
        </div>
        <div class="card-body">

            <!-- HAWB summary -->
            <div class="row g-2 mb-3">
                <div class="col-6">
                    <div class="bg-light rounded p-2 small">
                        <div class="text-muted mb-1">Shipper</div>
                        <div class="fw-semibold"><?= e($activeWeighHawb['shipper_name'] ?? '—') ?></div>
                    </div>
                </div>
                <div class="col-6">
                    <div class="bg-light rounded p-2 small">
                        <div class="text-muted mb-1">Consignee</div>
                        <div class="fw-semibold"><?= e($activeWeighHawb['cnee_name'] ?? '—') ?></div>
                    </div>
                </div>
                <?php if (!empty($activeWeighHawb['commodity'])): ?>
                <div class="col-12">
                    <div class="bg-light rounded p-2 small">
                        <div class="text-muted mb-1">Commodity</div>
                        <div><?= nl2br(e($activeWeighHawb['commodity'])) ?></div>
                    </div>
                </div>
                <?php endif; ?>
            </div>

            <form method="POST" id="weighForm">
                <input type="hidden" name="action"  value="save_weigh">
                <input type="hidden" name="hawb_id" value="<?= $activeWeighHawb['id'] ?>">

                <!-- ── TOTAL GW ──────────────────────────── -->
                <div class="mb-3">
                    <label class="form-label fw-bold">
                        Total Gross Weight — toàn bộ HAWB
                        <span class="badge bg-secondary ms-1">
                            <?= $activeWeighHawb['no_of_pieces'] ?> pcs
                        </span>
                    </label>
                    <div class="input-group">
                        <input type="number" name="total_gw" id="totalGW"
                               class="form-control form-control-lg text-center fw-bold"
                               step="0.01" min="0"
                               value="<?= $activeWeighHawb['gross_weight'] > 0
                                           ? $activeWeighHawb['gross_weight'] : '' ?>"
                               placeholder="e.g. 50.00"
                               oninput="calcLive()" required>
                        <span class="input-group-text fw-bold bg-light">kg</span>
                    </div>
                    <small class="text-muted">
                        Nhập tổng GW của cả <?= $activeWeighHawb['no_of_pieces'] ?> kiện
                    </small>
                </div>

                <!-- ── DIM GROUPS ────────────────────────── -->
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <div class="fw-bold small">
                        DIM Groups
                        <span class="text-muted fw-normal" style="font-size:.75rem;">
                            (L × W × H / số kiện)
                        </span>
                    </div>
                    <button type="button" class="btn btn-outline-secondary btn-sm"
                            onclick="addDimGroup()">
                        <i class="bi bi-plus-circle me-1"></i>Add Group
                    </button>
                </div>

                <!-- Column headers -->
                <div class="row g-1 mb-1 px-1">
                    <div class="col-2"><span class="dim-header">L (cm)</span></div>
                    <div class="col-2"><span class="dim-header">W (cm)</span></div>
                    <div class="col-2"><span class="dim-header">H (cm)</span></div>
                    <div class="col-2 text-center"><span class="dim-header">Qty</span></div>
                    <div class="col-3 text-center"><span class="dim-header">VW group</span></div>
                    <div class="col-1"></div>
                </div>

                <div id="dimContainer">
                    <?php foreach ($existingDims as $di => $dg):
                        $vwGroup = ($dg['length'] > 0 && $dg['width'] > 0 && $dg['height'] > 0)
                            ? round($dg['length'] * $dg['width'] * $dg['height'] / 6000 * $dg['qty_pieces'], 2)
                            : 0;
                    ?>
                    <div class="dim-row row g-1 mb-1 align-items-center" id="dimRow_<?= $di ?>">
                        <div class="col-2">
                            <input type="number" name="dim_l[]"
                                   class="form-control form-control-sm text-center dim-input"
                                   step="0.1" min="0"
                                   value="<?= $dg['length'] > 0 ? $dg['length'] : '' ?>"
                                   placeholder="L" oninput="calcLive()">
                        </div>
                        <div class="col-2">
                            <input type="number" name="dim_w[]"
                                   class="form-control form-control-sm text-center dim-input"
                                   step="0.1" min="0"
                                   value="<?= $dg['width'] > 0 ? $dg['width'] : '' ?>"
                                   placeholder="W" oninput="calcLive()">
                        </div>
                        <div class="col-2">
                            <input type="number" name="dim_h[]"
                                   class="form-control form-control-sm text-center dim-input"
                                   step="0.1" min="0"
                                   value="<?= $dg['height'] > 0 ? $dg['height'] : '' ?>"
                                   placeholder="H" oninput="calcLive()">
                        </div>
                        <div class="col-2">
                            <input type="number" name="dim_qty[]"
                                   class="form-control form-control-sm text-center dim-qty"
                                   step="1" min="1"
                                   value="<?= $dg['qty_pieces'] ?>"
                                   placeholder="pcs" oninput="calcLive()">
                        </div>
                        <div class="col-3">
                            <div class="input-group input-group-sm">
                                <input type="text"
                                       class="form-control text-center bg-light fw-bold vw-group-display"
                                       value="<?= $vwGroup > 0 ? number_format($vwGroup,2) : '—' ?>"
                                       readonly tabindex="-1"
                                       style="font-size:.8rem;">
                                <span class="input-group-text bg-light small">kg</span>
                            </div>
                        </div>
                        <div class="col-1 text-center">
                            <?php if ($di > 0): ?>
                            <button type="button" class="btn btn-sm btn-outline-danger"
                                    style="padding:2px 6px;"
                                    onclick="removeDimGroup(<?= $di ?>)">
                                <i class="bi bi-trash"></i>
                            </button>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>

                <!-- Qty check indicator -->
                <div id="qtyCheck" class="small mt-1 mb-1 ps-1"></div>

                <!-- ── LIVE RESULT ───────────────────────── -->
                <div class="p-3 bg-success bg-opacity-10 border border-success rounded-3 my-3">
                    <div class="row text-center g-0">
                        <div class="col-4">
                            <div class="small text-muted">Total GW</div>
                            <div class="fs-4 fw-bold" id="liveGW">
                                <?= $activeWeighHawb['gross_weight'] > 0
                                    ? number_format($activeWeighHawb['gross_weight'],2) : '0.00' ?>
                            </div>
                            <div class="small text-muted">kg</div>
                        </div>
                        <div class="col-4">
                            <div class="small text-muted">Total VW</div>
                            <div class="fs-4 fw-bold text-info" id="liveVW">
                                <?= $activeWeighHawb['volume_weight'] > 0
                                    ? number_format($activeWeighHawb['volume_weight'],2) : '0.00' ?>
                            </div>
                            <div class="small text-muted">kg</div>
                        </div>
                        <div class="col-4">
                            <div class="small text-muted">Chargeable W</div>
                            <div class="fs-4 fw-bold text-success" id="liveCW">
                                <?= $activeWeighHawb['chargeable_weight'] > 0
                                    ? number_format($activeWeighHawb['chargeable_weight'],2) : '0.00' ?>
                            </div>
                            <div class="small text-muted">kg</div>
                        </div>
                    </div>
                    <div class="text-center mt-2" style="font-size:.68rem;color:#555;">
                        VW = Σ(L×W×H÷6000 × qty) &nbsp;·&nbsp;
                        CW = MAX(GW,VW) → làm tròn lên 0.5 kg
                    </div>
                    <!-- DIM summary string -->
                    <div id="dimSummary" class="text-center mt-1"
                         style="font-size:.72rem;color:#0d6efd;font-weight:600;"></div>
                </div>

                <!-- Save buttons -->
                <div class="d-flex gap-2">
                    <button type="submit" class="btn btn-success flex-grow-1 fw-bold">
                        <i class="bi bi-floppy me-1"></i>Save Weight
                    </button>
                    <a href="<?= BASE_URL ?>print/hawb_print.php?id=<?= $activeWeighHawb['id'] ?>"
                       target="_blank" class="btn btn-outline-secondary" title="Print HAWB">
                        <i class="bi bi-printer"></i>
                    </a>
                    <a href="<?= BASE_URL ?>print/hawb_excel.php?id=<?= $activeWeighHawb['id'] ?>"
                       class="btn btn-outline-success" title="Download Excel">
                        <i class="bi bi-file-earmark-excel"></i>
                    </a>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>

</div><!-- /row -->
</main>
</div>

<!-- ══ MODAL: ADD HAWB ═══════════════════════════════════ -->
<?php if (isManager() && $manifest['status'] !== 'completed'): ?>
<div class="modal fade" id="modalAddHawb" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <form method="POST" class="modal-content">
            <input type="hidden" name="action" value="add_hawb">
            <div class="modal-header border-0">
                <h5 class="modal-title fw-bold">
                    <i class="bi bi-plus-circle text-success me-2"></i>
                    Add HAWB to <?= e($manifest['mawb_no'] ?? '') ?>
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="row g-3">
                    <!-- HAWB No -->
                    <div class="col-md-12">
                        <label class="form-label fw-semibold">
                            HAWB No
                            <span class="text-muted fw-normal">(để trống = tự động tạo)</span>
                        </label>
                        <div class="input-group">
                            <input type="text" name="hawb_no_manual" id="m_hawbNo"
                                   class="form-control text-uppercase fw-bold"
                                   placeholder="Để trống để tự động tạo"
                                   autocomplete="off" style="letter-spacing:.05em;">
                            <button type="button" class="btn btn-outline-secondary"
                                    onclick="suggestModalHawbNo()" title="Lấy số gợi ý">
                                <i class="bi bi-arrow-clockwise me-1"></i>Gợi ý
                            </button>
                        </div>
                        <small class="text-muted">
                            <i class="bi bi-info-circle me-1"></i>
                            Origin/Destination tự động lấy từ manifest.
                        </small>
                    </div>

                    <!-- Shipper -->
                    <div class="col-md-6">
                        <label class="form-label required">Shipper</label>
                        <div class="position-relative combo-field">
                            <input type="text" id="m_shipSearch" class="form-control"
                                   placeholder="Type code or name..." autocomplete="off">
                            <input type="hidden" name="shipper_id" id="m_ship_id">
                            <div id="m_shipList" class="autocomplete-list d-none"></div>
                            <select class="form-select"
                                    onchange="modalSelectParty('shipper', this)">
                                <option value="">— or pick from list —</option>
                                <?php foreach ($shipperList as $s): ?>
                                <option value="<?= $s['id'] ?>"
                                        data-code="<?= e($s['code']) ?>"
                                        data-name="<?= e($s['name']) ?>">
                                    <?= e($s['code']) ?> — <?= e($s['name']) ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <!-- Consignee -->
                    <div class="col-md-6">
                        <label class="form-label required">Consignee</label>
                        <div class="position-relative combo-field">
                            <input type="text" id="m_cneeSearch" class="form-control"
                                   placeholder="Type code or name..." autocomplete="off">
                            <input type="hidden" name="consignee_id" id="m_cnee_id">
                            <div id="m_cneeList" class="autocomplete-list d-none"></div>
                            <select class="form-select"
                                    onchange="modalSelectParty('consignee', this)">
                                <option value="">— or pick from list —</option>
                                <?php foreach ($consigneeList as $c): ?>
                                <option value="<?= $c['id'] ?>"
                                        data-code="<?= e($c['code']) ?>"
                                        data-name="<?= e($c['name']) ?>">
                                    <?= e($c['code']) ?> — <?= e($c['name']) ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <!-- Pieces + Term -->
                    <div class="col-md-3">
                        <label class="form-label required">No. of Pieces</label>
                        <input type="number" name="no_of_pieces"
                               class="form-control text-center"
                               min="1" value="1" required>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Payment Term</label>
                        <select name="payment_term" class="form-select">
                            <option value="PP">PP — Prepaid</option>
                            <option value="CC">CC — Collect</option>
                        </select>
                    </div>

                    <!-- Commodity -->
                    <div class="col-md-6">
                        <label class="form-label">Commodity / Description</label>
                        <textarea name="commodity" class="form-control" rows="3"
                                  placeholder="e.g. 1PKG OF AUTO-FOCUSING COMPONENTS&#10;INV SEMCO-OPT-260521&#10;TERM CIF"></textarea>
                    </div>

                    <!-- Notify Party -->
                    <div class="col-md-6">
                        <label class="form-label">Notify Party</label>
                        <textarea name="notify_party" class="form-control" rows="3"
                                  placeholder="Optional — shown on weight slip / label"></textarea>
                    </div>
                </div>
            </div>
            <div class="modal-footer border-0">
                <button type="button" class="btn btn-secondary"
                        data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-success px-4">
                    <i class="bi bi-plus-circle me-1"></i>Add HAWB
                </button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
const SHIPPERS    = <?= json_encode($shipperList)   ?>;
const CONSIGNEES  = <?= json_encode($consigneeList) ?>;
const TOTAL_PIECES= <?= $activeWeighHawb ? (int)$activeWeighHawb['no_of_pieces'] : 0 ?>;
let dimCount      = <?= count($existingDims) ?>;

// ════════════════════════════════════════════════════════
// AUTOCOMPLETE (modal)
// ════════════════════════════════════════════════════════
function acFilter(data, q, fields) {
    if (!q) return data.slice(0, 10);
    q = q.toLowerCase();
    return data.filter(r => fields.some(f => (r[f]||'').toLowerCase().includes(q))).slice(0,10);
}
function renderAC(listEl, items, onSelect) {
    listEl.innerHTML = '';
    if (!items.length) { listEl.classList.add('d-none'); return; }
    items.forEach(item => {
        const d = document.createElement('div');
        d.className = 'ac-item';
        d.innerHTML = `<span class="ac-code">${item.code}</span><span>${item.name}</span>`;
        d.addEventListener('mousedown', e => {
            e.preventDefault();
            onSelect(item);
            listEl.classList.add('d-none');
        });
        listEl.appendChild(d);
    });
    listEl.classList.remove('d-none');
}
document.addEventListener('click', e => {
    document.querySelectorAll('.autocomplete-list').forEach(el => {
        if (!el.closest('.position-relative')?.contains(e.target))
            el.classList.add('d-none');
    });
});

document.getElementById('m_shipSearch')?.addEventListener('input', function() {
    renderAC(document.getElementById('m_shipList'),
        acFilter(SHIPPERS, this.value, ['code','name']),
        item => {
            document.getElementById('m_ship_id').value = item.id;
            this.value = item.code + ' — ' + item.name;
        });
});
document.getElementById('m_cneeSearch')?.addEventListener('input', function() {
    renderAC(document.getElementById('m_cneeList'),
        acFilter(CONSIGNEES, this.value, ['code','name']),
        item => {
            document.getElementById('m_cnee_id').value = item.id;
            this.value = item.code + ' — ' + item.name;
        });
});
function modalSelectParty(type, sel) {
    const opt = sel.options[sel.selectedIndex];
    if (!opt.value) return;
    if (type === 'shipper') {
        document.getElementById('m_ship_id').value    = opt.value;
        document.getElementById('m_shipSearch').value = opt.dataset.code + ' — ' + opt.dataset.name;
    } else {
        document.getElementById('m_cnee_id').value    = opt.value;
        document.getElementById('m_cneeSearch').value = opt.dataset.code + ' — ' + opt.dataset.name;
    }
}

function suggestModalHawbNo() {
    const el = document.getElementById('m_hawbNo');
    if (el) el.placeholder = 'Đang tải...';
    fetch('<?= BASE_URL ?>api/next_hawb.php')
        .then(r => r.json())
        .then(data => { if (el) { el.value = data.hawb_no; el.placeholder = ''; } })
        .catch(() => { if (el) el.placeholder = 'Lỗi tải số'; });
}

document.getElementById('modalAddHawb')?.addEventListener('show.bs.modal', function() {
    suggestModalHawbNo();
});

// ════════════════════════════════════════════════════════
// WEIGH PANEL — DIM GROUPS + LIVE CALC
// ════════════════════════════════════════════════════════
function calcLive() {
    const gw = parseFloat(document.getElementById('totalGW')?.value) || 0;
    let totalVW  = 0;
    let totalQty = 0;
    const summaries = [];

    document.querySelectorAll('.dim-row').forEach(row => {
        const inputs = row.querySelectorAll('.dim-input');
        const l   = parseFloat(inputs[0]?.value) || 0;
        const w   = parseFloat(inputs[1]?.value) || 0;
        const h   = parseFloat(inputs[2]?.value) || 0;
        const qty = parseInt(row.querySelector('.dim-qty')?.value) || 0;

        const vwOne   = (l && w && h) ? Math.round(l * w * h / 6000 * 10000) / 10000 : 0;
        const vwGroup = Math.round(vwOne * qty * 100) / 100;

        totalVW  += vwGroup;
        totalQty += qty;

        const vwEl = row.querySelector('.vw-group-display');
        if (vwEl) vwEl.value = vwGroup > 0 ? vwGroup.toFixed(2) : '—';

        if (l && w && h && qty) {
            summaries.push(`${l}×${w}×${h}/${qty}pcs=${vwGroup.toFixed(2)}kg`);
        }
    });

    totalVW = Math.round(totalVW * 100) / 100;
    const cw = Math.ceil(Math.max(gw, totalVW) * 2) / 2;

    document.getElementById('liveGW').textContent = gw.toFixed(2);
    document.getElementById('liveVW').textContent = totalVW.toFixed(2);
    document.getElementById('liveCW').textContent = cw.toFixed(2);

    // DIM summary
    const summEl = document.getElementById('dimSummary');
    if (summEl) summEl.textContent = summaries.join('  +  ');

    // Qty check
    const qcEl = document.getElementById('qtyCheck');
    if (qcEl && TOTAL_PIECES > 0) {
        if (totalQty === 0) {
            qcEl.innerHTML = '';
        } else if (totalQty === TOTAL_PIECES) {
            qcEl.innerHTML =
                `<span class="text-success fw-semibold">
                    <i class="bi bi-check-circle me-1"></i>
                    DIM qty matches: ${totalQty} / ${TOTAL_PIECES} pcs ✓
                </span>`;
        } else {
            const diff = TOTAL_PIECES - totalQty;
            qcEl.innerHTML =
                `<span class="text-warning fw-semibold">
                    <i class="bi bi-exclamation-triangle me-1"></i>
                    DIM qty: <strong>${totalQty}</strong> / ${TOTAL_PIECES} pcs
                    ${diff > 0 ? `— còn thiếu ${diff} pcs` : `— thừa ${-diff} pcs`}
                </span>`;
        }
    }
}

function addDimGroup() {
    const idx = dimCount++;

    // Tính remaining qty
    let usedQty = 0;
    document.querySelectorAll('.dim-qty').forEach(el => {
        usedQty += parseInt(el.value) || 0;
    });
    const remaining = Math.max(1, TOTAL_PIECES - usedQty);

    const html = `
    <div class="dim-row row g-1 mb-1 align-items-center" id="dimRow_${idx}">
        <div class="col-2">
            <input type="number" name="dim_l[]"
                   class="form-control form-control-sm text-center dim-input"
                   step="0.1" min="0" value="" placeholder="L" oninput="calcLive()">
        </div>
        <div class="col-2">
            <input type="number" name="dim_w[]"
                   class="form-control form-control-sm text-center dim-input"
                   step="0.1" min="0" value="" placeholder="W" oninput="calcLive()">
        </div>
        <div class="col-2">
            <input type="number" name="dim_h[]"
                   class="form-control form-control-sm text-center dim-input"
                   step="0.1" min="0" value="" placeholder="H" oninput="calcLive()">
        </div>
        <div class="col-2">
            <input type="number" name="dim_qty[]"
                   class="form-control form-control-sm text-center dim-qty"
                   step="1" min="1" value="${remaining}" placeholder="pcs" oninput="calcLive()">
        </div>
        <div class="col-3">
            <div class="input-group input-group-sm">
                <input type="text"
                       class="form-control text-center bg-light fw-bold vw-group-display"
                       value="—" readonly tabindex="-1" style="font-size:.8rem;">
                <span class="input-group-text bg-light small">kg</span>
            </div>
        </div>
        <div class="col-1 text-center">
            <button type="button" class="btn btn-sm btn-outline-danger"
                    style="padding:2px 6px;"
                    onclick="removeDimGroup(${idx})">
                <i class="bi bi-trash"></i>
            </button>
        </div>
    </div>`;
    document.getElementById('dimContainer').insertAdjacentHTML('beforeend', html);
    // Focus L input của row mới
    const newRow = document.getElementById('dimRow_' + idx);
    newRow?.querySelector('.dim-input')?.focus();
    calcLive();
}

function removeDimGroup(idx) {
    document.getElementById('dimRow_' + idx)?.remove();
    calcLive();
}

// Init
<?php if ($activeWeighHawb): ?>calcLive();<?php endif; ?>

// Auto dismiss alerts
setTimeout(() => {
    document.querySelectorAll('.alert-dismissible').forEach(el =>
        bootstrap.Alert.getOrCreateInstance(el).close());
}, 5000);
</script>
</body>
</html>