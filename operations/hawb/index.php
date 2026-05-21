<?php
require_once __DIR__ . '/../../config/constants.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';
requireLogin();

$db = getDB();

// ── FILTERS ──────────────────────────────────────────────
$search     = trim($_GET['search']    ?? '');
$filterDate = trim($_GET['date']      ?? '');
$filterDest = trim($_GET['dest']      ?? '');
$filterTerm = trim($_GET['term']      ?? '');
$filterWeigh= trim($_GET['weighed']   ?? '');
$page       = max(1, (int)($_GET['page'] ?? 1));
$perPage    = 20;
$offset     = ($page - 1) * $perPage;

// ── BUILD WHERE ───────────────────────────────────────────
$where  = ['1=1'];
$params = [];
$types  = '';

if ($search) {
    $where[]  = "(h.hawb_no LIKE ? OR s.name LIKE ? OR s.code LIKE ?
                  OR cn.name LIKE ? OR cn.code LIKE ?
                  OR h.commodity LIKE ? OR m.mawb_no LIKE ?)";
    $like     = "%$search%";
    $params   = array_merge($params, [$like,$like,$like,$like,$like,$like,$like]);
    $types   .= 'sssssss';
}
if ($filterDate) {
    $where[]  = "m.flight_date = ?";
    $params[] = $filterDate;
    $types   .= 's';
}
if ($filterDest) {
    $where[]  = "ap2.iata_code = ?";
    $params[] = $filterDest;
    $types   .= 's';
}
if ($filterTerm) {
    $where[]  = "h.payment_term = ?";
    $params[] = $filterTerm;
    $types   .= 's';
}
if ($filterWeigh !== '') {
    $where[]  = "h.is_weighed = ?";
    $params[] = (int)$filterWeigh;
    $types   .= 'i';
}

$whereSQL = implode(' AND ', $where);

// ── COUNT ─────────────────────────────────────────────────
$countSQL = "
    SELECT COUNT(*)
    FROM hawbs h
    LEFT JOIN shippers   s   ON h.shipper_id    = s.id
    LEFT JOIN consignees cn  ON h.consignee_id  = cn.id
    LEFT JOIN airports   ap2 ON h.destination_id= ap2.id
    LEFT JOIN manifests  m   ON h.manifest_id   = m.id
    WHERE $whereSQL
";
if ($params) {
    $cstmt = $db->prepare($countSQL);
    $cstmt->bind_param($types, ...$params);
    $cstmt->execute();
    $totalRows = $cstmt->get_result()->fetch_row()[0];
    $cstmt->close();
} else {
    $totalRows = $db->query($countSQL)->fetch_row()[0];
}
$totalPages = max(1, ceil($totalRows / $perPage));

// ── DATA ──────────────────────────────────────────────────
$dataSQL = "
    SELECT h.*,
           s.code  AS shipper_code,  s.name AS shipper_name,
           cn.code AS cnee_code,     cn.name AS cnee_name,
           ap1.iata_code AS origin_code,
           ap2.iata_code AS dest_code,
           m.mawb_no, m.flight_no, m.flight_date, m.status AS manifest_status,
           al.code AS airline_code
    FROM hawbs h
    LEFT JOIN shippers   s   ON h.shipper_id    = s.id
    LEFT JOIN consignees cn  ON h.consignee_id  = cn.id
    LEFT JOIN airports   ap1 ON h.origin_id     = ap1.id
    LEFT JOIN airports   ap2 ON h.destination_id= ap2.id
    LEFT JOIN manifests  m   ON h.manifest_id   = m.id
    LEFT JOIN airlines   al  ON m.airline_id    = al.id
    WHERE $whereSQL
    ORDER BY h.id DESC
    LIMIT $perPage OFFSET $offset
";
if ($params) {
    $dstmt = $db->prepare($dataSQL);
    $dstmt->bind_param($types, ...$params);
    $dstmt->execute();
    $hawbs = $dstmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $dstmt->close();
} else {
    $hawbs = $db->query($dataSQL)->fetch_all(MYSQLI_ASSOC);
}

