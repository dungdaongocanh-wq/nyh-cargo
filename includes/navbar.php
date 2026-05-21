<?php if (!isset($_nyhNavbarLoaded)): $_nyhNavbarLoaded = true; ?>
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
                <li><a class="dropdown-item" href="<?= BASE_URL ?>profile.php">
                    <i class="bi bi-person me-2"></i>Profile</a></li>
                <li><a class="dropdown-item" href="<?= BASE_URL ?>change-password.php">
                    <i class="bi bi-key me-2"></i>Change Password</a></li>
                <li><hr class="dropdown-divider"></li>
                <li><a class="dropdown-item text-danger" href="<?= BASE_URL ?>logout.php"
                       onclick="return confirm('Logout?')">
                    <i class="bi bi-box-arrow-right me-2"></i>Logout</a></li>
            </ul>
        </div>
    </div>
</nav>
<?php endif; ?>