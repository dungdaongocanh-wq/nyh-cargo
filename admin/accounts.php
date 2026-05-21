<?php
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
requireRole(ROLE_ADMIN);

$db = getDB();

// ── CREATE ──────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'create') {
        $username  = trim($_POST['username']  ?? '');
        $full_name = trim($_POST['full_name'] ?? '');
        $email     = trim($_POST['email']     ?? '');
        $role      = $_POST['role']           ?? '';
        $password  = $_POST['password']       ?? '';
        $password2 = $_POST['password2']      ?? '';

        $errors = [];
        if (!$username)  $errors[] = 'Username is required.';
        if (!$full_name) $errors[] = 'Full name is required.';
        if (!in_array($role, ['admin','manager','staff'])) $errors[] = 'Invalid role.';
        if (strlen($password) < 6) $errors[] = 'Password must be at least 6 characters.';
        if ($password !== $password2) $errors[] = 'Passwords do not match.';

        // Check duplicate username
        if (!$errors) {
            $chk = $db->prepare("SELECT id FROM users WHERE username=?");
            $chk->bind_param('s', $username);
            $chk->execute();
            if ($chk->get_result()->num_rows > 0) $errors[] = "Username <strong>$username</strong> already exists.";
            $chk->close();
        }

        if ($errors) {
            setFlash('danger', implode('<br>', $errors));
        } else {
            $hash = password_hash($password, PASSWORD_BCRYPT);
            $by   = currentUserId();
            $stmt = $db->prepare("INSERT INTO users (username,password,full_name,email,role,is_active,created_by) VALUES (?,?,?,?,?,1,?)");
            $stmt->bind_param('sssssi', $username,$hash,$full_name,$email,$role,$by);
            $stmt->execute()
                ? setFlash('success', "Account <strong>$username</strong> created successfully.")
                : setFlash('danger', 'Database error: ' . $db->error);
            $stmt->close();
        }
        redirect(BASE_URL . 'admin/accounts.php');
    }

    // ── UPDATE ──────────────────────────────────────────
    if ($action === 'update') {
        $id        = (int)$_POST['id'];
        $full_name = trim($_POST['full_name'] ?? '');
        $email     = trim($_POST['email']     ?? '');
        $role      = $_POST['role']           ?? '';
        $is_active = isset($_POST['is_active']) ? 1 : 0;

        // Prevent admin from deactivating themselves
        if ($id === currentUserId() && !$is_active) {
            setFlash('danger', 'You cannot deactivate your own account.');
            redirect(BASE_URL . 'admin/accounts.php');
        }

        $stmt = $db->prepare("UPDATE users SET full_name=?,email=?,role=?,is_active=? WHERE id=?");
        $stmt->bind_param('sssii', $full_name,$email,$role,$is_active,$id);
        $stmt->execute(); $stmt->close();
        setFlash('success', 'Account updated successfully.');
        redirect(BASE_URL . 'admin/accounts.php');
    }

    // ── RESET PASSWORD ──────────────────────────────────
    if ($action === 'reset_password') {
        $id        = (int)$_POST['id'];
        $password  = $_POST['new_password']   ?? '';
        $password2 = $_POST['new_password2']  ?? '';

        if (strlen($password) < 6) {
            setFlash('danger', 'Password must be at least 6 characters.');
        } elseif ($password !== $password2) {
            setFlash('danger', 'Passwords do not match.');
        } else {
            $hash = password_hash($password, PASSWORD_BCRYPT);
            $stmt = $db->prepare("UPDATE users SET password=? WHERE id=?");
            $stmt->bind_param('si', $hash, $id);
            $stmt->execute(); $stmt->close();
            setFlash('success', 'Password reset successfully.');
        }
        redirect(BASE_URL . 'admin/accounts.php');
    }

    // ── TOGGLE ACTIVE ────────────────────────────────────
    if ($action === 'toggle_active') {
        $id = (int)$_POST['id'];
        if ($id === currentUserId()) {
            setFlash('danger', 'You cannot deactivate your own account.');
            redirect(BASE_URL . 'admin/accounts.php');
        }
        $db->query("UPDATE users SET is_active = IF(is_active=1,0,1) WHERE id=$id");
        setFlash('success', 'Account status updated.');
        redirect(BASE_URL . 'admin/accounts.php');
    }

    // ── DELETE ───────────────────────────────────────────
    if ($action === 'delete') {
        $id = (int)$_POST['id'];
        if ($id === currentUserId()) {
            setFlash('danger', 'You cannot delete your own account.');
            redirect(BASE_URL . 'admin/accounts.php');
        }
        $stmt = $db->prepare("DELETE FROM users WHERE id=?");
        $stmt->bind_param('i', $id);
        $stmt->execute()
            ? setFlash('success', 'Account deleted.')
            : setFlash('danger', 'Cannot delete — user has associated records.');
        $stmt->close();
        redirect(BASE_URL . 'admin/accounts.php');
    }
}

