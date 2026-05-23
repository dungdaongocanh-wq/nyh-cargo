<?php
require_once __DIR__ . '/../../config/constants.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';
requireRole([ROLE_ADMIN, ROLE_MANAGER]);

$db = getDB();

// ── SAVE MANIFEST + HAWBs ─────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $mawb_no     = strtoupper(trim($_POST['mawb_no']       ?? ''));
    $airline_id  = (int)($_POST['airline_id']              ?? 0);
    $flight_no   = strtoupper(trim($_POST['flight_no']     ?? ''));
    $flight_date = trim($_POST['flight_date']              ?? '');
    $origin_id   = (int)($_POST['origin_id']               ?? 0);
    $dest_id     = (int)($_POST['destination_id']          ?? 0);
    $customer_id = (int)($_POST['customer_id']             ?? 0) ?: null;
    $notes       = trim($_POST['notes']                    ?? '');
    $status      = isset($_POST['confirm_now']) ? 'confirmed' : 'draft';

    $errors = [];
    if (!$mawb_no)     $errors[] = 'MAWB No is required.';
    if (!$airline_id)  $errors[] = 'Airline is required.';
    if (!$flight_no)   $errors[] = 'Flight No is required.';
    if (!$flight_date) $errors[] = 'Flight date is required.';
    if (!$origin_id)   $errors[] = 'Origin airport is required.';
    if (!$dest_id)     $errors[] = 'Destination airport is required.';

    if (!$errors) {
        $chk = $db->prepare("SELECT id FROM manifests WHERE mawb_no=?");
        $chk->bind_param('s', $mawb_no);
        $chk->execute();
        if ($chk->get_result()->num_rows > 0)
            $errors[] = "MAWB No <strong>$mawb_no</strong> already exists.";
        $chk->close();
    }

    if ($errors) {
        setFlash('danger', implode('<br>', $errors));
        redirect(BASE_URL . 'operations/manifest/create.php');
    }

    $by          = currentUserId();
    $confirmedAt = $status === 'confirmed' ? date('Y-m-d H:i:s') : null;
    $stmt = $db->prepare("
        INSERT INTO manifests
            (mawb_no,airline_id,flight_no,flight_date,origin_id,destination_id,
             customer_id,notes,status,confirmed_at,created_by)
        VALUES (?,?,?,?,?,?,?,?,?,?,?)
    ");
    $stmt->bind_param('sississsssi',
        $mawb_no,$airline_id,$flight_no,$flight_date,$origin_id,$dest_id,
        $customer_id,$notes,$status,$confirmedAt,$by
    );

    if (!$stmt->execute()) {
        setFlash('danger', 'Error: ' . $db->error);
        $stmt->close();
        redirect(BASE_URL . 'operations/manifest/create.php');
    }
    $manifest_id = $db->insert_id;
    $stmt->close();

    // Insert HAWBs
    $hawb_shippers    = $_POST['hawb_shipper_id']   ?? [];
    $hawb_consignees  = $_POST['hawb_consignee_id'] ?? [];
    $hawb_commodities = $_POST['hawb_commodity']    ?? [];
    $hawb_pieces      = $_POST['hawb_pieces']       ?? [];
    $hawb_payment     = $_POST['hawb_payment_term'] ?? [];
    $hawb_notify      = $_POST['hawb_notify_party'] ?? [];
    $hawb_no_manual   = $_POST['hawb_no_manual']    ?? [];

    $totalPieces = 0;
    foreach ($hawb_shippers as $idx => $shipperId) {
        $manualNo  = strtoupper(trim($hawb_no_manual[$idx] ?? ''));
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
        $shipId    = (int)$shipperId ?: null;
        $cneeId    = (int)($hawb_consignees[$idx]  ?? 0) ?: null;
        $commodity = trim($hawb_commodities[$idx]  ?? '');
        $pcs       = (int)($hawb_pieces[$idx]      ?? 0);
        $payment   = $hawb_payment[$idx]            ?? 'PP';
        $notify    = trim($hawb_notify[$idx]        ?? '');
        $totalPieces += $pcs;

        $stmt2 = $db->prepare("
            INSERT INTO hawbs
                (hawb_no,manifest_id,seq_year,seq_month,seq_number,
                 shipper_id,consignee_id,origin_id,destination_id,
                 commodity,no_of_pieces,payment_term,notify_party)
            VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)
        ");
        $stmt2->bind_param('ssissiiiisiss',
            $hawbNoToUse,$manifest_id,$seqYear,$seqMonth,$seqNumber,
            $shipId,$cneeId,$origin_id,$dest_id,
            $commodity,$pcs,$payment,$notify
        );
        $stmt2->execute();
        $stmt2->close();
    }

    $db->query("UPDATE manifests SET total_pieces=$totalPieces WHERE id=$manifest_id");

    $hawbCount = count($hawb_shippers);
    setFlash('success',
        "Manifest <strong>$mawb_no</strong> created with <strong>$hawbCount</strong> HAWB(s). " .
        ($status === 'confirmed' ? '<span class="badge bg-success">Confirmed</span>' : '<span class="badge bg-secondary">Draft</span>')
    );
    redirect(BASE_URL . 'operations/manifest/edit.php?id=' . $manifest_id);
}