// ── DESTINATION list for filter ───────────────────────────
$destList = $db->query("
    SELECT DISTINCT ap.iata_code, ap.name
    FROM hawbs h
    JOIN airports ap ON h.destination_id = ap.id
    ORDER BY ap.iata_code
")->fetch_all(MYSQLI_ASSOC);

// ── STATS cards ───────────────────────────────────────────
$stats = $db->query("
    SELECT
        COUNT(*)                            AS total,
        SUM(is_weighed = 0)                 AS pending,
        SUM(is_weighed = 1)                 AS weighed,
        COALESCE(SUM(gross_weight),0)       AS total_gw,
        COALESCE(SUM(chargeable_weight),0)  AS total_cw,
        COALESCE(SUM(no_of_pieces),0)       AS total_pcs
    FROM hawbs
")->fetch_assoc();

$pageTitle = 'HAWB Bills';
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
        .stat-card {
            border-radius: 14px;
            padding: 1rem 1.25rem;
            border: 1px solid #e9ecef;
            background: #fff;
            transition: box-shadow .15s;
        }
        .stat-card:hover { box-shadow: 0 4px 16px rgba(0,0,0,.08); }
        .stat-icon {
            width: 44px; height: 44px;
            border-radius: 12px;
            display: flex; align-items: center; justify-content: center;
            font-size: 1.3rem; flex-shrink: 0;
        }
        .stat-val  { font-size: 1.5rem; font-weight: 800; line-height: 1; }
        .stat-lbl  { font-size: .75rem; color: #6c757d; margin-top: 2px; }

        .hawb-no-link {
            font-weight: 700; color: #0d6efd;
            text-decoration: none; font-size: .85rem;
        }
        .hawb-no-link:hover { text-decoration: underline; }

        .filter-bar { background:#fff; border-radius:12px;
                      border:1px solid #e9ecef; padding:1rem 1.25rem; }

        .badge-weighed   { background:#d1fae5; color:#065f46; border:1px solid #6ee7b7; }
        .badge-pending   { background:#fef3c7; color:#92400e; border:1px solid #fcd34d; }

        .table > :not(caption) > * > * { padding: .55rem .65rem; }
        .table thead th {
            background: #f8f9fa; font-size: .75rem;
            text-transform: uppercase; letter-spacing: .04em;
            font-weight: 700; white-space: nowrap;
        }
        .text-truncate-20 {
            max-width: 160px; overflow: hidden;
            text-overflow: ellipsis; white-space: nowrap;
            display: inline-block;
        }
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

<!-- Page header -->
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="fw-bold mb-0">
            <i class="bi bi-card-list text-primary me-2"></i>HAWB Bills
        </h4>
        <small class="text-muted">Tất cả House Air Waybill Bills</small>
    </div>
    <a href="<?= BASE_URL ?>operations/manifest/create.php"
       class="btn btn-success">
        <i class="bi bi-plus-circle me-1"></i>New Manifest
    </a>
</div>

<!-- ── STATS ─────────────────────────────────────────── -->
<div class="row g-3 mb-4">
    <div class="col-6 col-md-4 col-xl-2">
        <div class="stat-card d-flex align-items-center gap-3">
            <div class="stat-icon" style="background:#dbeafe;">
                <i class="bi bi-card-list text-primary"></i>
            </div>
            <div>
                <div class="stat-val"><?= number_format($stats['total']) ?></div>
                <div class="stat-lbl">Total HAWBs</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-4 col-xl-2">
        <div class="stat-card d-flex align-items-center gap-3">
            <div class="stat-icon" style="background:#fef3c7;">
                <i class="bi bi-hourglass-split text-warning"></i>
            </div>
            <div>
                <div class="stat-val text-warning"><?= number_format($stats['pending']) ?></div>
                <div class="stat-lbl">Pending Weigh</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-4 col-xl-2">
        <div class="stat-card d-flex align-items-center gap-3">
            <div class="stat-icon" style="background:#d1fae5;">
                <i class="bi bi-check-circle text-success"></i>
            </div>
            <div>
                <div class="stat-val text-success"><?= number_format($stats['weighed']) ?></div>
                <div class="stat-lbl">Weighed</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-4 col-xl-2">
        <div class="stat-card d-flex align-items-center gap-3">
            <div class="stat-icon" style="background:#ede9fe;">
                <i class="bi bi-boxes text-purple" style="color:#7c3aed;"></i>
            </div>
            <div>
                <div class="stat-val"><?= number_format($stats['total_pcs']) ?></div>
                <div class="stat-lbl">Total Pieces</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-4 col-xl-2">
        <div class="stat-card d-flex align-items-center gap-3">
            <div class="stat-icon" style="background:#fce7f3;">
                <i class="bi bi-speedometer2" style="color:#db2777;"></i>
            </div>
            <div>
                <div class="stat-val" style="font-size:1.1rem;">
                    <?= number_format($stats['total_gw'],1) ?>
                </div>
                <div class="stat-lbl">Total GW (kg)</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-4 col-xl-2">
        <div class="stat-card d-flex align-items-center gap-3">
            <div class="stat-icon" style="background:#d1fae5;">
                <i class="bi bi-lightning-charge text-success"></i>
            </div>
            <div>
                <div class="stat-val text-success" style="font-size:1.1rem;">
                    <?= number_format($stats['total_cw'],1) ?>
                </div>
                <div class="stat-lbl">Total CW (kg)</div>
            </div>
        </div>
    </div>
</div>

<!-- ── FILTER BAR ─────────────────────────────────────── -->
<form method="GET" class="filter-bar mb-3">
    <div class="row g-2 align-items-end">
        <!-- Search -->
        <div class="col-md-3">
            <label class="form-label small fw-semibold mb-1">
                <i class="bi bi-search me-1"></i>Search
            </label>
            <input type="text" name="search" class="form-control form-control-sm"
                   placeholder="HAWB No, Shipper, Consignee, MAWB..."
                   value="<?= e($search) ?>">
        </div>
        <!-- Flight Date -->
        <div class="col-md-2">
            <label class="form-label small fw-semibold mb-1">
                <i class="bi bi-calendar3 me-1"></i>Flight Date
            </label>
            <input type="date" name="date" class="form-control form-control-sm"
                   value="<?= e($filterDate) ?>">
        </div>
        <!-- Destination -->
        <div class="col-md-2">
            <label class="form-label small fw-semibold mb-1">
                <i class="bi bi-geo-alt me-1"></i>Destination
            </label>
            <select name="dest" class="form-select form-select-sm">
                <option value="">All Destinations</option>
                <?php foreach ($destList as $d): ?>
                <option value="<?= e($d['iata_code']) ?>"
                        <?= $filterDest === $d['iata_code'] ? 'selected' : '' ?>>
                    <?= e($d['iata_code']) ?> — <?= e($d['name']) ?>
                </option>
                <?php endforeach; ?>
            </select>
        </div>
        <!-- Payment Term -->
        <div class="col-md-1">
            <label class="form-label small fw-semibold mb-1">Term</label>
            <select name="term" class="form-select form-select-sm">
                <option value="">All</option>
                <option value="PP" <?= $filterTerm==='PP'?'selected':'' ?>>PP</option>
                <option value="CC" <?= $filterTerm==='CC'?'selected':'' ?>>CC</option>
            </select>
        </div>
        <!-- Weigh status -->
        <div class="col-md-2">
            <label class="form-label small fw-semibold mb-1">Weigh Status</label>
            <select name="weighed" class="form-select form-select-sm">
                <option value="">All</option>
                <option value="0" <?= $filterWeigh==='0'?'selected':'' ?>>⏳ Pending</option>
                <option value="1" <?= $filterWeigh==='1'?'selected':'' ?>>✅ Weighed</option>
            </select>
        </div>
        <!-- Buttons -->
        <div class="col-md-2 d-flex gap-2">
            <button type="submit" class="btn btn-primary btn-sm flex-grow-1">
                <i class="bi bi-funnel me-1"></i>Filter
            </button>
            <a href="index.php" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-x"></i>
            </a>
        </div>
    </div>
</form>

<!-- ── TABLE ──────────────────────────────────────────── -->
<div class="card">
    <div class="card-header py-2 d-flex justify-content-between align-items-center">
        <span class="fw-semibold small">
            <?php if ($search || $filterDate || $filterDest || $filterTerm || $filterWeigh !== ''): ?>
                <span class="text-primary">
                    <i class="bi bi-funnel-fill me-1"></i>
                    <?= number_format($totalRows) ?> result(s) found
                </span>
            <?php else: ?>
                <i class="bi bi-card-list me-1 text-muted"></i>
                <?= number_format($totalRows) ?> HAWB(s) total
            <?php endif; ?>
        </span>
        <span class="text-muted small">
            Page <?= $page ?> / <?= $totalPages ?>
        </span>
    </div>

    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead>
                <tr>
                    <th class="ps-3" width="12%">HAWB No</th>
                    <th width="12%">MAWB / Flight</th>
                    <th width="13%">Shipper</th>
                    <th width="13%">Consignee</th>
                    <th width="7%">Route</th>
                    <th class="text-center" width="5%">Pcs</th>
                    <th class="text-center" width="6%">GW (kg)</th>
                    <th class="text-center" width="6%">CW (kg)</th>
                    <th class="text-center" width="5%">Term</th>
                    <th class="text-center" width="8%">Status</th>
                    <th class="text-end pe-3" width="13%">Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php if (!$hawbs): ?>
            <tr>
                <td colspan="11" class="text-center py-5 text-muted">
                    <i class="bi bi-inbox fs-2 d-block mb-2 opacity-25"></i>
                    No HAWBs found.
                    <?php if ($search || $filterDate || $filterDest): ?>
                    <br>
                    <a href="index.php" class="btn btn-sm btn-outline-secondary mt-2">
                        Clear filters
                    </a>
                    <?php endif; ?>
                </td>
            </tr>
            <?php else: ?>
            <?php foreach ($hawbs as $h): ?>
            <tr>
                <!-- HAWB No -->
                <td class="ps-3">
                    <a href="<?= BASE_URL ?>operations/manifest/edit.php?id=<?= $h['manifest_id'] ?>&weigh=<?= $h['id'] ?>"
                       class="hawb-no-link">
                        <?= e($h['hawb_no']) ?>
                    </a>
                    <?php if ($h['manifest_status'] === 'draft'): ?>
                    <br><span class="badge bg-secondary" style="font-size:.6rem;">Draft</span>
                    <?php endif; ?>
                </td>

                <!-- MAWB / Flight -->
                <td>
                    <a href="<?= BASE_URL ?>operations/manifest/edit.php?id=<?= $h['manifest_id'] ?>"
                       class="text-decoration-none text-dark fw-semibold small">
                        <?= e($h['mawb_no'] ?? '—') ?>
                    </a>
                    <br>
                    <small class="text-muted">
                        <?= e($h['airline_code'] ?? '') ?>
                        <?= e($h['flight_no'] ?? '') ?>
                        <?php if ($h['flight_date']): ?>
                        · <?= date('d-M', strtotime($h['flight_date'])) ?>
                        <?php endif; ?>
                    </small>
                </td>

                <!-- Shipper -->
                <td>
                    <div class="fw-semibold small"><?= e($h['shipper_code'] ?? '—') ?></div>
                    <small class="text-muted">
                        <span class="text-truncate-20" title="<?= e($h['shipper_name'] ?? '') ?>">
                            <?= e(substr($h['shipper_name'] ?? '', 0, 25)) ?>
                        </span>
                    </small>
                </td>

                <!-- Consignee -->
                <td>
                    <div class="fw-semibold small"><?= e($h['cnee_code'] ?? '—') ?></div>
                    <small class="text-muted">
                        <span class="text-truncate-20" title="<?= e($h['cnee_name'] ?? '') ?>">
                            <?= e(substr($h['cnee_name'] ?? '', 0, 25)) ?>
                        </span>
                    </small>
                </td>

                <!-- Route -->
                <td class="text-center">
                    <span class="badge bg-light text-dark border" style="font-size:.75rem;font-weight:700;">
                        <?= e($h['origin_code'] ?? '?') ?>→<?= e($h['dest_code'] ?? '?') ?>
                    </span>
                </td>

                <!-- Pieces -->
                <td class="text-center">
                    <span class="badge bg-secondary"><?= (int)$h['no_of_pieces'] ?></span>
                </td>

                <!-- GW -->
                <td class="text-center small">
                    <?= $h['gross_weight'] > 0 ? number_format($h['gross_weight'],1) : '—' ?>
                </td>

                <!-- CW -->
                <td class="text-center small fw-bold <?= $h['chargeable_weight']>0?'text-success':'' ?>">
                    <?= $h['chargeable_weight'] > 0 ? number_format($h['chargeable_weight'],1) : '—' ?>
                </td>

                <!-- Term -->
                <td class="text-center">
                    <span class="badge bg-<?= $h['payment_term']==='PP'?'primary':'warning text-dark' ?>">
                        <?= e($h['payment_term'] ?? 'PP') ?>
                    </span>
                </td>

                <!-- Weigh Status -->
                <td class="text-center">
                    <?php if ($h['is_weighed']): ?>
                    <span class="badge badge-weighed small">
                        <i class="bi bi-check-circle me-1"></i>Weighed
                    </span>
                    <?php if ($h['weighed_at']): ?>
                    <br><small class="text-muted" style="font-size:.65rem;">
                        <?= date('d-M H:i', strtotime($h['weighed_at'])) ?>
                    </small>
                    <?php endif; ?>
                    <?php else: ?>
                    <span class="badge badge-pending small">
                        <i class="bi bi-hourglass-split me-1"></i>Pending
                    </span>
                    <?php endif; ?>
                </td>

                <!-- Actions -->
                <td class="text-end pe-3">
                    <div class="d-flex gap-1 justify-content-end flex-wrap">

                        <!-- GW/CW -->
                        <?php if (in_array($h['manifest_status'], ['confirmed','completed'])): ?>
                        <a href="<?= BASE_URL ?>operations/manifest/edit.php?id=<?= $h['manifest_id'] ?>&weigh=<?= $h['id'] ?>"
                           class="btn btn-sm <?= $h['is_weighed']?'btn-outline-success':'btn-outline-warning' ?>"
                           style="font-size:.7rem;font-weight:700;padding:2px 7px;"
                           title="<?= $h['is_weighed']?'Edit Weight':'Weigh' ?>">
                            GW/CW
                        </a>
                        <?php endif; ?>

                        <!-- Print HAWB -->
                        <a href="<?= BASE_URL ?>print/hawb_print.php?id=<?= $h['id'] ?>"
                           target="_blank"
                           class="btn btn-sm btn-outline-secondary"
                           title="Print HAWB A4">
                            <i class="bi bi-printer"></i>
                        </a>

                        <!-- Excel -->
                        <a href="<?= BASE_URL ?>print/hawb_excel.php?id=<?= $h['id'] ?>"
                           class="btn btn-sm btn-outline-success"
                           title="Download Excel">
                            <i class="bi bi-file-earmark-excel"></i>
                        </a>

                        <!-- Label -->
                        <a href="<?= BASE_URL ?>print/label_print.php?hawb_id=<?= $h['id'] ?>"
                           target="_blank"
                           class="btn btn-sm btn-outline-warning"
                           title="Print Label">
                            <i class="bi bi-tag"></i>
                        </a>

                        <!-- View manifest -->
                        <a href="<?= BASE_URL ?>operations/manifest/edit.php?id=<?= $h['manifest_id'] ?>"
                           class="btn btn-sm btn-outline-primary"
                           title="Open Manifest">
                            <i class="bi bi-folder2-open"></i>
                        </a>
<!-- Edit HAWB -->
<a href="<?= BASE_URL ?>operations/hawb/edit.php?id=<?= $h['id'] ?>"
   class="btn btn-sm btn-outline-primary"
   title="Edit HAWB">
    <i class="bi bi-pencil-square"></i>
</a>
                    </div>
                </td>
            </tr>
            <?php endforeach; ?>
            <?php endif; ?>
            </tbody>

            <!-- Footer totals -->
            <?php if ($hawbs): ?>
            <tfoot class="table-light">
                <tr>
                    <td colspan="5" class="ps-3 small fw-bold text-muted">
                        Page total (<?= count($hawbs) ?> rows)
                    </td>
                    <td class="text-center fw-bold">
                        <?= array_sum(array_column($hawbs,'no_of_pieces')) ?>
                    </td>
                    <td class="text-center fw-bold">
                        <?= number_format(array_sum(array_column($hawbs,'gross_weight')),1) ?>
                    </td>
                    <td class="text-center fw-bold text-success">
                        <?= number_format(array_sum(array_column($hawbs,'chargeable_weight')),1) ?>
                    </td>
                    <td colspan="3"></td>
                </tr>
            </tfoot>
            <?php endif; ?>
        </table>
    </div>
</div>

<!-- ── PAGINATION ─────────────────────────────────────── -->
<?php if ($totalPages > 1): ?>
<nav class="mt-3 d-flex justify-content-between align-items-center">
    <small class="text-muted">
        Showing <?= number_format(($page-1)*$perPage+1) ?>–<?= number_format(min($page*$perPage,$totalRows)) ?>
        of <?= number_format($totalRows) ?> HAWBs
    </small>
    <ul class="pagination pagination-sm mb-0">
        <!-- Prev -->
        <li class="page-item <?= $page<=1?'disabled':'' ?>">
            <a class="page-link" href="?<?= http_build_query(array_merge($_GET,['page'=>$page-1])) ?>">
                <i class="bi bi-chevron-left"></i>
            </a>
        </li>
        <?php
        $start = max(1, $page-2);
        $end   = min($totalPages, $page+2);
        if ($start > 1): ?>
        <li class="page-item">
            <a class="page-link" href="?<?= http_build_query(array_merge($_GET,['page'=>1])) ?>">1</a>
        </li>
        <?php if ($start > 2): ?>
        <li class="page-item disabled"><span class="page-link">…</span></li>
        <?php endif; endif; ?>

        <?php for ($i=$start; $i<=$end; $i++): ?>
        <li class="page-item <?= $i===$page?'active':'' ?>">
            <a class="page-link" href="?<?= http_build_query(array_merge($_GET,['page'=>$i])) ?>">
                <?= $i ?>
            </a>
        </li>
        <?php endfor; ?>

        <?php if ($end < $totalPages): ?>
        <?php if ($end < $totalPages-1): ?>
        <li class="page-item disabled"><span class="page-link">…</span></li>
        <?php endif; ?>
        <li class="page-item">
            <a class="page-link" href="?<?= http_build_query(array_merge($_GET,['page'=>$totalPages])) ?>">
                <?= $totalPages ?>
            </a>
        </li>
        <?php endif; ?>

        <!-- Next -->
        <li class="page-item <?= $page>=$totalPages?'disabled':'' ?>">
            <a class="page-link" href="?<?= http_build_query(array_merge($_GET,['page'=>$page+1])) ?>">
                <i class="bi bi-chevron-right"></i>
            </a>
        </li>
    </ul>
</nav>
<?php endif; ?>

</main>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Auto submit on select change
document.querySelectorAll('select[name="dest"], select[name="term"], select[name="weighed"]')
    .forEach(el => el.addEventListener('change', () => el.closest('form').submit()));

// Auto dismiss alerts
setTimeout(() => {
    document.querySelectorAll('.alert-dismissible').forEach(el =>
        bootstrap.Alert.getOrCreateInstance(el).close());
}, 5000);
</script>
</body>
</html>