// ── FETCH ────────────────────────────────────────────────
$search     = trim($_GET['q']    ?? '');
$filterRole = trim($_GET['role'] ?? '');

$sql  = "SELECT u.*, c.full_name AS creator_name
         FROM users u
         LEFT JOIN users c ON u.created_by = c.id
         WHERE 1=1";
if ($search)     $sql .= " AND (u.username LIKE '%$search%' OR u.full_name LIKE '%$search%' OR u.email LIKE '%$search%')";
if ($filterRole) $sql .= " AND u.role='$filterRole'";
$sql .= " ORDER BY u.role ASC, u.full_name ASC";
$rows = $db->query($sql);

// Stats
$stats = [
    'total'   => $db->query("SELECT COUNT(*) FROM users")->fetch_row()[0],
    'admin'   => $db->query("SELECT COUNT(*) FROM users WHERE role='admin'")->fetch_row()[0],
    'manager' => $db->query("SELECT COUNT(*) FROM users WHERE role='manager'")->fetch_row()[0],
    'staff'   => $db->query("SELECT COUNT(*) FROM users WHERE role='staff'")->fetch_row()[0],
    'active'  => $db->query("SELECT COUNT(*) FROM users WHERE is_active=1")->fetch_row()[0],
    'inactive'=> $db->query("SELECT COUNT(*) FROM users WHERE is_active=0")->fetch_row()[0],
];

