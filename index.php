<?php
require_once 'config/constants.php';
require_once 'config/database.php';
require_once 'includes/functions.php';
require_once 'config/session.php';
require_once 'includes/auth.php';
requireLogin();

$pageTitle = 'Dashboard';
$db  = getDB();
$uid = currentUserId();

// ===== STATS =====
$stats = [];
$stats['total_manifests'] = $db->query("SELECT COUNT(*) FROM manifests")->fetch_row()[0] ?? 0;
$stats['draft']           = $db->query("SELECT COUNT(*) FROM manifests WHERE status='draft'")->fetch_row()[0] ?? 0;
$stats['confirmed']       = $db->query("SELECT COUNT(*) FROM manifests WHERE status='confirmed'")->fetch_row()[0] ?? 0;
$stats['completed']       = $db->query("SELECT COUNT(*) FROM manifests WHERE status='completed'")->fetch_row()[0] ?? 0;
$stats['this_month']      = $db->query("SELECT COUNT(*) FROM manifests WHERE YEAR(created_at)=YEAR(NOW()) AND MONTH(created_at)=MONTH(NOW())")->fetch_row()[0] ?? 0;
$stats['last_month']      = $db->query("SELECT COUNT(*) FROM manifests WHERE YEAR(created_at)=YEAR(DATE_SUB(NOW(),INTERVAL 1 MONTH)) AND MONTH(created_at)=MONTH(DATE_SUB(NOW(),INTERVAL 1 MONTH))")->fetch_row()[0] ?? 0;
$stats['total_hawbs']     = $db->query("SELECT COUNT(*) FROM hawbs")->fetch_row()[0] ?? 0;
$stats['pending_weigh']   = $db->query("SELECT COUNT(*) FROM hawbs h JOIN manifests m ON h.manifest_id=m.id WHERE h.is_weighed=0 AND m.status='confirmed'")->fetch_row()[0] ?? 0;
$stats['gw_month']        = $db->query("SELECT COALESCE(SUM(h.gross_weight),0) FROM hawbs h JOIN manifests m ON h.manifest_id=m.id WHERE YEAR(m.flight_date)=YEAR(NOW()) AND MONTH(m.flight_date)=MONTH(NOW())")->fetch_row()[0] ?? 0;
$stats['cw_month']        = $db->query("SELECT COALESCE(SUM(h.chargeable_weight),0) FROM hawbs h JOIN manifests m ON h.manifest_id=m.id WHERE YEAR(m.flight_date)=YEAR(NOW()) AND MONTH(m.flight_date)=MONTH(NOW())")->fetch_row()[0] ?? 0;

// ===== USER INFO =====
$me = $db->query("SELECT full_name, email, role FROM users WHERE id=$uid")->fetch_assoc();

// ===== CHART 7 NGÀY =====
$chart_data = [];
for ($i = 6; $i >= 0; $i--) {
    $date  = date('Y-m-d', strtotime("-$i days"));
    $label = date('d/m',   strtotime("-$i days"));
    $count = $db->query("SELECT COUNT(*) FROM manifests WHERE DATE(created_at)='$date'")->fetch_row()[0] ?? 0;
    $chart_data[] = ['label' => $label, 'count' => (int)$count];
}

