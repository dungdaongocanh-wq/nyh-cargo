<?php
// ── Session Configuration ─────────────────────────────
ini_set('session.gc_maxlifetime',  3600);   // Session tồn tại 1 giờ
ini_set('session.cookie_lifetime', 3600);
ini_set('session.cookie_httponly', 1);      // Chặn JS đọc cookie
ini_set('session.cookie_samesite', 'Lax');  // CSRF protection
ini_set('session.use_strict_mode', 1);      // Chặn session fixation

// Tránh session lock blocking (quan trọng cho export/download)
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}