$roleColors = ['admin'=>'danger','manager'=>'primary','staff'=>'success'];
$pageTitle  = 'Accounts';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
    <title><?= $pageTitle ?> | <?= APP_NAME ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="<?= BASE_URL ?>assets/css/app.css" rel="stylesheet">
    <style>
        .avatar-circle {
            width: 38px; height: 38px; border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            font-weight: 700; font-size: .9rem; color: #fff;
            flex-shrink: 0;
        }
        .role-admin   { background: #dc3545; }
        .role-manager { background: #0d6efd; }
        .role-staff   { background: #198754; }
        .stat-mini { border-radius: 12px; padding: 1rem 1.25rem; }
    </style>
</head>
<body style="background:#f0f2f5;">
<?php require_once __DIR__ . '/../includes/navbar.php'; ?>
<div class="d-flex" style="margin-top:56px;min-height:calc(100vh - 56px);">
<?php require_once __DIR__ . '/../includes/sidebar.php'; ?>

<main class="flex-grow-1 p-4">

<!-- Flash -->
<?php $flash = getFlash(); if ($flash): ?>
<div class="alert alert-<?= e($flash['type']) ?> alert-dismissible fade show">
    <i class="bi bi-<?= $flash['type']==='success'?'check-circle':'exclamation-triangle' ?> me-2"></i>
    <?= $flash['message'] ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<!-- Page Header -->
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="fw-bold mb-0"><i class="bi bi-person-gear text-primary me-2"></i>Account Management</h4>
        <small class="text-muted">Manage system users and permissions</small>
    </div>
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalCreate">
        <i class="bi bi-person-plus me-1"></i>Add Account
    </button>
</div>

<!-- Stats Row -->
<div class="row g-3 mb-4">
    <div class="col-6 col-md-2">
        <div class="card stat-mini text-center shadow-sm">
            <div class="fs-3 fw-bold text-dark"><?= $stats['total'] ?></div>
            <div class="small text-muted">Total Users</div>
        </div>
    </div>
    <div class="col-6 col-md-2">
        <div class="card stat-mini text-center shadow-sm">
            <div class="fs-3 fw-bold text-danger"><?= $stats['admin'] ?></div>
            <div class="small text-muted">Admins</div>
        </div>
    </div>
    <div class="col-6 col-md-2">
        <div class="card stat-mini text-center shadow-sm">
            <div class="fs-3 fw-bold text-primary"><?= $stats['manager'] ?></div>
            <div class="small text-muted">Managers</div>
        </div>
    </div>
    <div class="col-6 col-md-2">
        <div class="card stat-mini text-center shadow-sm">
            <div class="fs-3 fw-bold text-success"><?= $stats['staff'] ?></div>
            <div class="small text-muted">Staff</div>
        </div>
    </div>
    <div class="col-6 col-md-2">
        <div class="card stat-mini text-center shadow-sm">
            <div class="fs-3 fw-bold text-success"><?= $stats['active'] ?></div>
            <div class="small text-muted">Active</div>
        </div>
    </div>
    <div class="col-6 col-md-2">
        <div class="card stat-mini text-center shadow-sm">
            <div class="fs-3 fw-bold text-secondary"><?= $stats['inactive'] ?></div>
            <div class="small text-muted">Inactive</div>
        </div>
    </div>
</div>

<!-- Filter & Search -->
<div class="card mb-3">
    <div class="card-body py-2">
        <form class="d-flex flex-wrap gap-2 align-items-center" method="GET">
            <input type="text" name="q" class="form-control form-control-sm"
                   style="max-width:260px;"
                   placeholder="Search username, name, email..."
                   value="<?= e($search) ?>">
            <select name="role" class="form-select form-select-sm" style="max-width:150px;">
                <option value="">All Roles</option>
                <option value="admin"   <?= $filterRole==='admin'   ?'selected':'' ?>>Admin</option>
                <option value="manager" <?= $filterRole==='manager' ?'selected':'' ?>>Manager</option>
                <option value="staff"   <?= $filterRole==='staff'   ?'selected':'' ?>>Staff</option>
            </select>
            <button class="btn btn-sm btn-outline-secondary">
                <i class="bi bi-search me-1"></i>Search
            </button>
            <?php if ($search || $filterRole): ?>
            <a href="?" class="btn btn-sm btn-outline-danger">
                <i class="bi bi-x me-1"></i>Clear
            </a>
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
                        <th class="ps-3">#</th>
                        <th>User</th>
                        <th>Username</th>
                        <th>Role</th>
                        <th>Email</th>
                        <th>Status</th>
                        <th>Created</th>
                        <th>Created By</th>
                        <th class="text-end pe-3">Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php $i = 1; while ($row = $rows->fetch_assoc()):
                    $roleColor = $roleColors[$row['role']] ?? 'secondary';
                    $isSelf    = ($row['id'] == currentUserId());
                ?>
                <tr class="<?= !$row['is_active'] ? 'table-secondary' : '' ?>">
                    <td class="ps-3 text-muted small"><?= $i++ ?></td>
                    <td>
                        <div class="d-flex align-items-center gap-2">
                            <div class="avatar-circle role-<?= e($row['role']) ?>">
                                <?= strtoupper(substr($row['full_name'], 0, 1)) ?>
                            </div>
                            <div>
                                <div class="fw-semibold">
                                    <?= e($row['full_name']) ?>
                                    <?php if ($isSelf): ?>
                                    <span class="badge bg-warning text-dark ms-1" style="font-size:.65rem;">You</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </td>
                    <td><code><?= e($row['username']) ?></code></td>
                    <td>
                        <span class="badge bg-<?= $roleColor ?>">
                            <i class="bi bi-<?= $row['role']==='admin'?'shield-check':($row['role']==='manager'?'briefcase':'person') ?> me-1"></i>
                            <?= ucfirst($row['role']) ?>
                        </span>
                    </td>
                    <td>
                        <small class="text-muted"><?= e($row['email'] ?: '—') ?></small>
                    </td>
                    <td>
                        <?php if ($row['is_active']): ?>
                        <span class="badge bg-success-subtle text-success border border-success-subtle">
                            <i class="bi bi-circle-fill me-1" style="font-size:.5rem;"></i>Active
                        </span>
                        <?php else: ?>
                        <span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle">
                            <i class="bi bi-circle me-1" style="font-size:.5rem;"></i>Inactive
                        </span>
                        <?php endif; ?>
                    </td>
                    <td><small class="text-muted"><?= fmtDate($row['created_at'], 'd-M-Y') ?></small></td>
                    <td><small class="text-muted"><?= e($row['creator_name'] ?: '—') ?></small></td>
                    <td class="text-end pe-3">
                        <div class="d-flex gap-1 justify-content-end">
                            <!-- Edit -->
                            <button class="btn btn-sm btn-outline-primary btn-action"
                                    title="Edit"
                                    onclick='openEdit(<?= json_encode([
                                        "id"        => $row["id"],
                                        "full_name" => $row["full_name"],
                                        "email"     => $row["email"],
                                        "role"      => $row["role"],
                                        "is_active" => $row["is_active"],
                                        "username"  => $row["username"],
                                    ]) ?>)'>
                                <i class="bi bi-pencil"></i>
                            </button>

                            <!-- Reset Password -->
                            <button class="btn btn-sm btn-outline-warning btn-action"
                                    title="Reset Password"
                                    onclick="openReset(<?= $row['id'] ?>, '<?= e($row['username']) ?>')">
                                <i class="bi bi-key"></i>
                            </button>

                            <!-- Toggle Active -->
                            <?php if (!$isSelf): ?>
                            <form method="POST" class="d-inline">
                                <input type="hidden" name="action" value="toggle_active">
                                <input type="hidden" name="id" value="<?= $row['id'] ?>">
                                <button class="btn btn-sm btn-action
                                    <?= $row['is_active'] ? 'btn-outline-secondary' : 'btn-outline-success' ?>"
                                    title="<?= $row['is_active'] ? 'Deactivate' : 'Activate' ?>">
                                    <i class="bi bi-<?= $row['is_active'] ? 'person-dash' : 'person-check' ?>"></i>
                                </button>
                            </form>

                            <!-- Delete -->
                            <form method="POST" class="d-inline"
                                  onsubmit="return confirm('Delete account <?= e($row['username']) ?>? This cannot be undone.')">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="id" value="<?= $row['id'] ?>">
                                <button class="btn btn-sm btn-outline-danger btn-action" title="Delete">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </form>
                            <?php else: ?>
                            <!-- Placeholder to keep alignment -->
                            <button class="btn btn-sm btn-action invisible"><i class="bi bi-person-dash"></i></button>
                            <button class="btn btn-sm btn-action invisible"><i class="bi bi-trash"></i></button>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
                <?php endwhile; ?>
                <?php if ($rows->num_rows === 0): ?>
                <tr>
                    <td colspan="9" class="text-center py-5 text-muted">
                        <i class="bi bi-people fs-2 d-block mb-2 opacity-25"></i>
                        No accounts found.
                    </td>
                </tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Permission Reference -->