// ===== RECENT MANIFESTS =====
$recent = $db->query("
    SELECT m.id, m.mawb_no, m.flight_no, m.flight_date, m.status,
           m.total_pieces, m.total_gw,
           al.code    AS airline_code,
           ap1.iata_code AS origin_code,
           ap2.iata_code AS dest_code,
           c.name     AS customer_name,
           u.full_name AS created_by_name,
           COUNT(h.id) AS hawb_count
    FROM manifests m
    LEFT JOIN airlines  al  ON m.airline_id      = al.id
    LEFT JOIN airports  ap1 ON m.origin_id        = ap1.id
    LEFT JOIN airports  ap2 ON m.destination_id   = ap2.id
    LEFT JOIN customers c   ON m.customer_id      = c.id
    LEFT JOIN users     u   ON m.created_by       = u.id
    LEFT JOIN hawbs     h   ON m.id               = h.manifest_id
    GROUP BY m.id
    ORDER BY m.created_at DESC LIMIT 10
");

// ===== UPCOMING FLIGHTS =====
$upcoming = $db->query("
    SELECT m.id, m.mawb_no, m.flight_no, m.flight_date, m.status,
           ap1.iata_code AS origin_code,
           ap2.iata_code AS dest_code,
           c.name AS customer_name
    FROM manifests m
    LEFT JOIN airports  ap1 ON m.origin_id      = ap1.id
    LEFT JOIN airports  ap2 ON m.destination_id = ap2.id
    LEFT JOIN customers c   ON m.customer_id    = c.id
    WHERE m.flight_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)
      AND m.status != 'completed'
    ORDER BY m.flight_date ASC LIMIT 8
");

// ===== MONTH CHANGE =====
$monthChange = $stats['last_month'] > 0
    ? round(($stats['this_month'] - $stats['last_month']) / $stats['last_month'] * 100, 1)
    : ($stats['this_month'] > 0 ? 100 : 0);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard | <?= APP_NAME ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    <link href="assets/css/app.css" rel="stylesheet">
    <style>
        body { background: #f0f2f5; }

        /* ===== WELCOME BANNER ===== */
        .welcome-banner {
            background: linear-gradient(135deg, #1a56db 0%, #1e40af 55%, #1e3a8a 100%);
            border-radius: 20px;
            min-height: 33vh;
            display: flex;
            align-items: center;
            padding: 2.5rem 3rem;
            box-shadow: 0 12px 40px rgba(26,86,219,.30);
            position: relative;
            overflow: hidden;
            margin-bottom: 2rem;
        }
        .welcome-banner::before {
            content:''; position:absolute; top:-60px; right:-60px;
            width:280px; height:280px;
            background:rgba(255,255,255,.06); border-radius:50%;
        }
        .welcome-banner::after {
            content:''; position:absolute; bottom:-80px; left:30%;
            width:220px; height:220px;
            background:rgba(255,255,255,.04); border-radius:50%;
        }
        .wb-inner { position:relative; z-index:1; width:100%; }
        .wb-date  { font-size:.85rem; color:rgba(255,255,255,.7); letter-spacing:.4px; margin-bottom:.6rem; }
        .welcome-banner h2 { font-size:2.2rem; font-weight:800; color:#fff; margin-bottom:.5rem; line-height:1.2; }
        .wb-meta  { font-size:.9rem; color:rgba(255,255,255,.7); display:flex; align-items:center; gap:.75rem; flex-wrap:wrap; }
        .wb-meta .sep { opacity:.4; }

        .wb-stats { display:flex; gap:1.2rem; margin-top:1.8rem; flex-wrap:wrap; }
        .wb-stat-item {
            background:rgba(255,255,255,.12);
            border:1px solid rgba(255,255,255,.18);
            border-radius:12px; padding:.75rem 1.25rem;
            text-align:center; min-width:100px;
            backdrop-filter:blur(4px);
        }
        .wb-stat-item .val { font-size:1.7rem; font-weight:800; color:#fff; line-height:1; }
        .wb-stat-item .lbl { font-size:.7rem; color:rgba(255,255,255,.65); text-transform:uppercase; letter-spacing:.5px; margin-top:4px; }

        .wb-actions { display:flex; flex-direction:column; gap:.75rem; align-items:flex-end; }
        .wb-actions .btn { font-weight:600; padding:.65rem 1.5rem; border-radius:10px; font-size:.9rem; white-space:nowrap; }
        .btn-amber { background:#f59e0b; border:none; color:#fff; font-weight:700; }
        .btn-amber:hover { background:#d97706; color:#fff; }

        /* ===== STAT CARDS ===== */
        .stat-card { transition:transform .15s, box-shadow .15s; border:none !important; border-radius:14px !important; }
        .stat-card:hover { transform:translateY(-3px); box-shadow:0 .5rem 1.5rem rgba(0,0,0,.1) !important; }
        .stat-icon { width:52px; height:52px; border-radius:14px; display:flex; align-items:center; justify-content:center; font-size:1.4rem; }
        .info-label { font-size:.7rem; font-weight:700; text-transform:uppercase; color:#6c757d; letter-spacing:.5px; margin-top:2px; }

        /* ===== CARDS ===== */
        .card { border:none !important; border-radius:14px !important; box-shadow:0 1px 6px rgba(0,0,0,.07) !important; }
        .card-header { background:#fff !important; border-bottom:1px solid #f0f0f0 !important; border-radius:14px 14px 0 0 !important; }

        /* ===== TABLE ===== */
        .table th { font-size:.72rem; text-transform:uppercase; letter-spacing:.05em; background:#f8f9fa; color:#6c757d; border:none; }
        .table td { font-size:.875rem; vertical-align:middle; }
        .table-hover tbody tr:hover { background:#f0f4ff; cursor:pointer; }

        /* ===== PENDING ALERT ===== */
        .pending-pill {
            background:linear-gradient(135deg,#fff3cd,#ffe69c);
            border:1px solid #ffc107;
            border-radius:12px; padding:.6rem 1rem;
            font-size:.85rem; font-weight:600; color:#856404;
        }
    </style>
</head>
<body>

<!-- TOP NAVBAR -->
<nav class="navbar navbar-dark px-3 fixed-top" style="height:56px;background:#1a1d23;z-index:1050;">
    <div class="d-flex align-items-center gap-2">
        <button class="btn btn-sm btn-outline-secondary d-lg-none" id="sidebarToggle">
            <i class="bi bi-list"></i>
        </button>
        <a class="navbar-brand fw-bold d-flex align-items-center gap-2" href="<?= BASE_URL ?>index.php">
            <img src="<?= BASE_URL ?>assets/img/logo.png" alt="" height="32" onerror="this.style.display='none'">
            <span class="text-white"><?= APP_NAME ?></span>
        </a>
    </div>
    <div class="d-flex align-items-center gap-3">
        <span class="text-white-50 small d-none d-md-inline">
            <i class="bi bi-person-circle me-1"></i>
            <?= e(currentUserName()) ?>
            <span class="badge bg-secondary ms-1"><?= ucfirst(currentUserRole()) ?></span>
        </span>
        <div class="dropdown">
            <button class="btn btn-sm btn-outline-light dropdown-toggle" data-bs-toggle="dropdown">
                <i class="bi bi-gear"></i>
            </button>
            <ul class="dropdown-menu dropdown-menu-end shadow">
                <li><a class="dropdown-item" href="<?= BASE_URL ?>profile.php"><i class="bi bi-person me-2"></i>Profile</a></li>
                <li><a class="dropdown-item" href="<?= BASE_URL ?>change-password.php"><i class="bi bi-key me-2"></i>Change Password</a></li>
                <li><hr class="dropdown-divider"></li>
                <li><a class="dropdown-item text-danger" href="<?= BASE_URL ?>logout.php"><i class="bi bi-box-arrow-right me-2"></i>Logout</a></li>
            </ul>
        </div>
    </div>
</nav>

<div class="d-flex" style="margin-top:56px;min-height:calc(100vh - 56px);">

<!-- SIDEBAR -->
<?php require_once 'includes/sidebar.php'; ?>

<!-- MAIN -->
<main class="flex-grow-1 p-4" id="mainContent">

    <!-- Flash -->
    <?php $flash = getFlash(); if ($flash): ?>
    <div class="alert alert-<?= e($flash['type']) ?> alert-dismissible fade show mb-3">
        <i class="bi bi-<?= $flash['type']==='success'?'check-circle':'exclamation-triangle' ?> me-2"></i>
        <?= e($flash['message']) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>

    <!-- ===== WELCOME BANNER ===== -->
    <div class="welcome-banner">
        <div class="wb-inner">
            <div class="row align-items-center">
                <div class="col-lg-9">
                    <div class="wb-date">
                        <i class="bi bi-calendar3 me-1"></i>
                        <?= date('l, d F Y') ?> &nbsp;·&nbsp; NYH Air Cargo Management
                    </div>
                    <h2>Welcome back, <?= e($me['full_name'] ?? currentUserName()) ?>! ✈️</h2>
                    <div class="wb-meta">
                        <span><i class="bi bi-person-badge me-1"></i><?= ucfirst(e($me['role'] ?? currentUserRole())) ?></span>
                        <?php if (!empty($me['email'])): ?>
                        <span class="sep">|</span>
                        <span><i class="bi bi-envelope me-1"></i><?= e($me['email']) ?></span>
                        <?php endif; ?>
                        <span class="sep">|</span>
                        <span><i class="bi bi-clock me-1"></i><?= date('H:i') ?></span>
                    </div>
                    <div class="wb-stats">
                        <div class="wb-stat-item">
                            <div class="val"><?= number_format($stats['total_manifests']) ?></div>
                            <div class="lbl">Total Manifests</div>
                        </div>
                        <div class="wb-stat-item">
                            <div class="val"><?= number_format($stats['this_month']) ?></div>
                            <div class="lbl"><?= date('M Y') ?></div>
                        </div>
                        <div class="wb-stat-item">
                            <div class="val"><?= number_format($stats['total_hawbs']) ?></div>
                            <div class="lbl">Total HAWBs</div>
                        </div>
                        <div class="wb-stat-item">
                            <div class="val"><?= number_format($stats['gw_month'], 0) ?></div>
                            <div class="lbl">GW/Month (kg)</div>
                        </div>
                    </div>
                </div>
                <div class="col-lg-3 mt-4 mt-lg-0">
                    <div class="wb-actions">
                        <?php if (isManager()): ?>
                        <a href="<?= BASE_URL ?>operations/manifest/create.php" class="btn btn-amber w-100">
                            <i class="bi bi-plus-circle me-2"></i>New Manifest
                        </a>
                        <?php endif; ?>
                        <a href="<?= BASE_URL ?>operations/manifest/index.php" class="btn btn-outline-light w-100">
                            <i class="bi bi-list-ul me-2"></i>All Manifests
                        </a>
                        <a href="<?= BASE_URL ?>operations/hawb/index.php" class="btn btn-outline-light w-100">
                            <i class="bi bi-card-list me-2"></i>HAWB Bills
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- ===== PENDING ALERT ===== -->
    <?php if ($stats['pending_weigh'] > 0): ?>
    <div class="pending-pill mb-4 d-flex align-items-center gap-2">
        <i class="bi bi-exclamation-triangle-fill fs-5"></i>
        <span>
            <strong><?= $stats['pending_weigh'] ?> HAWB(s)</strong> pending weighing
            <?php if (isStaff()): ?>
            — <a href="<?= BASE_URL ?>operations/hawb/index.php?filter=pending" class="text-warning fw-bold">Go to weighing →</a>
            <?php endif; ?>
        </span>
    </div>
    <?php endif; ?>

    <!-- ===== STAT CARDS ===== -->
    <div class="row g-3 mb-4">
        <div class="col-6 col-md-3">
            <div class="card stat-card shadow-sm h-100">
                <div class="card-body p-3">
                    <div class="d-flex align-items-center justify-content-between mb-3">
                        <div class="stat-icon bg-primary bg-opacity-10 text-primary">
                            <i class="bi bi-file-earmark-text"></i>
                        </div>
                        <span class="badge bg-primary bg-opacity-10 text-primary small">Total</span>
                    </div>
                    <div class="fs-2 fw-bold"><?= number_format($stats['total_manifests']) ?></div>
                    <div class="info-label">Manifests</div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card stat-card shadow-sm h-100">
                <div class="card-body p-3">
                    <div class="d-flex align-items-center justify-content-between mb-3">
                        <div class="stat-icon bg-success bg-opacity-10 text-success">
                            <i class="bi bi-calendar-check"></i>
                        </div>
                        <?php if ($monthChange > 0): ?>
                        <span class="badge bg-success bg-opacity-10 text-success small">
                            <i class="bi bi-arrow-up"></i> <?= abs($monthChange) ?>%
                        </span>
                        <?php elseif ($monthChange < 0): ?>
                        <span class="badge bg-danger bg-opacity-10 text-danger small">
                            <i class="bi bi-arrow-down"></i> <?= abs($monthChange) ?>%
                        </span>
                        <?php else: ?>
                        <span class="badge bg-secondary bg-opacity-10 text-secondary small">—</span>
                        <?php endif; ?>
                    </div>
                    <div class="fs-2 fw-bold"><?= number_format($stats['this_month']) ?></div>
                    <div class="info-label">This Month</div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card stat-card shadow-sm h-100">
                <div class="card-body p-3">
                    <div class="d-flex align-items-center justify-content-between mb-3">
                        <div class="stat-icon bg-warning bg-opacity-10 text-warning">
                            <i class="bi bi-boxes"></i>
                        </div>
                        <span class="badge bg-warning bg-opacity-10 text-warning small">GW</span>
                    </div>
                    <div class="fs-2 fw-bold"><?= number_format($stats['gw_month'], 0) ?></div>
                    <div class="info-label">Gross Weight / Month (kg)</div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card stat-card shadow-sm h-100">
                <div class="card-body p-3">
                    <div class="d-flex align-items-center justify-content-between mb-3">
                        <div class="stat-icon bg-info bg-opacity-10 text-info">
                            <i class="bi bi-speedometer2"></i>
                        </div>
                        <span class="badge bg-info bg-opacity-10 text-info small">CW</span>
                    </div>
                    <div class="fs-2 fw-bold"><?= number_format($stats['cw_month'], 0) ?></div>
                    <div class="info-label">Chargeable Weight / Month (kg)</div>
                </div>
            </div>
        </div>
    </div>

    <!-- ===== STATUS + CHART ===== -->
    <div class="row g-4 mb-4">

        <!-- Status breakdown -->
        <div class="col-md-4">
            <div class="card shadow-sm h-100">
                <div class="card-header py-3 fw-bold">
                    <i class="bi bi-pie-chart text-primary me-2"></i>Manifest Status
                </div>
                <div class="card-body">
                    <div class="d-flex align-items-center justify-content-between mb-3 p-3 bg-light rounded-3">
                        <span class="badge bg-secondary"><i class="bi bi-pencil me-1"></i>Draft</span>
                        <span class="fs-4 fw-bold text-secondary"><?= $stats['draft'] ?></span>
                    </div>
                    <div class="d-flex align-items-center justify-content-between mb-3 p-3 bg-light rounded-3">
                        <span class="badge bg-primary"><i class="bi bi-check-circle me-1"></i>Confirmed</span>
                        <span class="fs-4 fw-bold text-primary"><?= $stats['confirmed'] ?></span>
                    </div>
                    <div class="d-flex align-items-center justify-content-between mb-3 p-3 bg-light rounded-3">
                        <span class="badge bg-success"><i class="bi bi-check-all me-1"></i>Completed</span>
                        <span class="fs-4 fw-bold text-success"><?= $stats['completed'] ?></span>
                    </div>
                    <?php if ($stats['total_manifests'] > 0): ?>
                    <div class="mt-3">
                        <div class="d-flex justify-content-between small text-muted mb-1">
                            <span>Progress</span>
                            <span><?= round($stats['completed'] / $stats['total_manifests'] * 100) ?>% completed</span>
                        </div>
                        <div class="progress" style="height:8px;border-radius:8px;">
                            <div class="progress-bar bg-secondary" style="width:<?= round($stats['draft']/$stats['total_manifests']*100) ?>%"></div>
                            <div class="progress-bar bg-primary"   style="width:<?= round($stats['confirmed']/$stats['total_manifests']*100) ?>%"></div>
                            <div class="progress-bar bg-success"   style="width:<?= round($stats['completed']/$stats['total_manifests']*100) ?>%"></div>
                        </div>
                    </div>
                    <?php endif; ?>
                    <hr class="my-3">
                    <div class="row g-2 text-center">
                        <div class="col-6">
                            <div class="bg-light rounded-3 p-2">
                                <div class="fw-bold text-primary"><?= $stats['total_hawbs'] ?></div>
                                <div class="info-label">Total HAWBs</div>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="bg-light rounded-3 p-2">
                                <div class="fw-bold text-danger"><?= $stats['pending_weigh'] ?></div>
                                <div class="info-label">Pending Weigh</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Chart 7 days -->
        <div class="col-md-8">
            <div class="card shadow-sm h-100">
                <div class="card-header py-3 fw-bold">
                    <i class="bi bi-bar-chart text-success me-2"></i>Manifests Created – Last 7 Days
                </div>
                <div class="card-body d-flex align-items-center">
                    <canvas id="chartWeek" style="max-height:240px;width:100%;"></canvas>
                </div>
            </div>
        </div>

    </div>

    <!-- ===== RECENT MANIFESTS + UPCOMING ===== -->
    <div class="row g-4">

        <!-- Recent manifests -->
        <div class="col-xl-8">
            <div class="card shadow-sm">
                <div class="card-header py-3 d-flex justify-content-between align-items-center">
                    <span class="fw-bold"><i class="bi bi-clock-history text-primary me-2"></i>Recent Manifests</span>
                    <a href="<?= BASE_URL ?>operations/manifest/index.php" class="btn btn-outline-primary btn-sm">
                        View All <i class="bi bi-arrow-right ms-1"></i>
                    </a>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead>
                                <tr>
                                    <th class="ps-3">MAWB No</th>
                                    <th>Flight</th>
                                    <th>Date</th>
                                    <th>Route</th>
                                    <th>HAWBs</th>
                                    <th>GW (kg)</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php if ($recent && $recent->num_rows > 0):
                            while ($row = $recent->fetch_assoc()): ?>
                            <tr onclick="window.location='<?= BASE_URL ?>operations/manifest/edit.php?id=<?= $row['id'] ?>'" style="cursor:pointer;">
                                <td class="ps-3">
                                    <div class="fw-bold text-primary small"><?= e($row['mawb_no']) ?></div>
                                    <small class="text-muted"><?= e($row['airline_code']) ?></small>
                                </td>
                                <td><small><?= e($row['flight_no']) ?></small></td>
                                <td><small><?= fmtDate($row['flight_date'], 'd-M-Y') ?></small></td>
                                <td>
                                    <span class="fw-semibold small"><?= e($row['origin_code']) ?></span>
                                    <i class="bi bi-arrow-right text-muted mx-1" style="font-size:.7rem;"></i>
                                    <span class="fw-semibold small"><?= e($row['dest_code']) ?></span>
                                </td>
                                <td><span class="badge bg-secondary"><?= $row['hawb_count'] ?></span></td>
                                <td><small class="fw-semibold"><?= $row['total_gw'] > 0 ? number_format($row['total_gw'], 1) : '—' ?></small></td>
                                <td><?= statusBadge($row['status']) ?></td>
                            </tr>
                            <?php endwhile; else: ?>
                            <tr>
                                <td colspan="7" class="text-center py-5 text-muted">
                                    <i class="bi bi-inbox fs-2 d-block mb-2 opacity-25"></i>
                                    No manifests yet.
                                    <?php if (isManager()): ?>
                                    <br><a href="<?= BASE_URL ?>operations/manifest/create.php" class="btn btn-primary btn-sm mt-2">
                                        <i class="bi bi-plus-circle me-1"></i>Create First Manifest
                                    </a>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Upcoming flights -->
        <div class="col-xl-4">
            <div class="card shadow-sm">
                <div class="card-header py-3 fw-bold">
                    <i class="bi bi-airplane-engines text-danger me-2"></i>Upcoming Flights (7 days)
                </div>
                <div class="card-body p-0">
                    <?php if ($upcoming && $upcoming->num_rows > 0):
                    while ($u = $upcoming->fetch_assoc()):
                        $daysLeft   = (int)floor((strtotime($u['flight_date']) - time()) / 86400);
                        $badgeColor = $daysLeft == 0 ? 'danger' : ($daysLeft <= 2 ? 'warning' : 'success');
                        $badgeText  = $daysLeft == 0 ? 'Today' : ($daysLeft == 1 ? 'Tomorrow' : "$daysLeft days");
                    ?>
                    <a href="<?= BASE_URL ?>operations/manifest/edit.php?id=<?= $u['id'] ?>"
                       class="d-flex align-items-center px-3 py-2 border-bottom text-decoration-none text-dark">
                        <div class="me-3 text-center" style="min-width:40px;">
                            <div class="fw-bold text-primary lh-1"><?= date('d', strtotime($u['flight_date'])) ?></div>
                            <div class="text-muted" style="font-size:.65rem;"><?= date('M', strtotime($u['flight_date'])) ?></div>
                        </div>
                        <div class="flex-grow-1 overflow-hidden">
                            <div class="fw-semibold small text-truncate"><?= e($u['mawb_no']) ?></div>
                            <div class="text-muted" style="font-size:.72rem;">
                                <?= e($u['origin_code']) ?> → <?= e($u['dest_code']) ?>
                                <?php if ($u['flight_no']): ?> · <?= e($u['flight_no']) ?><?php endif; ?>
                            </div>
                        </div>
                        <span class="badge bg-<?= $badgeColor ?> ms-2 flex-shrink-0" style="font-size:.7rem;"><?= $badgeText ?></span>
                    </a>
                    <?php endwhile; else: ?>
                    <div class="text-center py-5 text-muted">
                        <i class="bi bi-calendar-x fs-2 d-block mb-2 opacity-25"></i>
                        <small>No flights in the next 7 days</small>
                    </div>
                    <?php endif; ?>
                </div>
                <?php if (isManager()): ?>
                <div class="card-footer bg-white text-center py-2">
                    <a href="<?= BASE_URL ?>operations/manifest/create.php" class="btn btn-outline-primary btn-sm w-100">
                        <i class="bi bi-plus-circle me-1"></i>New Manifest
                    </a>
                </div>
                <?php endif; ?>
            </div>
        </div>

    </div>

</main>
</div>

<!-- Footer -->
<footer class="text-center py-3 border-top bg-white">
    <small class="text-muted">
        &copy; <?= date('Y') ?> <?= APP_NAME ?> — Air Cargo Management System v<?= APP_VERSION ?>
    </small>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Chart
const chartData = <?= json_encode($chart_data) ?>;
new Chart(document.getElementById('chartWeek').getContext('2d'), {
    type: 'bar',
    data: {
        labels: chartData.map(d => d.label),
        datasets: [{
            label: 'Manifests',
            data: chartData.map(d => d.count),
            backgroundColor: 'rgba(13,110,253,.15)',
            borderColor:     'rgba(13,110,253,.8)',
            borderWidth: 2, borderRadius: 8,
            hoverBackgroundColor: 'rgba(13,110,253,.3)',
        }]
    },
    options: {
        responsive: true,
        plugins: { legend: { display: false } },
        scales: {
            y: { beginAtZero:true, ticks:{ stepSize:1, precision:0 }, grid:{ color:'rgba(0,0,0,.04)' } },
            x: { grid:{ display:false } }
        }
    }
});

// Sidebar toggle mobile
document.getElementById('sidebarToggle')?.addEventListener('click', function() {
    document.getElementById('sidebar').classList.toggle('d-none');
});

// Auto-dismiss alerts
setTimeout(() => {
    document.querySelectorAll('.alert.alert-dismissible').forEach(el => {
        bootstrap.Alert.getOrCreateInstance(el).close();
    });
}, 4000);
</script>
</body>
</html>