<?php
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
requireLogin();

$docId = (int)($_GET['id'] ?? 0);
if (!$docId) { http_response_code(400); exit('Invalid ID'); }

$db = getDB();
$stmt = $db->prepare("
    SELECT d.*, m.assigned_staff_id
    FROM manifest_documents d
    JOIN manifests m ON d.manifest_id = m.id
    WHERE d.id = ?
");
$stmt->bind_param('i', $docId);
$stmt->execute();
$doc = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$doc) { http_response_code(404); exit('File not found'); }

if (currentUserRole() === ROLE_STAFF) {
    if ((int)($doc['assigned_staff_id'] ?? 0) !== currentUserId()) {
        http_response_code(403); exit('Access denied');
    }
}

$filePath = __DIR__ . '/../' . $doc['file_path'];
if (!file_exists($filePath)) { http_response_code(404); exit('File not found on disk'); }

$mime = $doc['mime_type'] ?: 'application/octet-stream';
header('Content-Type: ' . $mime);
header('Content-Disposition: attachment; filename="' . rawurlencode($doc['original_name']) . '"');
header('Content-Length: ' . filesize($filePath));
header('Cache-Control: no-cache');
readfile($filePath);
exit;