<div class="card mt-4">
    <div class="card-header fw-bold py-2">
        <i class="bi bi-shield-check me-2 text-primary"></i>Permission Reference
    </div>
    <div class="card-body">
        <div class="row g-3">
            <div class="col-md-4">
                <div class="border border-danger rounded-3 p-3">
                    <div class="d-flex align-items-center gap-2 mb-2">
                        <span class="badge bg-danger"><i class="bi bi-shield-check me-1"></i>Admin</span>
                        <small class="text-muted">Full Access</small>
                    </div>
                    <ul class="small text-muted mb-0 ps-3">
                        <li>Create / Delete / Edit all accounts</li>
                        <li>Reset any user's password</li>
                        <li>All Manager permissions</li>
                        <li>Delete any record in system</li>
                    </ul>
                </div>
            </div>
            <div class="col-md-4">
                <div class="border border-primary rounded-3 p-3">
                    <div class="d-flex align-items-center gap-2 mb-2">
                        <span class="badge bg-primary"><i class="bi bi-briefcase me-1"></i>Manager</span>
                        <small class="text-muted">Operations</small>
                    </div>
                    <ul class="small text-muted mb-0 ps-3">
                        <li>Create & edit Manifests</li>
                        <li>Create & manage HAWB Bills</li>
                        <li>Manage Master Data</li>
                        <li>Confirm / Complete manifests</li>
                        <li>Print HAWB, Manifest, Labels</li>
                    </ul>
                </div>
            </div>
            <div class="col-md-4">
                <div class="border border-success rounded-3 p-3">
                    <div class="d-flex align-items-center gap-2 mb-2">
                        <span class="badge bg-success"><i class="bi bi-person me-1"></i>Staff</span>
                        <small class="text-muted">Weighing & Print</small>
                    </div>
                    <ul class="small text-muted mb-0 ps-3">
                        <li>View confirmed manifests only</li>
                        <li>Enter GW & DIM per piece</li>
                        <li>System calculates CW automatically</li>
                        <li>Print HAWB Bills</li>
                        <li>Print Labels (Zebra)</li>
                        <li>Print Weight Slips</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>

