<?php
/**
 * Excel Import Handler
 * Requires PhpSpreadsheet: composer require phpoffice/phpspreadsheet
 */

function checkSpreadsheet() {
    $autoload = __DIR__ . '/../vendor/autoload.php';
    if (!file_exists($autoload)) {
        return ['type' => 'danger', 'message' => 'PhpSpreadsheet not installed. Run: <code>composer require phpoffice/phpspreadsheet</code>'];
    }
    require_once $autoload;
    return null;
}

function readExcelRows($file) {
    $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($file['tmp_name']);
    $sheet       = $spreadsheet->getActiveSheet();
    $rows        = [];
    $first       = true;
    foreach ($sheet->getRowIterator() as $row) {
        if ($first) { $first = false; continue; } // skip header
        $cells = [];
        foreach ($row->getCellIterator() as $cell) {
            $cells[] = trim((string)$cell->getValue());
        }
        if (array_filter($cells)) $rows[] = $cells;
    }
    return $rows;
}

function importShippers($file, $db) {
    if (!$file || $file['error'] !== UPLOAD_ERR_OK)
        return ['type' => 'danger', 'message' => 'No file uploaded.'];
    if ($err = checkSpreadsheet()) return $err;

    $rows    = readExcelRows($file);
    $ok = $skip = $fail = 0;
    $skipExisting = isset($_POST['skip_existing']);

    foreach ($rows as $r) {
        $code    = strtoupper($r[0] ?? '');
        $name    = $r[1] ?? '';
        if (!$code || !$name) { $fail++; continue; }

        $addr    = $r[2] ?? '';
        $city    = $r[3] ?? '';
        $country = $r[4] ?? '';
        $phone   = $r[5] ?? '';
        $fax     = $r[6] ?? '';
        $email   = $r[7] ?? '';
        $tax     = $r[8] ?? '';

        if ($skipExisting) {
            $chk = $db->prepare("SELECT id FROM shippers WHERE code=?");
            $chk->bind_param('s', $code); $chk->execute();
            if ($chk->get_result()->num_rows > 0) { $skip++; $chk->close(); continue; }
            $chk->close();
        }

        $stmt = $db->prepare("INSERT INTO shippers (code,name,address,city,country,phone,fax,email,tax_id)
                               VALUES (?,?,?,?,?,?,?,?,?)
                               ON DUPLICATE KEY UPDATE name=VALUES(name),address=VALUES(address),
                               city=VALUES(city),country=VALUES(country),phone=VALUES(phone),
                               fax=VALUES(fax),email=VALUES(email),tax_id=VALUES(tax_id)");
        $stmt->bind_param('sssssssss', $code,$name,$addr,$city,$country,$phone,$fax,$email,$tax);
        $stmt->execute() ? $ok++ : $fail++;
        $stmt->close();
    }

    return ['type' => 'success', 'message' => "Import complete: <strong>$ok</strong> imported, <strong>$skip</strong> skipped, <strong>$fail</strong> failed."];
}

function importConsignees($file, $db) {
    if (!$file || $file['error'] !== UPLOAD_ERR_OK)
        return ['type' => 'danger', 'message' => 'No file uploaded.'];
    if ($err = checkSpreadsheet()) return $err;

    $rows = readExcelRows($file);
    $ok = $skip = $fail = 0;
    $skipExisting = isset($_POST['skip_existing']);

    foreach ($rows as $r) {
        $code = strtoupper($r[0] ?? ''); $name = $r[1] ?? '';
        if (!$code || !$name) { $fail++; continue; }

        if ($skipExisting) {
            $chk = $db->prepare("SELECT id FROM consignees WHERE code=?");
            $chk->bind_param('s', $code); $chk->execute();
            if ($chk->get_result()->num_rows > 0) { $skip++; $chk->close(); continue; }
            $chk->close();
        }

        $addr=$r[2]??''; $city=$r[3]??''; $country=$r[4]??'';
        $phone=$r[5]??''; $fax=$r[6]??''; $email=$r[7]??'';
        $acct=$r[8]??''; $usci=$r[9]??'';

        $stmt = $db->prepare("INSERT INTO consignees (code,name,address,city,country,phone,fax,email,account_no,usci_no)
                               VALUES (?,?,?,?,?,?,?,?,?,?)
                               ON DUPLICATE KEY UPDATE name=VALUES(name),address=VALUES(address)");
        $stmt->bind_param('ssssssssss', $code,$name,$addr,$city,$country,$phone,$fax,$email,$acct,$usci);
        $stmt->execute() ? $ok++ : $fail++;
        $stmt->close();
    }

    return ['type' => 'success', 'message' => "Import complete: <strong>$ok</strong> imported, <strong>$skip</strong> skipped, <strong>$fail</strong> failed."];
}

function importCustomers($file, $db) {
    if (!$file || $file['error'] !== UPLOAD_ERR_OK)
        return ['type' => 'danger', 'message' => 'No file uploaded.'];
    if ($err = checkSpreadsheet()) return $err;

    $rows = readExcelRows($file);
    $ok = $skip = $fail = 0;
    $skipExisting = isset($_POST['skip_existing']);

    foreach ($rows as $r) {
        $code = strtoupper($r[0] ?? ''); $name = $r[1] ?? '';
        if (!$code || !$name) { $fail++; continue; }

        if ($skipExisting) {
            $chk = $db->prepare("SELECT id FROM customers WHERE code=?");
            $chk->bind_param('s', $code); $chk->execute();
            if ($chk->get_result()->num_rows > 0) { $skip++; $chk->close(); continue; }
            $chk->close();
        }

        $addr=$r[2]??''; $city=$r[3]??''; $country=$r[4]??'';
        $phone=$r[5]??''; $fax=$r[6]??''; $email=$r[7]??'';
        $acct=$r[8]??''; $usci=$r[9]??''; $contact=$r[10]??'';

        $stmt = $db->prepare("INSERT INTO customers (code,name,address,city,country,phone,fax,email,account_no,usci_no,contact_full)
                               VALUES (?,?,?,?,?,?,?,?,?,?,?)
                               ON DUPLICATE KEY UPDATE name=VALUES(name),address=VALUES(address)");
        $stmt->bind_param('sssssssssss', $code,$name,$addr,$city,$country,$phone,$fax,$email,$acct,$usci,$contact);
        $stmt->execute() ? $ok++ : $fail++;
        $stmt->close();
    }

    return ['type' => 'success', 'message' => "Import complete: <strong>$ok</strong> imported, <strong>$skip</strong> skipped, <strong>$fail</strong> failed."];
}