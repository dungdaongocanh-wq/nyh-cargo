<?php
require_once 'config/session.php';
require_once 'config/database.php';
require_once 'config/constants.php';

// Already logged in
if (!empty($_SESSION['user_id'])) {
    header('Location: ' . BASE_URL . 'index.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($username === '' || $password === '') {
        $error = 'Please enter username and password.';
    } else {
        $db   = getDB();
        $stmt = $db->prepare("SELECT id, password, full_name, role, is_active FROM users WHERE username = ?");
        $stmt->bind_param('s', $username);
        $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$user || !$user['is_active']) {
            $error = 'Invalid username or account is disabled.';
        } elseif (!password_verify($password, $user['password'])) {
            $error = 'Invalid password.';
        } else {
            $_SESSION['user_id']   = $user['id'];
            $_SESSION['user_name'] = $user['full_name'];
            $_SESSION['user_role'] = $user['role'];
            header('Location: ' . BASE_URL . 'index.php');
            exit;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login | <?= APP_NAME ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #1a1d23 0%, #2c3e50 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .login-card {
            width: 100%;
            max-width: 400px;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 20px 60px rgba(0,0,0,.4);
        }
        .login-header {
            background: #1a1d23;
            padding: 2rem;
            text-align: center;
        }
        .login-body { padding: 2rem; }
        .form-control:focus { border-color: #0d6efd; box-shadow: 0 0 0 .2rem rgba(13,110,253,.15); }
    </style>
</head>
<body>
<div class="login-card bg-white">
    <!-- Header -->
    <div class="login-header">
        <img src="assets/img/logo.png" alt="Logo" height="48" class="mb-2"
             onerror="this.style.display='none'">
        <h4 class="text-white fw-bold mb-0"><?= APP_NAME ?></h4>
        <small class="text-secondary">Air Cargo Management System</small>
    </div>

    <!-- Body -->
    <div class="login-body">
        <h5 class="fw-semibold mb-4 text-center text-dark">Sign In</h5>

        <?php if ($error): ?>
        <div class="alert alert-danger py-2 small">
            <i class="bi bi-exclamation-triangle me-1"></i><?= htmlspecialchars($error) ?>
        </div>
        <?php endif; ?>

        <form method="POST" autocomplete="off">
            <div class="mb-3">
                <label class="form-label fw-medium">Username</label>
                <div class="input-group">
                    <span class="input-group-text bg-light">
                        <i class="bi bi-person text-secondary"></i>
                    </span>
                    <input type="text" name="username" class="form-control"
                           value="<?= htmlspecialchars($_POST['username'] ?? '') ?>"
                           placeholder="Enter username" autofocus required>
                </div>
            </div>
            <div class="mb-4">
                <label class="form-label fw-medium">Password</label>
                <div class="input-group">
                    <span class="input-group-text bg-light">
                        <i class="bi bi-lock text-secondary"></i>
                    </span>
                    <input type="password" name="password" id="passwordInput"
                           class="form-control" placeholder="Enter password" required>
                    <button type="button" class="btn btn-outline-secondary"
                            onclick="togglePwd()">
                        <i class="bi bi-eye" id="eyeIcon"></i>
                    </button>
                </div>
            </div>
            <button type="submit" class="btn btn-primary w-100 py-2 fw-semibold">
                <i class="bi bi-box-arrow-in-right me-2"></i>Login
            </button>
        </form>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
function togglePwd() {
    const inp = document.getElementById('passwordInput');
    const ico = document.getElementById('eyeIcon');
    if (inp.type === 'password') {
        inp.type = 'text';
        ico.className = 'bi bi-eye-slash';
    } else {
        inp.type = 'password';
        ico.className = 'bi bi-eye';
    }
}
</script>
</body>
</html>