</main>
</div>

<!-- ══════════════════════════════════════════════════════════
     MODAL: CREATE ACCOUNT
═══════════════════════════════════════════════════════════ -->
<div class="modal fade" id="modalCreate" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <form method="POST" class="modal-content" autocomplete="off">
            <input type="hidden" name="action" value="create">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title fw-bold">
                    <i class="bi bi-person-plus text-primary me-2"></i>Create New Account
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="row g-3">
                    <!-- Username -->
                    <div class="col-md-6">
                        <label class="form-label required">Username</label>
                        <div class="input-group">
                            <span class="input-group-text bg-light"><i class="bi bi-at text-secondary"></i></span>
                            <input type="text" name="username" class="form-control"
                                   placeholder="e.g. john.doe" autocomplete="off"
                                   pattern="[a-zA-Z0-9._]+" title="Letters, numbers, dot, underscore only"
                                   required>
                        </div>
                        <small class="text-muted">Letters, numbers, dot, underscore only</small>
                    </div>

                    <!-- Role -->
                    <div class="col-md-6">
                        <label class="form-label required">Role</label>
                        <select name="role" class="form-select" required>
                            <option value="">Select role...</option>
                            <option value="admin">Admin — Full Access</option>
                            <option value="manager">Manager — Operations</option>
                            <option value="staff">Staff — Weighing & Print</option>
                        </select>
                    </div>

                    <!-- Full Name -->
                    <div class="col-md-6">
                        <label class="form-label required">Full Name</label>
                        <div class="input-group">
                            <span class="input-group-text bg-light"><i class="bi bi-person text-secondary"></i></span>
                            <input type="text" name="full_name" class="form-control"
                                   placeholder="e.g. Nguyen Van A" required>
                        </div>
                    </div>

                    <!-- Email -->
                    <div class="col-md-6">
                        <label class="form-label">Email</label>
                        <div class="input-group">
                            <span class="input-group-text bg-light"><i class="bi bi-envelope text-secondary"></i></span>
                            <input type="email" name="email" class="form-control"
                                   placeholder="email@company.com">
                        </div>
                    </div>

                    <div class="col-12"><hr class="my-1"></div>

                    <!-- Password -->
                    <div class="col-md-6">
                        <label class="form-label required">Password</label>
                        <div class="input-group">
                            <span class="input-group-text bg-light"><i class="bi bi-lock text-secondary"></i></span>
                            <input type="password" name="password" id="c_pwd"
                                   class="form-control" placeholder="Min. 6 characters"
                                   minlength="6" autocomplete="new-password" required>
                            <button type="button" class="btn btn-outline-secondary"
                                    onclick="togglePwd('c_pwd','c_eye')">
                                <i class="bi bi-eye" id="c_eye"></i>
                            </button>
                        </div>
                    </div>

                    <!-- Confirm Password -->
                    <div class="col-md-6">
                        <label class="form-label required">Confirm Password</label>
                        <div class="input-group">
                            <span class="input-group-text bg-light"><i class="bi bi-lock-fill text-secondary"></i></span>
                            <input type="password" name="password2" id="c_pwd2"
                                   class="form-control" placeholder="Re-enter password"
                                   autocomplete="new-password" required>
                            <button type="button" class="btn btn-outline-secondary"
                                    onclick="togglePwd('c_pwd2','c_eye2')">
                                <i class="bi bi-eye" id="c_eye2"></i>
                            </button>
                        </div>
                        <div id="pwdMatchMsg" class="small mt-1"></div>
                    </div>
                </div>
            </div>
            <div class="modal-footer border-0 pt-0">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary px-4">
                    <i class="bi bi-check-circle me-1"></i>Create Account
                </button>
            </div>
        </form>
    </div>
