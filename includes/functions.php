<?php

// ── Generate HAWB No ─────────────────────────────────────
function generateHawbNo(): array {
    $db = getDB();
    $ym = date('ym');

    $stmt = $db->prepare(
        "INSERT INTO `hawb_sequence` (`year_month`, `last_seq`)
         VALUES (?, 1)
         ON DUPLICATE KEY UPDATE `last_seq` = `last_seq` + 1"
    );
    $stmt->bind_param('s', $ym);
    $stmt->execute();
    $stmt->close();

    $row = $db->query(
        "SELECT `last_seq` FROM `hawb_sequence` WHERE `year_month` = '$ym'"
    )->fetch_assoc();

    $seq = (int)$row['last_seq'];
    $yy  = substr($ym, 0, 2);
    $mm  = substr($ym, 2, 2);

    $hawbNo = HAWB_PREFIX . $yy . $mm . str_pad($seq, 3, '0', STR_PAD_LEFT) . HAWB_SUFFIX;

    return [
        'hawb_no'    => $hawbNo,
        'seq_year'   => $yy,
        'seq_month'  => $mm,
        'seq_number' => $seq,
    ];
}

function peekNextHawbNo(): string {
    $db = getDB();
    $ym = date('ym');
    $row = $db->query(
        "SELECT COALESCE(MAX(last_seq), 0) + 1 AS next_seq
         FROM hawb_sequence WHERE year_month = '$ym'"
    )->fetch_assoc();
    $seq = (int)($row['next_seq'] ?? 1);
    $yy  = substr($ym, 0, 2);
    $mm  = substr($ym, 2, 2);
    return HAWB_PREFIX . $yy . $mm . str_pad($seq, 3, '0', STR_PAD_LEFT) . HAWB_SUFFIX;
}

// ── Flash messages ───────────────────────────────────────
function setFlash(string $type, string $message): void {
    if (session_status() === PHP_SESSION_NONE) session_start();
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

function getFlash(): ?array {
    if (session_status() === PHP_SESSION_NONE) session_start();
    if (!isset($_SESSION['flash'])) return null;
    $flash = $_SESSION['flash'];
    unset($_SESSION['flash']);
    return $flash;
}

// ── Redirect ─────────────────────────────────────────────
function redirect(string $url): void {
    header('Location: ' . $url);
    exit;
}

// ── Escape output ─────────────────────────────────────────
function e(?string $str): string {
    return htmlspecialchars((string)($str ?? ''), ENT_QUOTES, 'UTF-8');
}

// ── Format date ───────────────────────────────────────────
function fmtDate(?string $date, string $format = 'd/m/Y'): string {
    if (!$date) return '—';
    return date($format, strtotime($date));
}

// ── Status badge ──────────────────────────────────────────
function statusBadge(string $status): string {
    $map = [
        'draft'     => ['secondary', 'pencil-square', 'Draft'],
        'confirmed' => ['primary',   'check-circle',  'Confirmed'],
        'completed' => ['success',   'check-all',     'Completed'],
        'cancelled' => ['danger',    'x-circle',      'Cancelled'],
    ];
    $s = $map[$status] ?? ['secondary', 'question-circle', ucfirst($status)];
    return "<span class=\"badge bg-{$s[0]}\"><i class=\"bi bi-{$s[1]} me-1\"></i>{$s[2]}</span>";
}

// ── Weight calculators ────────────────────────────────────
function calcVolumeWeight(float $l, float $w, float $h, int $divisor = 6000): float {
    if ($l <= 0 || $w <= 0 || $h <= 0) return 0.0;
    return round(($l * $w * $h) / $divisor, 2);
}

function calcChargeableWeight(float $gw, float $vw): float {
    return ceil(max($gw, $vw) * 2) / 2;
}