<?php
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
requireLogin();

$db  = getDB();
$hid = (int)($_GET['id'] ?? 0);
if (!$hid) die('Invalid ID');

$stmt = $db->prepare("
    SELECT h.hawb_no, h.manifest_id
    FROM hawbs h
    WHERE h.id = ?
");
$stmt->bind_param('i', $hid);
$stmt->execute();
$h = $stmt->get_result()->fetch_assoc();
$stmt->close();
if (!$h) die('HAWB not found.');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>HAWB <?= e($h['hawb_no']) ?></title>
    <style>
        * { margin:0; padding:0; box-sizing:border-box; }
        body {
            font-family: Arial, sans-serif;
            color: #000;
            background: #f3f4f6;
        }
        @media print {
            .no-print { display: none !important; }
        }
        .print-bar {
            background: #1a56db;
            color: #fff;
            padding: 8px 16px;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .print-bar button,
        .print-bar a {
            background: #fff;
            color: #1a56db;
            border: none;
            border-radius: 6px;
            padding: 6px 18px;
            font-weight: 700;
            cursor: pointer;
            text-decoration: none;
            font-size: 14px;
        }
        .print-bar a.back-link {
            margin-left: auto;
        }
        #pdfFrame {
            width: 100%;
            height: calc(100vh - 50px);
            border: none;
            display: block;
            background: #fff;
        }
    </style>
</head>
<body>
<div class="print-bar no-print">
    <button type="button" onclick="document.getElementById('pdfFrame').contentWindow.print()">🖨 Print</button>
    <a href="hawb_excel.php?id=<?= $hid ?>">📥 Download Excel</a>
    <a href="hawb_pdf.php?id=<?= $hid ?>" target="_blank" rel="noopener">📄 Download PDF</a>
    <a class="back-link" href="../operations/manifest/edit.php?id=<?= $h['manifest_id'] ?>&weigh=<?= $hid ?>">← Back</a>
</div>
<iframe id="pdfFrame" src="hawb_pdf.php?id=<?= $hid ?>"></iframe>

<script>
if (new URLSearchParams(location.search).get('print') === '1') {
    document.getElementById('pdfFrame').onload = function() {
        this.contentWindow.print();
    };
}
</script>
</body>
</html>