</div>

<!-- ══════════════════════════════════════════════════════════
     MODAL: EDIT ACCOUNT
═══════════════════════════════════════════════════════════ -->
<div class="modal fade" id="modalEdit" tabindex="-1">
    <div class="modal-dialog">
        <form method="POST" class="modal-content">
            <input type="hidden" name="action" value="update">
            <input type="hidden" name="id" id="e_id">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title fw-bold">
                    <i class="bi bi-pencil text-primary me-2"></i>Edit Account:
                    <span id="e_username_label" class="text-muted fw-normal"></span>
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="row g-3">
                    <div class="col-12">
                        <label class="form-label required">Full Name</label>
                        <input type="text" name="full_name" id="e_full_name" class="form-control" required>
                    </div>
                    <div class="col-12">
                        <label class="form-label">Email</label>
                        <input type="email" name="email" id="e_email" class="form-control">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label required">Role</label>
                        <select name="role" id="e_role" class="form-select" required>
                            <option value="admin">Admin</option>
                            <option value="manager">Manager</option>
                            <option value="staff">Staff</option>
                        </select>
                    </div>
                    <div class="col-md-6 d-flex align-items-end">
                        <div class="form-check">
                            <input type="checkbox" name="is_active" id="e_active"
                                   class="form-check-input" value="1">
                            <label class="form-check-label fw-semibold" for="e_active">
                                Active Account
                            </label>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer border-0">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-check-circle me-1"></i>Save Changes
                </button>
            </div>
        </form>
    </div>
</div>

<!-- ══════════════════════════════════════════════════════════
     MODAL: RESET PASSWORD
═══════════════════════════════════════════════════════════ -->
<div class="modal fade" id="modalReset" tabindex="-1">
    <div class="modal-dialog">
        <form method="POST" class="modal-content" autocomplete="off">
            <input type="hidden" name="action" value="reset_password">
            <input type="hidden" name="id" id="r_id">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title fw-bold">
                    <i class="bi bi-key text-warning me-2"></i>Reset Password:
                    <span id="r_username_label" class="text-muted fw-normal"></span>
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-warning small py-2">
                    <i class="bi bi-exclamation-triangle me-1"></i>
                    The user will need to use the new password on their next login.
                </div>
                <div class="mb-3">
                    <label class="form-label required">New Password</label>
                    <div class="input-group">
                        <span class="input-group-text bg-light"><i class="bi bi-lock text-secondary"></i></span>
                        <input type="password" name="new_password" id="r_pwd"
                               class="form-control" placeholder="Min. 6 characters"
                               minlength="6" autocomplete="new-password" required>
                        <button type="button" class="btn btn-outline-secondary"
                                onclick="togglePwd('r_pwd','r_eye')">
                            <i class="bi bi-eye" id="r_eye"></i>
                        </button>
                    </div>
                </div>
                <div class="mb-1">
                    <label class="form-label required">Confirm New Password</label>
                    <div class="input-group">
                        <span class="input-group-text bg-light"><i class="bi bi-lock-fill text-secondary"></i></span>
                        <input type="password" name="new_password2" id="r_pwd2"
                               class="form-control" placeholder="Re-enter new password"
                               autocomplete="new-password" required>
                        <button type="button" class="btn btn-outline-secondary"
                                onclick="togglePwd('r_pwd2','r_eye2')">
                            <i class="bi bi-eye" id="r_eye2"></i>
                        </button>
                    </div>
                    <div id="r_pwdMatchMsg" class="small mt-1"></div>
                </div>

                <!-- Quick preset buttons -->
                <div class="mt-3">
                    <small class="text-muted">Quick set:</small>
                    <div class="d-flex gap-2 mt-1 flex-wrap">
                        <button type="button" class="btn btn-sm btn-outline-secondary"
                                onclick="setPreset('Admin@123')">Admin@123</button>
                        <button type="button" class="btn btn-sm btn-outline-secondary"
                                onclick="setPreset('Staff@123')">Staff@123</button>
                        <button type="button" class="btn btn-sm btn-outline-secondary"
                                onclick="setPreset('Nyh@2025')">Nyh@2025</button>
                    </div>
                </div>
            </div>
            <div class="modal-footer border-0">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-warning px-4">
                    <i class="bi bi-key me-1"></i>Reset Password
                </button>
            </div>
        </form>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
