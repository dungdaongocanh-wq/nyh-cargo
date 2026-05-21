<?php
require_once __DIR__ . '/../../config/constants.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';
requireRole([ROLE_ADMIN, ROLE_MANAGER]);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect(BASE_URL . 'operations/hawb/index.php');
}

$db          = getDB();
$hid         = (int)($_POST['hawb_id']     ?? 0);
$manifest_id = (int)($_POST['manifest_id'] ?? 0);

if (!$hid || !$manifest_id) {
    setFlash('danger', 'Invalid request.');
    redirect(BASE_URL . 'operations/hawb/index.php');
}

// Load HAWB + check exists
$stmt = $db->prepare("
    SELECT h.hawb_no, h.manifest_id, m.status AS manifest_status
    FROM hawbs h
    JOIN manifests m ON h.manifest_id = m.id
    WHERE h.id = ?
");
$stmt->bind_param('i', $hid);
$stmt->execute();
$hawb = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$hawb) {
    setFlash('danger', 'HAWB not found.');
    redirect(BASE_URL . 'operations/hawb/index.php');
}

// Completed → Admin only
if ($hawb['manifest_status'] === 'completed' && !isAdmin()) {
    setFlash('danger', 'Cannot delete HAWB from a completed manifest.');
    redirect(BASE_URL . 'operations/manifest/edit.php?id=' . $manifest_id);
}

$hawbNo = $hawb['hawb_no'];

// Delete DIM groups
$db->query("DELETE FROM hawb_dim_groups WHERE hawb_id = $hid");

// Delete HAWB
$stmt = $db->prepare("DELETE FROM hawbs WHERE id = ? AND manifest_id = ?");
$stmt->bind_param('ii', $hid, $manifest_id);
$stmt->execute();
$affected = $stmt->affected_rows;
$stmt->close();

if ($affected > 0) {
    // Recalc manifest totals
    $db->query("
        UPDATE manifests SET
            total_pieces = (SELECT COALESCE(SUM(no_of_pieces),0) FROM hawbs WHERE manifest_id=$manifest_id),
            total_gw     = (SELECT COALESCE(SUM(gross_weight),0)  FROM hawbs WHERE manifest_id=$manifest_id)
        WHERE id = $manifest_id
    ");

    setFlash('success', "HAWB <strong>$hawbNo</strong> deleted successfully.");
} else {
    setFlash('danger', 'Delete failed.');
}

// Redirect back to manifest
$back = $_POST['back'] ?? 'manifest';
if ($back === 'list') {
    redirect(BASE_URL . 'operations/hawb/index.php');
} else {
    redirect(BASE_URL . 'operations/manifest/edit.php?id=' . $manifest_id);
}