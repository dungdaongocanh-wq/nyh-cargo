<?php
if (!isset($flash)) $flash = null;

$currentPage = basename($_SERVER['PHP_SELF']);
$currentDir  = basename(dirname($_SERVER['PHP_SELF']));
$currentPath = $_SERVER['PHP_SELF'];
?>

<!-- SIDEBAR -->
<nav id="sidebar" class="d-flex flex-column"
     style="width:240px;min-width:240px;background:#212529;min-height:100%;transition:all .3s;z-index:1040;">

    <div class="flex-grow-1 overflow-y-auto py-2">
        <ul class="nav flex-column px-2">

            <!-- ===== DASHBOARD ===== -->
            <li class="nav-item">
                <a class="nav-link d-flex align-items-center gap-2 rounded-2 px-3 py-2
                    <?= (str_contains($currentPath, 'nyh-cargo/index.php') || $currentPage === 'index.php' && $currentDir === 'nyh-cargo') ? 'active' : '' ?>"
                   href="<?= BASE_URL ?>index.php">
                    <i class="bi bi-speedometer2"></i>
                    <span>Dashboard</span>
                </a>
            </li>

            <!-- ===== OPERATIONS ===== -->
            <li class="nav-item mt-3">
                <div class="px-3 mb-1" style="font-size:.68rem;font-weight:700;text-transform:uppercase;letter-spacing:.08em;color:#6c757d;">
                    Operations
                </div>
            </li>

            <?php if (isManager()): ?>
            <!-- Manifests -->
            <li class="nav-item">
                <a class="nav-link d-flex align-items-center gap-2 rounded-2 px-3 py-2
                    <?= str_contains($currentPath, '/operations/manifest') ? 'active' : '' ?>"
                   href="<?= BASE_URL ?>operations/manifest/index.php">
                    <i class="bi bi-file-earmark-text"></i>
                    <span>Manifests</span>
                    <?php
                    $draftCount = getDB()->query("SELECT COUNT(*) FROM manifests WHERE status='draft'")->fetch_row()[0] ?? 0;
                    if ($draftCount > 0):
                    ?>
                    <span class="badge bg-warning text-dark ms-auto" style="font-size:.65rem;"><?= $draftCount ?></span>
                    <?php endif; ?>
                </a>
            </li>
            <?php endif; ?>

            <!-- HAWB Bills -->
            <li class="nav-item">
                <a class="nav-link d-flex align-items-center gap-2 rounded-2 px-3 py-2
                    <?= str_contains($currentPath, '/operations/hawb') ? 'active' : '' ?>"
                   href="<?= BASE_URL ?>operations/hawb/index.php">
                    <i class="bi bi-card-list"></i>
                    <span>HAWB Bills</span>
                    <?php
                    $pendingWeigh = getDB()->query("SELECT COUNT(*) FROM hawbs h JOIN manifests m ON h.manifest_id=m.id WHERE h.is_weighed=0 AND m.status='confirmed'")->fetch_row()[0] ?? 0;
                    if ($pendingWeigh > 0):
                    ?>
                    <span class="badge bg-danger ms-auto" style="font-size:.65rem;"><?= $pendingWeigh ?></span>
                    <?php endif; ?>
                </a>
            </li>

            <!-- ===== MASTER DATA ===== -->
            <?php if (isManager()): ?>
            <li class="nav-item mt-3">
                <div class="px-3 mb-1" style="font-size:.68rem;font-weight:700;text-transform:uppercase;letter-spacing:.08em;color:#6c757d;">
                    Master Data
                </div>
            </li>

            <!-- Airlines -->
            <li class="nav-item">
                <a class="nav-link d-flex align-items-center gap-2 rounded-2 px-3 py-2
                    <?= $currentPage === 'airlines.php' ? 'active' : '' ?>"
                   href="<?= BASE_URL ?>master/airlines.php">
                    <i class="bi bi-airplane"></i>
                    <span>Airlines</span>
                </a>
            </li>

            <!-- Airports -->
            <li class="nav-item">
                <a class="nav-link d-flex align-items-center gap-2 rounded-2 px-3 py-2
                    <?= $currentPage === 'airports.php' ? 'active' : '' ?>"
                   href="<?= BASE_URL ?>master/airports.php">
                    <i class="bi bi-geo-alt"></i>
                    <span>Airports</span>
                </a>
            </li>

            <!-- Shippers -->
            <li class="nav-item">
                <a class="nav-link d-flex align-items-center gap-2 rounded-2 px-3 py-2
                    <?= $currentPage === 'shippers.php' ? 'active' : '' ?>"
                   href="<?= BASE_URL ?>master/shippers.php">
                    <i class="bi bi-box-seam"></i>
                    <span>Shippers</span>
                </a>
            </li>

            <!-- Consignees -->
            <li class="nav-item">
                <a class="nav-link d-flex align-items-center gap-2 rounded-2 px-3 py-2
                    <?= $currentPage === 'consignees.php' ? 'active' : '' ?>"
                   href="<?= BASE_URL ?>master/consignees.php">
                    <i class="bi bi-building"></i>
                    <span>Consignees</span>
                </a>
            </li>

            <!-- Customers (MAWB) -->
            <li class="nav-item">
                <a class="nav-link d-flex align-items-center gap-2 rounded-2 px-3 py-2
                    <?= $currentPage === 'customers.php' ? 'active' : '' ?>"
                   href="<?= BASE_URL ?>master/customers.php">
                    <i class="bi bi-people"></i>
                    <span>Customers <small class="opacity-75">(MAWB)</small></span>
                </a>
            </li>
            <?php endif; ?>

            <!-- ===== PRINT ===== -->
            <?php if (isStaff()): ?>
            <li class="nav-item mt-3">
                <div class="px-3 mb-1" style="font-size:.68rem;font-weight:700;text-transform:uppercase;letter-spacing:.08em;color:#6c757d;">
                    Print
                </div>
            </li>

            <li class="nav-item">
                <a class="nav-link d-flex align-items-center gap-2 rounded-2 px-3 py-2
                    <?= str_contains($currentPath, '/print/hawb') ? 'active' : '' ?>"
                   href="<?= BASE_URL ?>operations/hawb/index.php?filter=ready">
                    <i class="bi bi-printer"></i>
                    <span>Print HAWB</span>
                </a>
            </li>

            <li class="nav-item">
                <a class="nav-link d-flex align-items-center gap-2 rounded-2 px-3 py-2
                    <?= str_contains($currentPath, '/print/label') ? 'active' : '' ?>"
                   href="<?= BASE_URL ?>operations/hawb/index.php?filter=label">
                    <i class="bi bi-tag"></i>
                    <span>Print Labels</span>
                </a>
            </li>
            <?php endif; ?>

            <!-- ===== ADMINISTRATION ===== -->
            <?php if (isAdmin()): ?>
            <li class="nav-item mt-3">
                <div class="px-3 mb-1" style="font-size:.68rem;font-weight:700;text-transform:uppercase;letter-spacing:.08em;color:#6c757d;">
                    Administration
                </div>
            </li>

            <!-- Accounts -->
            <li class="nav-item">
                <a class="nav-link d-flex align-items-center gap-2 rounded-2 px-3 py-2
                    <?= $currentPage === 'accounts.php' ? 'active' : '' ?>"
                   href="<?= BASE_URL ?>admin/accounts.php">
                    <i class="bi bi-person-gear"></i>
                    <span>Accounts</span>
                </a>
            </li>
            <?php endif; ?>

        </ul>
    </div>

    <!-- ===== SIDEBAR FOOTER ===== -->
    <div class="px-3 py-3 border-top border-secondary">
        <!-- User info -->
        <div class="d-flex align-items-center gap-2 mb-2">
            <div class="rounded-circle bg-primary d-flex align-items-center justify-content-center flex-shrink-0"
                 style="width:32px;height:32px;font-size:.8rem;font-weight:700;color:#fff;">
                <?= strtoupper(substr(currentUserName(), 0, 1)) ?>
            </div>
            <div class="overflow-hidden">
                <div class="text-white fw-semibold text-truncate" style="font-size:.8rem;max-width:140px;">
                    <?= e(currentUserName()) ?>
                </div>
                <div style="font-size:.68rem;color:#6c757d;">
                    <?= ucfirst(currentUserRole()) ?>
                </div>
            </div>
        </div>

        <!-- Quick links -->
        <div class="d-flex gap-1">
            <a href="<?= BASE_URL ?>change-password.php"
               class="btn btn-sm flex-grow-1 text-secondary border-secondary"
               style="font-size:.72rem;background:transparent;"
               title="Change Password">
                <i class="bi bi-key me-1"></i>Password
            </a>
            <a href="<?= BASE_URL ?>logout.php"
               class="btn btn-sm text-danger border-secondary"
               style="font-size:.72rem;background:transparent;"
               title="Logout"
               onclick="return confirm('Logout?')">
                <i class="bi bi-box-arrow-right"></i>
            </a>
        </div>

        <div class="mt-2" style="font-size:.65rem;color:#495057;">
            <?= APP_NAME ?> v<?= APP_VERSION ?>
        </div>
    </div>

</nav>
<!-- /SIDEBAR -->

<style>
#sidebar .nav-link {
    color: #adb5bd;
    font-size: .875rem;
    transition: background .15s, color .15s;
}
#sidebar .nav-link:hover {
    background: rgba(255,255,255,.08);
    color: #fff;
}
#sidebar .nav-link.active {
    background: #0d6efd;
    color: #fff;
}
#sidebar .nav-link.active i,
#sidebar .nav-link:hover i {
    color: inherit;
}
/* Scrollbar sidebar */
#sidebar::-webkit-scrollbar { width: 4px; }
#sidebar::-webkit-scrollbar-track { background: transparent; }
#sidebar::-webkit-scrollbar-thumb { background: #495057; border-radius: 4px; }

@media (max-width: 991.98px) {
    #sidebar {
        position: fixed;
        top: 56px;
        left: 0;
        height: calc(100vh - 56px);
    }
}
</style>