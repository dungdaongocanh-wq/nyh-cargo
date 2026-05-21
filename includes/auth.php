<?php
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/constants.php';

/**
 * Require user to be logged in, redirect if not.
 */
function requireLogin(): void {
    if (empty($_SESSION['user_id'])) {
        header('Location: ' . BASE_URL . 'login.php');
        exit;
    }
}

/**
 * Require specific role(s). Pass string or array.
 */
function requireRole(string|array $roles): void {
    requireLogin();
    $roles = (array)$roles;
    if (!in_array($_SESSION['user_role'], $roles, true)) {
        http_response_code(403);
        include __DIR__ . '/../403.php';
        exit;
    }
}

function isAdmin(): bool   { return ($_SESSION['user_role'] ?? '') === ROLE_ADMIN; }
function isManager(): bool { return in_array($_SESSION['user_role'] ?? '', [ROLE_ADMIN, ROLE_MANAGER], true); }
function isStaff(): bool   { return isset($_SESSION['user_role']); }

function currentUserId(): int    { return (int)($_SESSION['user_id'] ?? 0); }
function currentUserName(): string { return $_SESSION['user_name'] ?? ''; }
function currentUserRole(): string { return $_SESSION['user_role'] ?? ''; }