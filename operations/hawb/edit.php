<?php
require_once __DIR__ . '/../../config/constants.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';
requireLogin();

$db  = getDB();
$hid = (int)($_GET['id'] ?? 0);
if (!$hid) redirect(BASE_URL . 'operations/hawb/index.php');

// ── Load HAWB ─────────────────────────────────────────────
function loadHawb(mysqli $db, int $hid): ?array {
    $stmt = $db->prepare("
        SELECT h.*,
               s.code  AS shipper_code,  s.name AS shipper_name,
               cn.code AS cnee_code,     cn.name AS cnee_name,
               ap1.iata_code AS origin_code,  ap1.name AS origin_name,
               ap2.iata_code AS dest_code,    ap2.name AS dest_name,
               m.mawb_no, m.flight_no, m.flight_date, m.status AS manifest_status,
               m.origin_id AS m_origin_id, m.destination_id AS m_dest_id,
               al.code AS airline_code
        FROM hawbs h
        LEFT JOIN shippers   s   ON h.shipper_id    = s.id
        LEFT JOIN consignees cn  ON h.consignee_id  = cn.id
        LEFT JOIN airports   ap1 ON h.origin_id     = ap1.id
        LEFT JOIN airports   ap2 ON h.destination_id= ap2.id
        LEFT JOIN manifests  m   ON h.manifest_id   = m.id
        LEFT JOIN airlines   al  ON m.airline_id    = al.id
        WHERE h.id = ?
    ");
    $stmt->bind_param('i', $hid);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $row ?: null;
}

$hawb = loadHawb($db, $hid);
if (!$hawb) {
    setFlash('danger', 'HAWB not found.');
    redirect(BASE_URL . 'operations/hawb/index.php');
}

// Staff không sửa HAWB của manifest draft
if (!isManager() && $hawb['manifest_status'] === 'draft') {
    setFlash('danger', 'Access denied.');
    redirect(BASE_URL . 'operations/hawb/index.php');
}

// Completed manifest → chỉ Admin mới sửa
if ($hawb['manifest_status'] === 'completed' && !isAdmin()) {
    setFlash('danger', 'Manifest is completed. Only Admin can edit HAWBs.');
    redirect(BASE_URL . 'operations/manifest/edit.php?id=' . $hawb['manifest_id']);
}

// ── Dropdown data ─────────────────────────────────────────
$shipperList   = $db->query("SELECT id,code,name FROM shippers   WHERE is_active=1 ORDER BY code")->fetch_all(MYSQLI_ASSOC);
$consigneeList = $db->query("SELECT id,code,name FROM consignees WHERE is_active=1 ORDER BY code")->fetch_all(MYSQLI_ASSOC);
$airportList   = $db->query("SELECT id,iata_code,name,city FROM airports WHERE is_active=1 ORDER BY iata_code")->fetch_all(MYSQLI_ASSOC);

// ════════════════════════════════════════════════════════
// POST — SAVE EDIT
// ════════════════════════════════════════════════════════
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'update_hawb') {
        $shipId    = (int)($_POST['shipper_id']    ?? 0) ?: null;
        $cneeId    = (int)($_POST['consignee_id']  ?? 0) ?: null;
        $originId  = (int)($_POST['origin_id']     ?? 0) ?: null;
        $destId    = (int)($_POST['destination_id']?? 0) ?: null;
        $pcs       = max(1, (int)($_POST['no_of_pieces']  ?? 1));
        $payment   = $_POST['payment_term']         ?? 'PP';
        $commodity = trim($_POST['commodity']       ?? '');
        $notify    = trim($_POST['notify_party']    ?? '');
        $handling  = trim($_POST['handling_info']   ?? '');

        // Extra fields
        $currency   = trim($_POST['currency']                ?? 'USD');
        $rateClass  = trim($_POST['rate_class']              ?? 'Q');
        $itemNo     = trim($_POST['commodity_item_no']       ?? '');
        $acctInfo   = trim($_POST['accounting_info']         ?? 'FREIGHT PREPAID');
        $declCarr   = trim($_POST['declared_value_carriage'] ?? 'NVD');
        $declCust   = trim($_POST['declared_value_customs']  ?? 'AS PER INV');
        $insurance  = trim($_POST['amount_insurance']        ?? 'XXX');

        $stmt = $db->prepare("
            UPDATE hawbs SET
                shipper_id              = ?,
                consignee_id            = ?,
                origin_id               = ?,
                destination_id          = ?,
                no_of_pieces            = ?,
                payment_term            = ?,
                commodity               = ?,
                notify_party            = ?,
                handling_info           = ?,
                currency                = ?,
                rate_class              = ?,
                commodity_item_no       = ?,
                accounting_info         = ?,
                declared_value_carriage = ?,
                declared_value_customs  = ?,
                amount_insurance        = ?
            WHERE id = ?
        ");
        $stmt->bind_param('iiiissssssssssssi',
            $shipId, $cneeId, $originId, $destId,
            $pcs, $payment,
            $commodity, $notify, $handling,
            $currency, $rateClass, $itemNo,
            $acctInfo, $declCarr, $declCust, $insurance,
            $hid
        );
        $stmt->execute();
        $stmt->close();

        // Recalc manifest totals
        $mid = $hawb['manifest_id'];
        $db->query("
            UPDATE manifests SET
                total_pieces = (SELECT COALESCE(SUM(no_of_pieces),0) FROM hawbs WHERE manifest_id=$mid),
                total_gw     = (SELECT COALESCE(SUM(gross_weight),0)  FROM hawbs WHERE manifest_id=$mid)
            WHERE id = $mid
        ");

        setFlash('success', "HAWB <strong>{$hawb['hawb_no']}</strong> updated successfully.");
        redirect(BASE_URL . 'operations/hawb/edit.php?id=' . $hid);
    }
}

// Reload sau POST
$hawb = loadHawb($db, $hid);
$pageTitle = 'Edit HAWB: ' . ($hawb['hawb_no'] ?? '');
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
        .autocomplete-list {
            position:absolute; z-index:9999; background:#fff;
            border:1px solid #dee2e6; border-radius:8px;
            max-height:200px; overflow-y:auto; width:100%;
            box-shadow:0 4px 16px rgba(0,0,0,.1);
        }
        .autocomplete-list .ac-item { padding:.45rem .75rem; cursor:pointer; font-size:.83rem; }
        .autocomplete-list .ac-item:hover { background:#f0f4ff; }
        .ac-code { font-weight:700; color:#0d6efd; margin-right:.35rem; }
        .combo-field .form-control { border-bottom-left-radius:0; border-bottom-right-radius:0; }
        .combo-field .form-select  {
            border-top:none; border-top-left-radius:0; border-top-right-radius:0;
            font-size:.8rem; color:#6c757d;
        }
        .section-title {
            font-size:.7rem; font-weight:700; text-transform:uppercase;
            letter-spacing:.06em; color:#6c757d;
            border-bottom:2px solid #e9ecef; padding-bottom:4px; margin-bottom:12px;
        }
    </style>
</head>
<body style="background:#f0f2f5;">
<?php require_once __DIR__ . '/../../includes/navbar.php'; ?>
<div class="d-flex" style="margin-top:56px; min-height:calc(100vh - 56px);">
<?php require_once __DIR__ . '/../../includes/sidebar.php'; ?>
<main class="flex-grow-1 p-4" style="max-width:1000px;">

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
            <a href="<?= BASE_URL ?>operations/hawb/index.php">HAWBs</a>
        </li>
        <li class="breadcrumb-item">
            <a href="<?= BASE_URL ?>operations/manifest/edit.php?id=<?= $hawb['manifest_id'] ?>">
                <?= e($hawb['mawb_no'] ?? '') ?>
            </a>
        </li>
        <li class="breadcrumb-item active"><?= e($hawb['hawb_no']) ?></li>
    </ol>
</nav>

<!-- Page header -->
<div class="d-flex justify-content-between align-items-start mb-4">
    <div>
        <h4 class="fw-bold mb-1">
            <i class="bi bi-pencil-square text-primary me-2"></i>
            Edit HAWB: <span class="text-primary"><?= e($hawb['hawb_no']) ?></span>
        </h4>
        <div class="d-flex gap-2 flex-wrap">
            <span class="badge bg-light text-dark border">
                <i class="bi bi-airplane me-1"></i>
                <?= e($hawb['airline_code'] ?? '') ?>
                <?= e($hawb['flight_no'] ?? '') ?>
                · <?= fmtDate($hawb['flight_date'] ?? null, 'd M Y') ?>
            </span>
            <span class="badge bg-light text-dark border">
                <i class="bi bi-geo-alt me-1"></i>
                <?= e($hawb['origin_code'] ?? '?') ?> → <?= e($hawb['dest_code'] ?? '?') ?>
            </span>
            <?= statusBadge($hawb['manifest_status'] ?? 'draft') ?>
            <?php if ($hawb['is_weighed']): ?>
            <span class="badge bg-success">
                <i class="bi bi-check-circle me-1"></i>Weighed
            </span>
            <?php endif; ?>
        </div>
    </div>
    <div class="d-flex gap-2">
        <a href="<?= BASE_URL ?>print/hawb_print.php?id=<?= $hid ?>"
           target="_blank" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-printer me-1"></i>Print
        </a>
        <a href="<?= BASE_URL ?>print/hawb_excel.php?id=<?= $hid ?>"
           class="btn btn-outline-success btn-sm">
            <i class="bi bi-file-earmark-excel me-1"></i>Excel
        </a>
        <?php if (isManager()): ?>
        <button class="btn btn-outline-danger btn-sm"
                data-bs-toggle="modal" data-bs-target="#modalDelete">
            <i class="bi bi-trash me-1"></i>Delete
        </button>
        <?php endif; ?>
    </div>
</div>

<!-- ══ FORM ════════════════════════════════════════════ -->
<form method="POST" id="editForm">
    <input type="hidden" name="action" value="update_hawb">

    <!-- ── SECTION 1: PARTIES ──────────────────────── -->
    <div class="card mb-3">
        <div class="card-body">
            <div class="section-title">Shipper & Consignee</div>
            <div class="row g-3">

                <!-- Shipper -->
                <div class="col-md-6">
                    <label class="form-label required">Shipper</label>
                    <div class="position-relative combo-field">
                        <input type="text" id="shipSearch" class="form-control"
                               placeholder="Type code or name..." autocomplete="off"
                               value="<?= !empty($hawb['shipper_code'])
                                   ? e($hawb['shipper_code'] . ' — ' . $hawb['shipper_name'])
                                   : '' ?>">
                        <input type="hidden" name="shipper_id" id="ship_id"
                               value="<?= $hawb['shipper_id'] ?? '' ?>">
                        <div id="shipList" class="autocomplete-list d-none"></div>
                        <select class="form-select" onchange="selectParty('shipper', this)">
                            <option value="">— or pick from list —</option>
                            <?php foreach ($shipperList as $s): ?>
                            <option value="<?= $s['id'] ?>"
                                    data-code="<?= e($s['code']) ?>"
                                    data-name="<?= e($s['name']) ?>"
                                    <?= $hawb['shipper_id'] == $s['id'] ? 'selected' : '' ?>>
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
                        <input type="text" id="cneeSearch" class="form-control"
                               placeholder="Type code or name..." autocomplete="off"
                               value="<?= !empty($hawb['cnee_code'])
                                   ? e($hawb['cnee_code'] . ' — ' . $hawb['cnee_name'])
                                   : '' ?>">
                        <input type="hidden" name="consignee_id" id="cnee_id"
                               value="<?= $hawb['consignee_id'] ?? '' ?>">
                        <div id="cneeList" class="autocomplete-list d-none"></div>
                        <select class="form-select" onchange="selectParty('consignee', this)">
                            <option value="">— or pick from list —</option>
                            <?php foreach ($consigneeList as $c): ?>
                            <option value="<?= $c['id'] ?>"
                                    data-code="<?= e($c['code']) ?>"
                                    data-name="<?= e($c['name']) ?>"
                                    <?= $hawb['consignee_id'] == $c['id'] ? 'selected' : '' ?>>
                                <?= e($c['code']) ?> — <?= e($c['name']) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- ── SECTION 2: ROUTING ──────────────────────── -->
    <div class="card mb-3">
        <div class="card-body">
            <div class="section-title">Routing & Basic Info</div>
            <div class="row g-3">

                <!-- Origin -->
                <div class="col-md-3">
                    <label class="form-label">Origin Airport</label>
                    <div class="position-relative combo-field">
                        <input type="text" id="originSearch" class="form-control"
                               placeholder="HAN..." autocomplete="off"
                               value="<?= !empty($hawb['origin_code'])
                                   ? e($hawb['origin_code'] . ' — ' . $hawb['origin_name'])
                                   : '' ?>">
                        <input type="hidden" name="origin_id" id="origin_id"
                               value="<?= $hawb['origin_id'] ?? '' ?>">
                        <div id="originList" class="autocomplete-list d-none"></div>
                        <select class="form-select" onchange="selectAirport('origin', this)">
                            <option value="">— pick —</option>
                            <?php foreach ($airportList as $ap): ?>
                            <option value="<?= $ap['id'] ?>"
                                    data-code="<?= e($ap['iata_code']) ?>"
                                    data-name="<?= e($ap['name']) ?>"
                                    <?= $hawb['origin_id'] == $ap['id'] ? 'selected' : '' ?>>
                                <?= e($ap['iata_code']) ?> — <?= e($ap['name']) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <!-- Destination -->
                <div class="col-md-3">
                    <label class="form-label">Destination Airport</label>
                    <div class="position-relative combo-field">
                        <input type="text" id="destSearch" class="form-control"
                               placeholder="PVG..." autocomplete="off"
                               value="<?= !empty($hawb['dest_code'])
                                   ? e($hawb['dest_code'] . ' — ' . $hawb['dest_name'])
                                   : '' ?>">
                        <input type="hidden" name="destination_id" id="dest_id"
                               value="<?= $hawb['destination_id'] ?? '' ?>">
                        <div id="destList" class="autocomplete-list d-none"></div>
                        <select class="form-select" onchange="selectAirport('dest', this)">
                            <option value="">— pick —</option>
                            <?php foreach ($airportList as $ap): ?>
                            <option value="<?= $ap['id'] ?>"
                                    data-code="<?= e($ap['iata_code']) ?>"
                                    data-name="<?= e($ap['name']) ?>"
                                    <?= $hawb['destination_id'] == $ap['id'] ? 'selected' : '' ?>>
                                <?= e($ap['iata_code']) ?> — <?= e($ap['name']) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <!-- Pieces -->
                <div class="col-md-2">
                    <label class="form-label required">No. of Pieces</label>
                    <input type="number" name="no_of_pieces"
                           class="form-control text-center fw-bold"
                           min="1" value="<?= (int)$hawb['no_of_pieces'] ?>" required>
                </div>

                <!-- Payment Term -->
                <div class="col-md-2">
                    <label class="form-label">Payment Term</label>
                    <select name="payment_term" class="form-select">
                        <option value="PP" <?= $hawb['payment_term']==='PP'?'selected':'' ?>>PP — Prepaid</option>
                        <option value="CC" <?= $hawb['payment_term']==='CC'?'selected':'' ?>>CC — Collect</option>
                    </select>
                </div>

                <!-- Currency -->
                <div class="col-md-2">
                    <label class="form-label">Currency</label>
                    <input type="text" name="currency" class="form-control text-center text-uppercase"
                           value="<?= e($hawb['currency'] ?? 'USD') ?>" maxlength="5">
                </div>
            </div>
        </div>
    </div>

    <!-- ── SECTION 3: COMMODITY ────────────────────── -->
    <div class="card mb-3">
        <div class="card-body">
            <div class="section-title">Goods & Handling</div>
            <div class="row g-3">

                <!-- Commodity -->
                <div class="col-md-8">
                    <label class="form-label">Commodity / Description</label>
                    <textarea name="commodity" class="form-control" rows="4"
                              placeholder="e.g. 1PKG OF AUTO-FOCUSING COMPONENTS (SC1C87)&#10;INV SEMCO-OPT-260521-S2&#10;TERM CIF"><?= e($hawb['commodity'] ?? '') ?></textarea>
                </div>

                <!-- Notify Party -->
                <div class="col-md-4">
                    <label class="form-label">Notify Party</label>
                    <textarea name="notify_party" class="form-control" rows="4"
                              placeholder="Optional — shown on weight slip"><?= e($hawb['notify_party'] ?? '') ?></textarea>
                </div>

                <!-- Handling Info -->
                <div class="col-md-12">
                    <label class="form-label">
                        Handling Information
                        <span class="text-muted fw-normal">(if any)</span>
                    </label>
                    <input type="text" name="handling_info" class="form-control"
                           placeholder="e.g. KEEP UPRIGHT · FRAGILE · DO NOT STACK"
                           value="<?= e($hawb['handling_info'] ?? '') ?>">
                </div>
            </div>
        </div>
    </div>

    <!-- ── SECTION 4: DECLARED VALUES (Collapsible) ── -->
    <div class="card mb-3">
        <div class="card-header py-2 d-flex justify-content-between align-items-center"
             style="cursor:pointer;" data-bs-toggle="collapse" data-bs-target="#declaredPanel">
            <span class="small fw-bold">
                <i class="bi bi-shield-check text-secondary me-2"></i>
                Declared Values & Accounting
            </span>
            <i class="bi bi-chevron-down small text-muted"></i>
        </div>
        <div class="collapse" id="declaredPanel">
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-3">
                        <label class="form-label small">Rate Class</label>
                        <input type="text" name="rate_class" class="form-control text-center text-uppercase"
                               value="<?= e($hawb['rate_class'] ?? 'Q') ?>" maxlength="5">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small">Commodity Item No.</label>
                        <input type="text" name="commodity_item_no" class="form-control"
                               value="<?= e($hawb['commodity_item_no'] ?? '') ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small">Accounting Info</label>
                        <input type="text" name="accounting_info" class="form-control"
                               value="<?= e($hawb['accounting_info'] ?? 'FREIGHT PREPAID') ?>">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small">Declared Value for Carriage</label>
                        <input type="text" name="declared_value_carriage" class="form-control"
                               value="<?= e($hawb['declared_value_carriage'] ?? 'NVD') ?>">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small">Declared Value for Customs</label>
                        <input type="text" name="declared_value_customs" class="form-control"
                               value="<?= e($hawb['declared_value_customs'] ?? 'AS PER INV') ?>">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small">Amount of Insurance</label>
                        <input type="text" name="amount_insurance" class="form-control"
                               value="<?= e($hawb['amount_insurance'] ?? 'XXX') ?>">
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- ── WEIGHT INFO (read-only) ─────────────────── -->
    <?php if ($hawb['is_weighed']): ?>
    <div class="card mb-3 border-success">
        <div class="card-body py-2">
            <div class="section-title text-success">Weight Data (Read-only — edit via Weigh panel)</div>
            <div class="row g-2 text-center">
                <div class="col-4">
                    <div class="small text-muted">Gross Weight</div>
                    <div class="fw-bold fs-5"><?= number_format($hawb['gross_weight'],2) ?> kg</div>
                </div>
                <div class="col-4">
                    <div class="small text-muted">Volume Weight</div>
                    <div class="fw-bold fs-5 text-info"><?= number_format($hawb['volume_weight'],2) ?> kg</div>
                </div>
                <div class="col-4">
                    <div class="small text-muted">Chargeable Weight</div>
                    <div class="fw-bold fs-5 text-success"><?= number_format($hawb['chargeable_weight'],2) ?> kg</div>
                </div>
            </div>
            <div class="text-center mt-2">
                <a href="<?= BASE_URL ?>operations/manifest/edit.php?id=<?= $hawb['manifest_id'] ?>&weigh=<?= $hid ?>"
                   class="btn btn-sm btn-outline-success">
                    <i class="bi bi-scale me-1"></i>Edit Weight in Manifest
                </a>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Save buttons -->
    <div class="d-flex gap-2 justify-content-end mb-5">
        <a href="<?= BASE_URL ?>operations/manifest/edit.php?id=<?= $hawb['manifest_id'] ?>"
           class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left me-1"></i>Back to Manifest
        </a>
        <a href="<?= BASE_URL ?>operations/hawb/index.php"
           class="btn btn-outline-secondary">
            <i class="bi bi-list me-1"></i>All HAWBs
        </a>
        <button type="submit" class="btn btn-primary px-4">
            <i class="bi bi-floppy me-1"></i>Save Changes
        </button>
    </div>
</form>

</main>
</div>

<!-- ══ MODAL DELETE ══════════════════════════════════════ -->
<?php if (isManager()): ?>
<div class="modal fade" id="modalDelete" tabindex="-1">
    <div class="modal-dialog modal-sm">
        <div class="modal-content">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title text-danger fw-bold">
                    <i class="bi bi-trash me-2"></i>Delete HAWB
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p>Delete HAWB <strong class="text-danger"><?= e($hawb['hawb_no']) ?></strong>?</p>
                <div class="alert alert-warning small py-2">
                    <i class="bi bi-exclamation-triangle me-1"></i>
                    This will also delete all weight data (DIM groups) for this HAWB.
                    <?php if ($hawb['manifest_status'] === 'completed'): ?>
                    <br><strong>Warning: Manifest is Completed!</strong>
                    <?php endif; ?>
                </div>
            </div>
            <div class="modal-footer border-0 pt-0">
                <button type="button" class="btn btn-secondary btn-sm"
                        data-bs-dismiss="modal">Cancel</button>
                <form method="POST"
                      action="<?= BASE_URL ?>operations/hawb/delete.php">
                    <input type="hidden" name="hawb_id"     value="<?= $hid ?>">
                    <input type="hidden" name="manifest_id" value="<?= $hawb['manifest_id'] ?>">
                    <input type="hidden" name="csrf_token"
                           value="<?= $_SESSION['csrf_token'] ?? '' ?>">
                    <button type="submit" class="btn btn-danger btn-sm">
                        <i class="bi bi-trash me-1"></i>Yes, Delete
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
const SHIPPERS   = <?= json_encode($shipperList)   ?>;
const CONSIGNEES = <?= json_encode($consigneeList) ?>;
const AIRPORTS   = <?= json_encode($airportList)   ?>;

// ── Autocomplete ──────────────────────────────────────────
function acFilter(data, q, fields) {
    if (!q) return data.slice(0, 10);
    q = q.toLowerCase();
    return data.filter(r => fields.some(f => (r[f]||'').toLowerCase().includes(q))).slice(0,10);
}
function renderAC(listEl, items, onSelect, renderFn) {
    listEl.innerHTML = '';
    if (!items.length) { listEl.classList.add('d-none'); return; }
    items.forEach(item => {
        const d = document.createElement('div');
        d.className = 'ac-item';
        d.innerHTML = renderFn(item);
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

// Shipper
document.getElementById('shipSearch').addEventListener('input', function() {
    renderAC(document.getElementById('shipList'),
        acFilter(SHIPPERS, this.value, ['code','name']),
        item => {
            document.getElementById('ship_id').value = item.id;
            this.value = item.code + ' — ' + item.name;
        },
        item => `<span class="ac-code">${item.code}</span>${item.name}`
    );
});

// Consignee
document.getElementById('cneeSearch').addEventListener('input', function() {
    renderAC(document.getElementById('cneeList'),
        acFilter(CONSIGNEES, this.value, ['code','name']),
        item => {
            document.getElementById('cnee_id').value = item.id;
            this.value = item.code + ' — ' + item.name;
        },
        item => `<span class="ac-code">${item.code}</span>${item.name}`
    );
});

// Origin
document.getElementById('originSearch').addEventListener('input', function() {
    renderAC(document.getElementById('originList'),
        acFilter(AIRPORTS, this.value, ['iata_code','name','city']),
        item => {
            document.getElementById('origin_id').value = item.id;
            this.value = item.iata_code + ' — ' + item.name;
        },
        item => `<span class="ac-code">${item.iata_code}</span>${item.name}`
    );
});

// Destination
document.getElementById('destSearch').addEventListener('input', function() {
    renderAC(document.getElementById('destList'),
        acFilter(AIRPORTS, this.value, ['iata_code','name','city']),
        item => {
            document.getElementById('dest_id').value = item.id;
            this.value = item.iata_code + ' — ' + item.name;
        },
        item => `<span class="ac-code">${item.iata_code}</span>${item.name}`
    );
});

// Dropdown selects
function selectParty(type, sel) {
    const opt = sel.options[sel.selectedIndex];
    if (!opt.value) return;
    if (type === 'shipper') {
        document.getElementById('ship_id').value    = opt.value;
        document.getElementById('shipSearch').value = opt.dataset.code + ' — ' + opt.dataset.name;
    } else {
        document.getElementById('cnee_id').value    = opt.value;
        document.getElementById('cneeSearch').value = opt.dataset.code + ' — ' + opt.dataset.name;
    }
}
function selectAirport(type, sel) {
    const opt = sel.options[sel.selectedIndex];
    if (!opt.value) return;
    const map = {
        origin: { hid:'origin_id', inp:'originSearch' },
        dest:   { hid:'dest_id',   inp:'destSearch'   },
    };
    document.getElementById(map[type].hid).value = opt.value;
    document.getElementById(map[type].inp).value = opt.dataset.code + ' — ' + opt.dataset.name;
}

// Auto dismiss alerts
setTimeout(() => {
    document.querySelectorAll('.alert-dismissible').forEach(el =>
        bootstrap.Alert.getOrCreateInstance(el).close());
}, 5000);
</script>
</body>
</html>