// ── DROPDOWN DATA ─────────────────────────────────────
$airlines_rs  = $db->query("SELECT id,code,name,mawb_prefix FROM airlines  WHERE is_active=1 ORDER BY code");
$airports_rs  = $db->query("SELECT id,iata_code,name,city,country FROM airports WHERE is_active=1 ORDER BY iata_code");
$customers_rs = $db->query("SELECT id,code,name FROM customers  WHERE is_active=1 ORDER BY code");
$shippers_rs  = $db->query("SELECT id,code,name FROM shippers   WHERE is_active=1 ORDER BY code");
$consignees_rs= $db->query("SELECT id,code,name FROM consignees WHERE is_active=1 ORDER BY code");

$airlineList  = $airlines_rs->fetch_all(MYSQLI_ASSOC);
$airportList  = $airports_rs->fetch_all(MYSQLI_ASSOC);
$customerList = $customers_rs->fetch_all(MYSQLI_ASSOC);
$shipperList  = $shippers_rs->fetch_all(MYSQLI_ASSOC);
$consigneeList= $consignees_rs->fetch_all(MYSQLI_ASSOC);

$pageTitle = 'New Manifest';
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
        /* ── Autocomplete ── */
        .autocomplete-list {
            position: absolute; z-index: 9999;
            background: #fff; border: 1px solid #dee2e6;
            border-radius: 8px; max-height: 220px;
            overflow-y: auto; width: 100%;
            box-shadow: 0 4px 16px rgba(0,0,0,.12);
        }
        .autocomplete-list .ac-item {
            padding: .45rem .75rem; cursor: pointer; font-size: .85rem;
        }
        .autocomplete-list .ac-item:hover,
        .autocomplete-list .ac-item.active { background: #f0f4ff; }
        .ac-code { font-weight: 700; color: #0d6efd; margin-right: .35rem; }
        .ac-name { color: #495057; }

        /* ── HAWB row ── */
        .hawb-row {
            background: #fff; border: 1px solid #dee2e6;
            border-radius: 12px; padding: 1.1rem 1.25rem;
            position: relative;
        }
        .hawb-num {
            position: absolute; top: -13px; left: 16px;
            background: #0d6efd; color: #fff;
            border-radius: 20px; padding: 2px 14px;
            font-size: .75rem; font-weight: 700;
        }
        .hawb-delete { position: absolute; top: 10px; right: 12px; }

        /* ── Combo field ── */
        .combo-field .form-control { border-bottom-left-radius: 0; border-bottom-right-radius: 0; }
        .combo-field .form-select  {
            border-top: none;
            border-top-left-radius: 0; border-top-right-radius: 0;
            font-size: .8rem; color: #6c757d;
        }
        .combo-field .form-select:focus { border-color: #86b7fe; box-shadow: none; }
        .combo-field .form-control-sm + .form-select { font-size: .78rem; }
    </style>
</head>
<body style="background:#f0f2f5;">

<?php require_once __DIR__ . '/../../includes/navbar.php'; ?>
<div class="d-flex" style="margin-top:56px; min-height:calc(100vh - 56px);">
<?php require_once __DIR__ . '/../../includes/sidebar.php'; ?>

<main class="flex-grow-1 p-4" style="max-width:1200px;">

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
        <li class="breadcrumb-item active">New Manifest</li>
    </ol>
</nav>

<form method="POST" id="manifestForm">

<!-- ── PAGE HEADER ─────────────────────────────────── -->
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="fw-bold mb-0">
            <i class="bi bi-plus-circle text-primary me-2"></i>New Air Cargo Manifest
        </h4>
        <small class="text-muted">Fill in MAWB details and add HAWB bills</small>
    </div>
    <div class="d-flex gap-2">
        <a href="index.php" class="btn btn-outline-secondary">
            <i class="bi bi-x me-1"></i>Cancel
        </a>
        <button type="submit" name="save_draft" class="btn btn-outline-primary">
            <i class="bi bi-floppy me-1"></i>Save Draft
        </button>
        <button type="submit" name="confirm_now" value="1" class="btn btn-success">
            <i class="bi bi-check-circle me-1"></i>Save & Confirm
        </button>
    </div>
</div>

<!-- ══════════════════════════════════════════════════
     SECTION 1 — MASTER AIR WAYBILL INFO
═══════════════════════════════════════════════════ -->
<div class="card mb-4">
    <div class="card-header py-3">
        <span class="fw-bold">
            <i class="bi bi-info-circle text-primary me-2"></i>Master Air Waybill Information
        </span>
    </div>
    <div class="card-body">
        <div class="row g-3">

            <!-- AIRLINE -->
            <div class="col-md-4">
                <label class="form-label required">Airline</label>
                <div class="position-relative combo-field">
                    <input type="text" id="airlineSearch" class="form-control"
                           placeholder="Type code or name..." autocomplete="off">
                    <input type="hidden" name="airline_id" id="airline_id">
                    <div id="airlineList" class="autocomplete-list d-none"></div>
                    <select id="airlineSelect" class="form-select"
                            onchange="selectFromDropdown('airline', this)">
                        <option value="">— or pick from list —</option>
                        <?php foreach ($airlineList as $a): ?>
                        <option value="<?= $a['id'] ?>"
                                data-code="<?= e($a['code']) ?>"
                                data-name="<?= e($a['name']) ?>"
                                data-prefix="<?= e($a['mawb_prefix']) ?>">
                            <?= e($a['code']) ?> — <?= e($a['name']) ?>
                            <?= $a['mawb_prefix'] ? '(' . e($a['mawb_prefix']) . '-)' : '' ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div id="airlineSelected" class="mt-1"></div>
            </div>

            <!-- MAWB NO -->
            <div class="col-md-4">
                <label class="form-label required">Master Air Waybill No</label>
                <div class="input-group">
                    <span class="input-group-text bg-light fw-bold"
                          id="mawbPrefix" style="min-width:52px;">—</span>
                    <input type="text" id="mawbSuffix" class="form-control"
                           placeholder="92594106" maxlength="12">
                    <input type="hidden" name="mawb_no" id="mawb_no">
                </div>
                <small class="text-muted">Prefix auto-filled from airline</small>
            </div>

            <!-- FLIGHT NO -->
            <div class="col-md-2">
                <label class="form-label required">Flight No</label>
                <input type="text" name="flight_no" class="form-control text-uppercase"
                       placeholder="HO1330" required>
            </div>

            <!-- FLIGHT DATE -->
            <div class="col-md-2">
                <label class="form-label required">Flight Date</label>
                <input type="date" name="flight_date" class="form-control"
                       value="<?= date('Y-m-d') ?>" required>
            </div>

            <!-- ORIGIN AIRPORT -->
            <div class="col-md-3">
                <label class="form-label required">Origin Airport (FROM)</label>
                <div class="position-relative combo-field">
                    <input type="text" id="originSearch" class="form-control"
                           placeholder="HAN — Noi Bai..." autocomplete="off">
                    <input type="hidden" name="origin_id" id="origin_id">
                    <div id="originList" class="autocomplete-list d-none"></div>
                    <select id="originSelect" class="form-select"
                            onchange="selectAirportDropdown('origin', this)">
                        <option value="">— or pick from list —</option>
                        <?php foreach ($airportList as $ap): ?>
                        <option value="<?= $ap['id'] ?>"
                                data-code="<?= e($ap['iata_code']) ?>"
                                data-name="<?= e($ap['name']) ?>"
                                data-city="<?= e($ap['city']) ?>"
                                data-country="<?= e($ap['country']) ?>">
                            <?= e($ap['iata_code']) ?> — <?= e($ap['name']) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div id="originSelected" class="mt-1"></div>
            </div>

            <!-- DESTINATION AIRPORT -->
            <div class="col-md-3">
                <label class="form-label required">Destination Airport (TO)</label>
                <div class="position-relative combo-field">
                    <input type="text" id="destSearch" class="form-control"
                           placeholder="PVG — Pudong..." autocomplete="off">
                    <input type="hidden" name="destination_id" id="destination_id">
                    <div id="destList" class="autocomplete-list d-none"></div>
                    <select id="destSelect" class="form-select"
                            onchange="selectAirportDropdown('dest', this)">
                        <option value="">— or pick from list —</option>
                        <?php foreach ($airportList as $ap): ?>
                        <option value="<?= $ap['id'] ?>"
                                data-code="<?= e($ap['iata_code']) ?>"
                                data-name="<?= e($ap['name']) ?>"
                                data-city="<?= e($ap['city']) ?>"
                                data-country="<?= e($ap['country']) ?>">
                            <?= e($ap['iata_code']) ?> — <?= e($ap['name']) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div id="destSelected" class="mt-1"></div>
            </div>

            <!-- CONSIGNEE MAWB -->
            <div class="col-md-4">
                <label class="form-label">Consignee (MAWB)</label>
                <div class="position-relative combo-field">
                    <input type="text" id="customerSearch" class="form-control"
                           placeholder="Search by code or name..." autocomplete="off">
                    <input type="hidden" name="customer_id" id="customer_id">
                    <div id="customerList" class="autocomplete-list d-none"></div>
                    <select id="customerSelect" class="form-select"
                            onchange="selectFromDropdown('customer', this)">
                        <option value="">— or pick from list —</option>
                        <?php foreach ($customerList as $c): ?>
                        <option value="<?= $c['id'] ?>"
                                data-code="<?= e($c['code']) ?>"
                                data-name="<?= e($c['name']) ?>">
                            <?= e($c['code']) ?> — <?= e($c['name']) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div id="customerSelected" class="mt-1"></div>
            </div>

            <!-- NOTES -->
            <div class="col-md-2">
                <label class="form-label">Notes</label>
                <input type="text" name="notes" class="form-control" placeholder="Optional">
            </div>

        </div><!-- /row -->

        <!-- Shipper MAWB (Fixed NAMYANG) -->
        <div class="mt-3 p-3 bg-light rounded-3 border-start border-4 border-primary">
            <div class="text-uppercase fw-bold small text-muted mb-1"
                 style="font-size:.7rem;letter-spacing:.06em;">
                Shipper on MAWB (Fixed)
            </div>
            <div class="row g-0 small text-muted">
                <div class="col-md-7">
                    <strong class="text-dark"><?= DEFAULT_SHIPPER_NAME ?></strong><br>
                    <?= DEFAULT_SHIPPER_ADDRESS ?>
                </div>
                <div class="col-md-5">
                    Tel: <?= DEFAULT_SHIPPER_TEL ?><br>
                    Fax: <?= DEFAULT_SHIPPER_FAX ?><br>
                    MST: <?= DEFAULT_SHIPPER_TAX ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ══════════════════════════════════════════════════
     SECTION 2 — HAWB BILLS
═══════════════════════════════════════════════════ -->
<div class="card mb-4">
    <div class="card-header py-3 d-flex justify-content-between align-items-center">
        <span class="fw-bold">
            <i class="bi bi-card-list text-success me-2"></i>House Air Waybill Bills
        </span>
        <div class="d-flex align-items-center gap-3">
            <small class="text-muted">
                Total pieces: <strong id="totalPcsDisplay">0</strong>
            </small>
            <button type="button" class="btn btn-success btn-sm" onclick="addHawbRow()">
                <i class="bi bi-plus-circle me-1"></i>Add HAWB
            </button>
        </div>
    </div>
    <div class="card-body">
        <div id="hawbContainer"></div>
        <div id="noHawbMsg" class="text-center text-muted py-4" style="display:none;">
            <i class="bi bi-inbox fs-3 d-block mb-2 opacity-25"></i>
            No HAWBs added yet. Click <strong>Add HAWB</strong> to begin.
        </div>
    </div>
</div>

<!-- Submit bottom -->
<div class="d-flex justify-content-end gap-2 mb-5">
    <a href="index.php" class="btn btn-outline-secondary">
        <i class="bi bi-x me-1"></i>Cancel
    </a>
    <button type="submit" name="save_draft" class="btn btn-outline-primary px-4">
        <i class="bi bi-floppy me-1"></i>Save as Draft
    </button>
    <button type="submit" name="confirm_now" value="1" class="btn btn-success px-4">
        <i class="bi bi-check-circle me-1"></i>Save & Confirm Now
    </button>
</div>

</form>
</main>
</div><!-- /wrapper -->

<!-- ══════════════════════════════════════════════════
     HAWB ROW TEMPLATE
═══════════════════════════════════════════════════ -->
<template id="hawbTemplate">
    <div class="hawb-row mb-4" id="hawbRow_{IDX}">
        <div class="hawb-num">HAWB #{NUM}</div>
        <button type="button" class="btn btn-sm btn-outline-danger hawb-delete"
                onclick="removeHawb({IDX})" title="Remove HAWB">
            <i class="bi bi-trash"></i>
        </button>

        <div class="row g-2 mt-1">

            <!-- HAWB NO (editable) -->
            <div class="col-md-4">
                <label class="form-label small fw-bold">
                    HAWB No
                    <span class="text-muted fw-normal">(tự động — có thể sửa)</span>
                </label>
                <div class="input-group input-group-sm">
                    <input type="text" name="hawb_no_manual[]" id="hawbNo_{IDX}"
                           class="form-control form-control-sm text-uppercase fw-bold"
                           placeholder="Đang tải..." autocomplete="off"
                           style="letter-spacing:.05em;">
                    <button type="button" class="btn btn-outline-secondary btn-sm"
                            onclick="suggestHawbNo({IDX})" title="Lấy số tự động">
                        <i class="bi bi-arrow-clockwise"></i>
                    </button>
                </div>
                <small class="text-muted" style="font-size:.7rem;">
                    Để trống = tự động tạo khi lưu
                </small>
            </div>

            <!-- SHIPPER -->
            <div class="col-md-4">
                <label class="form-label required small">Shipper</label>
                <div class="position-relative combo-field">
                    <input type="text" id="shipSearch_{IDX}"
                           class="form-control form-control-sm"
                           placeholder="Code or name..." autocomplete="off"
                           oninput="acHawbSearch(this,'shipper',{IDX})">
                    <input type="hidden" name="hawb_shipper_id[]" id="ship_id_{IDX}">
                    <div id="shipList_{IDX}" class="autocomplete-list d-none"></div>
                    <select class="form-select form-select-sm"
                            onchange="selectHawbDropdown('shipper',{IDX},this)">
                        <option value="">— or pick from list —</option>
                        __SHIPPER_OPTIONS__
                    </select>
                </div>
                <div id="shipSel_{IDX}" class="mt-1"></div>
            </div>

            <!-- CONSIGNEE -->
            <div class="col-md-4">
                <label class="form-label required small">Consignee</label>
                <div class="position-relative combo-field">
                    <input type="text" id="cneeSearch_{IDX}"
                           class="form-control form-control-sm"
                           placeholder="Code or name..." autocomplete="off"
                           oninput="acHawbSearch(this,'consignee',{IDX})">
                    <input type="hidden" name="hawb_consignee_id[]" id="cnee_id_{IDX}">
                    <div id="cneeList_{IDX}" class="autocomplete-list d-none"></div>
                    <select class="form-select form-select-sm"
                            onchange="selectHawbDropdown('consignee',{IDX},this)">
                        <option value="">— or pick from list —</option>
                        __CONSIGNEE_OPTIONS__
                    </select>
                </div>
                <div id="cneeSel_{IDX}" class="mt-1"></div>
            </div>

            <!-- PIECES -->
            <div class="col-md-1">
                <label class="form-label small">Pieces</label>
                <input type="number" name="hawb_pieces[]" id="pcs_{IDX}"
                       class="form-control form-control-sm text-center"
                       min="1" value="1" oninput="calcTotalPcs()">
            </div>

            <!-- PAYMENT TERM -->
            <div class="col-md-1">
                <label class="form-label small">Term</label>
                <select name="hawb_payment_term[]" class="form-select form-select-sm">
                    <option value="PP">PP</option>
                    <option value="CC">CC</option>
                </select>
            </div>

            <!-- COMMODITY -->
<div class="col-md-8">
    <label class="form-label small">Commodity / Description</label>
    <textarea name="hawb_commodity[]" class="form-control form-control-sm" rows="3"
              placeholder="e.g. 1PKG OF AUTO-FOCUSING COMPONENTS (SC1C87)&#10;INV SEMCO-OPT-260521-S2&#10;TERM CIF"></textarea>
</div>

<!-- NOTIFY PARTY -->
<div class="col-md-4">
    <label class="form-label small">Notify Party</label>
    <textarea name="hawb_notify_party[]" class="form-control form-control-sm" rows="3"
              placeholder="Optional — shown on weight slip"></textarea>
</div>

<!-- HANDLING INFORMATION ← MỚI -->
<div class="col-md-12">
    <label class="form-label small">
        Handling Information
        <span class="text-muted fw-normal">(if any)</span>
    </label>
    <input type="text" name="hawb_handling_info[]"
           class="form-control form-control-sm"
           placeholder="e.g. KEEP UPRIGHT · FRAGILE · DO NOT STACK · AS PER ATTACHED LIST">
</div>

        </div>
    </div>
</template>

<!-- ══════════════════════════════════════════════════
     SCRIPTS
═══════════════════════════════════════════════════ -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
// ── DATA ────────────────────────────────────────────────
const AIRLINES   = <?= json_encode($airlineList)   ?>;
const AIRPORTS   = <?= json_encode($airportList)   ?>;
const CUSTOMERS  = <?= json_encode($customerList)  ?>;
const SHIPPERS   = <?= json_encode($shipperList)   ?>;
const CONSIGNEES = <?= json_encode($consigneeList) ?>;

// Pre-build <option> HTML for HAWB template dropdowns (built once, reused per row)
const SHIPPER_OPTIONS = SHIPPERS.map(s =>
    `<option value="${s.id}" data-code="${s.code}" data-name="${s.name}">` +
    `${s.code} — ${s.name}</option>`
).join('');

const CONSIGNEE_OPTIONS = CONSIGNEES.map(c =>
    `<option value="${c.id}" data-code="${c.code}" data-name="${c.name}">` +
    `${c.code} — ${c.name}</option>`
).join('');

let hawkIdx = 0;

// ════════════════════════════════════════════════════════
// AUTOCOMPLETE ENGINE
// ════════════════════════════════════════════════════════
function acFilter(data, q, fields) {
    if (!q) return data.slice(0, 12);
    q = q.toLowerCase();
    return data.filter(r => fields.some(f => (r[f]||'').toLowerCase().includes(q))).slice(0, 12);
}

function renderDropdown(listEl, items, onSelect, renderFn) {
    listEl.innerHTML = '';
    if (!items.length) { listEl.classList.add('d-none'); return; }
    items.forEach((item, i) => {
        const div = document.createElement('div');
        div.className = 'ac-item' + (i === 0 ? ' active' : '');
        div.innerHTML = renderFn(item);
        div.addEventListener('mousedown', e => {
            e.preventDefault(); // prevent blur before click
            onSelect(item);
            listEl.classList.add('d-none');
        });
        listEl.appendChild(div);
    });
    listEl.classList.remove('d-none');
}

// Close all dropdowns on outside click
document.addEventListener('click', e => {
    document.querySelectorAll('.autocomplete-list').forEach(el => {
        if (!el.closest('.position-relative')?.contains(e.target))
            el.classList.add('d-none');
    });
});

// ════════════════════════════════════════════════════════
// AIRLINE — type search
// ════════════════════════════════════════════════════════
document.getElementById('airlineSearch').addEventListener('input', function () {
    const items = acFilter(AIRLINES, this.value, ['code', 'name']);
    renderDropdown(
        document.getElementById('airlineList'), items,
        item => applyAirline(item),
        item => `<span class="ac-code">${item.code}</span>
                 <span class="ac-name">${item.name}</span>
                 ${item.mawb_prefix
                    ? `<span class="badge bg-light text-dark border ms-2">${item.mawb_prefix}-</span>`
                    : ''}`
    );
});

function applyAirline(item) {
    document.getElementById('airline_id').value     = item.id;
    document.getElementById('airlineSearch').value  = item.code + ' — ' + item.name;
    document.getElementById('airlineSelect').value  = item.id;
    document.getElementById('mawbPrefix').textContent = item.mawb_prefix || '—';
    document.getElementById('airlineSelected').innerHTML =
        `<span class="badge bg-primary">${item.code}</span>
         <small class="text-muted ms-1">${item.name}</small>`;
    buildMawbNo();
}

// AIRLINE — dropdown select
function selectFromDropdown(type, sel) {
    const opt = sel.options[sel.selectedIndex];
    if (!opt.value) return;
    if (type === 'airline') {
        applyAirline({
            id: opt.value,
            code: opt.dataset.code,
            name: opt.dataset.name,
            mawb_prefix: opt.dataset.prefix
        });
    }
    if (type === 'customer') {
        document.getElementById('customer_id').value    = opt.value;
        document.getElementById('customerSearch').value = opt.dataset.code + ' — ' + opt.dataset.name;
        document.getElementById('customerSelect').value = opt.value;
        document.getElementById('customerSelected').innerHTML =
            `<span class="badge bg-warning text-dark">${opt.dataset.code}</span>
             <small class="text-muted ms-1">${opt.dataset.name}</small>`;
    }
}

// MAWB No builder
function buildMawbNo() {
    const prefix = document.getElementById('mawbPrefix').textContent.trim();
    const suffix = document.getElementById('mawbSuffix').value.trim();
    document.getElementById('mawb_no').value =
        (prefix && prefix !== '—' && suffix) ? prefix + '-' + suffix : '';
}
document.getElementById('mawbSuffix').addEventListener('input', buildMawbNo);

// ════════════════════════════════════════════════════════
// AIRPORT — type search
// ════════════════════════════════════════════════════════
function setupAirportSearch(inputId, hiddenId, listId, selectedId, selectId) {
    document.getElementById(inputId).addEventListener('input', function () {
        const items = acFilter(AIRPORTS, this.value, ['iata_code', 'name', 'city', 'country']);
        renderDropdown(
            document.getElementById(listId), items,
            item => applyAirport(item, hiddenId, inputId, selectedId, selectId),
            item => `<span class="ac-code">${item.iata_code}</span>
                     <span class="ac-name">${item.name}</span>
                     <small class="text-muted ms-1">${item.city || ''}</small>`
        );
    });
}

function applyAirport(item, hiddenId, inputId, selectedId, selectId) {
    document.getElementById(hiddenId).value  = item.id;
    document.getElementById(inputId).value   = item.iata_code + ' — ' + item.name;
    if (selectId) document.getElementById(selectId).value = item.id;
    document.getElementById(selectedId).innerHTML =
        `<span class="badge bg-info text-dark">${item.iata_code}</span>
         <small class="text-muted ms-1">${item.city || ''}, ${item.country || ''}</small>`;
}

// AIRPORT — dropdown select
function selectAirportDropdown(type, sel) {
    const opt = sel.options[sel.selectedIndex];
    if (!opt.value) return;
    const map = {
        origin: { hid:'origin_id',      inp:'originSearch', sel:'originSelect', dis:'originSelected' },
        dest:   { hid:'destination_id', inp:'destSearch',   sel:'destSelect',   dis:'destSelected'   },
    };
    const m = map[type];
    document.getElementById(m.hid).value = opt.value;
    document.getElementById(m.inp).value = opt.dataset.code + ' — ' + opt.dataset.name;
    document.getElementById(m.dis).innerHTML =
        `<span class="badge bg-info text-dark">${opt.dataset.code}</span>
         <small class="text-muted ms-1">${opt.dataset.city||''}, ${opt.dataset.country||''}</small>`;
}

setupAirportSearch('originSearch', 'origin_id',      'originList', 'originSelected', 'originSelect');
setupAirportSearch('destSearch',   'destination_id', 'destList',   'destSelected',   'destSelect');

// ════════════════════════════════════════════════════════
// CUSTOMER (MAWB Consignee) — type search
// ════════════════════════════════════════════════════════
document.getElementById('customerSearch').addEventListener('input', function () {
    const items = acFilter(CUSTOMERS, this.value, ['code', 'name']);
    renderDropdown(
        document.getElementById('customerList'), items,
        item => {
            document.getElementById('customer_id').value    = item.id;
            document.getElementById('customerSearch').value = item.code + ' — ' + item.name;
            document.getElementById('customerSelect').value = item.id;
            document.getElementById('customerSelected').innerHTML =
                `<span class="badge bg-warning text-dark">${item.code}</span>
                 <small class="text-muted ms-1">${item.name}</small>`;
        },
        item => `<span class="ac-code">${item.code}</span><span class="ac-name">${item.name}</span>`
    );
});

// ════════════════════════════════════════════════════════
// HAWB — Shipper / Consignee type search
// ════════════════════════════════════════════════════════
function acHawbSearch(inputEl, type, idx) {
    const data   = type === 'shipper' ? SHIPPERS : CONSIGNEES;
    const listId = type === 'shipper' ? `shipList_${idx}` : `cneeList_${idx}`;
    const hidId  = type === 'shipper' ? `ship_id_${idx}`  : `cnee_id_${idx}`;
    const selId  = type === 'shipper' ? `shipSel_${idx}`  : `cneeSel_${idx}`;
    const items  = acFilter(data, inputEl.value, ['code', 'name']);

    renderDropdown(
        document.getElementById(listId), items,
        item => {
            document.getElementById(hidId).value = item.id;
            inputEl.value = item.code + ' — ' + item.name;
            document.getElementById(selId).innerHTML =
                `<small class="text-muted">${item.name}</small>`;
        },
        item => `<span class="ac-code">${item.code}</span><span class="ac-name">${item.name}</span>`
    );
}

// HAWB — Shipper / Consignee dropdown select
function selectHawbDropdown(type, idx, sel) {
    const opt = sel.options[sel.selectedIndex];
    if (!opt.value) return;
    if (type === 'shipper') {
        document.getElementById('ship_id_'    + idx).value = opt.value;
        document.getElementById('shipSearch_' + idx).value = opt.dataset.code + ' — ' + opt.dataset.name;
        document.getElementById('shipSel_'    + idx).innerHTML =
            `<small class="text-muted">${opt.dataset.name}</small>`;
    } else {
        document.getElementById('cnee_id_'    + idx).value = opt.value;
        document.getElementById('cneeSearch_' + idx).value = opt.dataset.code + ' — ' + opt.dataset.name;
        document.getElementById('cneeSel_'    + idx).innerHTML =
            `<small class="text-muted">${opt.dataset.name}</small>`;
    }
}

// ════════════════════════════════════════════════════════
// ADD / REMOVE HAWB ROWS
// ════════════════════════════════════════════════════════
function suggestHawbNo(idx) {
    const el = document.getElementById('hawbNo_' + idx);
    if (el) el.placeholder = 'Đang tải...';
    fetch('<?= BASE_URL ?>api/next_hawb.php')
        .then(r => r.json())
        .then(data => { if (el) el.value = data.hawb_no; })
        .catch(() => { if (el) el.placeholder = 'Lỗi'; });
}

function addHawbRow() {
    const idx = hawkIdx++;
    const num = document.querySelectorAll('.hawb-row').length + 1;

    let html = document.getElementById('hawbTemplate').innerHTML
        .replace(/{IDX}/g, idx)
        .replace(/{NUM}/g, num)
        .replace('__SHIPPER_OPTIONS__',   SHIPPER_OPTIONS)
        .replace('__CONSIGNEE_OPTIONS__', CONSIGNEE_OPTIONS);

    document.getElementById('hawbContainer').insertAdjacentHTML('beforeend', html);
    document.getElementById('noHawbMsg').style.display = 'none';
    renumberHawbs();
    calcTotalPcs();
    suggestHawbNo(idx);

    // Focus first input of new row
    document.getElementById('shipSearch_' + idx)?.focus();
}

function removeHawb(idx) {
    document.getElementById('hawbRow_' + idx)?.remove();
    const rows = document.querySelectorAll('.hawb-row');
    if (!rows.length) document.getElementById('noHawbMsg').style.display = '';
    renumberHawbs();
    calcTotalPcs();
}

function renumberHawbs() {
    document.querySelectorAll('.hawb-num').forEach((el, i) => {
        el.textContent = 'HAWB #' + (i + 1);
    });
}

function calcTotalPcs() {
    let total = 0;
    document.querySelectorAll('[name="hawb_pieces[]"]').forEach(el => {
        total += parseInt(el.value) || 0;
    });
    document.getElementById('totalPcsDisplay').textContent = total;
}

// ════════════════════════════════════════════════════════
// FORM VALIDATION
// ════════════════════════════════════════════════════════
document.getElementById('manifestForm').addEventListener('submit', function (e) {
    const checks = [
        { id: 'airline_id',      label: 'Airline' },
        { id: 'mawb_no',         label: 'MAWB No' },
        { id: 'origin_id',       label: 'Origin Airport' },
        { id: 'destination_id',  label: 'Destination Airport' },
    ];
    const missing = checks.filter(c => !document.getElementById(c.id)?.value);
    if (missing.length) {
        e.preventDefault();
        alert('Please fill in required fields:\n' + missing.map(c => '• ' + c.label).join('\n'));
        return;
    }
    if (!document.querySelectorAll('.hawb-row').length) {
        if (!confirm('No HAWBs added. Save manifest without any HAWB?')) {
            e.preventDefault();
        }
    }
});

// ── INIT ────────────────────────────────────────────────
addHawbRow(); // add first HAWB row automatically

setTimeout(() => {
    document.querySelectorAll('.alert-dismissible').forEach(el =>
        bootstrap.Alert.getOrCreateInstance(el).close());
}, 5000);
</script>
</body>
</html>