// ── Open Edit Modal ────────────────────────────────────
function openEdit(r) {
    document.getElementById('e_id').value          = r.id;
    document.getElementById('e_full_name').value   = r.full_name;
    document.getElementById('e_email').value       = r.email ?? '';
    document.getElementById('e_role').value        = r.role;
    document.getElementById('e_active').checked    = r.is_active == 1;
    document.getElementById('e_username_label').textContent = r.username;
    new bootstrap.Modal(document.getElementById('modalEdit')).show();
}

// ── Open Reset Modal ────────────────────────────────────
function openReset(id, username) {
    document.getElementById('r_id').value = id;
    document.getElementById('r_username_label').textContent = username;
    document.getElementById('r_pwd').value  = '';
    document.getElementById('r_pwd2').value = '';
    document.getElementById('r_pwdMatchMsg').textContent = '';
    new bootstrap.Modal(document.getElementById('modalReset')).show();
}

// ── Toggle password visibility ──────────────────────────
function togglePwd(inputId, iconId) {
    const inp = document.getElementById(inputId);
    const ico = document.getElementById(iconId);
    if (inp.type === 'password') {
        inp.type = 'text';
        ico.className = 'bi bi-eye-slash';
    } else {
        inp.type = 'password';
        ico.className = 'bi bi-eye';
    }
}

// ── Quick preset passwords ──────────────────────────────
function setPreset(val) {
    document.getElementById('r_pwd').value  = val;
    document.getElementById('r_pwd2').value = val;
    document.getElementById('r_pwdMatchMsg').innerHTML =
        '<span class="text-success"><i class="bi bi-check-circle me-1"></i>Password set to: ' + val + '</span>';
}

// ── Password match check (Create) ──────────────────────
document.getElementById('c_pwd2')?.addEventListener('input', function() {
    const p1  = document.getElementById('c_pwd').value;
    const msg = document.getElementById('pwdMatchMsg');
    if (!this.value) { msg.textContent = ''; return; }
    if (this.value === p1) {
        msg.innerHTML = '<span class="text-success"><i class="bi bi-check-circle me-1"></i>Passwords match</span>';
    } else {
        msg.innerHTML = '<span class="text-danger"><i class="bi bi-x-circle me-1"></i>Passwords do not match</span>';
    }
});

// ── Password match check (Reset) ───────────────────────
document.getElementById('r_pwd2')?.addEventListener('input', function() {
    const p1  = document.getElementById('r_pwd').value;
    const msg = document.getElementById('r_pwdMatchMsg');
    if (!this.value) { msg.textContent = ''; return; }
    if (this.value === p1) {
        msg.innerHTML = '<span class="text-success"><i class="bi bi-check-circle me-1"></i>Passwords match</span>';
    } else {
        msg.innerHTML = '<span class="text-danger"><i class="bi bi-x-circle me-1"></i>Passwords do not match</span>';
    }
});

// ── Auto dismiss alerts ─────────────────────────────────
setTimeout(() => {
    document.querySelectorAll('.alert-dismissible').forEach(el =>
        bootstrap.Alert.getOrCreateInstance(el).close());
}, 5000);
</script>
</body>
</html>