<?php
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
requireLogin();

$type = $_GET['type'] ?? '';

$templates = [
    'shippers' => [
        'filename' => 'template_shippers.xlsx',
        'headers'  => ['code','name','address','city','country','phone','fax','email','tax_id'],
        'sample'   => ['SEMCO','SAMSUNG ELECTRO-MECHANICS CO.,LTD','150 MAEYEONG-RO SUWON','Suwon','South Korea','+82-31-200-0000','','',''],
    ],
    'consignees' => [
        'filename' => 'template_consignees.xlsx',
        'headers'  => ['code','name','address','city','country','phone','fax','email','account_no','usci_no'],
        'sample'   => ['JINGHAO','JIANGXI JINGHAO OPTICAL CO.,LTD','FUTURE CITY PARK NANCHANG','Nanchang','China','+86-791-0000','','','','913101...'],
    ],
    'customers' => [
        'filename' => 'template_customers.xlsx',
        'headers'  => ['code','name','address','city','country','phone','fax','email','account_no','usci_no','contact_full'],
        'sample'   => ['EASYWAY','EASYWAY LOGISTICS CO.,LTD','RM502 BLOCK C 469 WUSONG ROAD SHANGHAI','Shanghai','China','+86-21-6835 8521','','taocs2@smlogi.com','','913101...','TEL:+86-21-6835 8521 FAX:+86-21-6605 9169'],
    ],
];

if (!isset($templates[$type])) {
    die('Invalid template type.');
}

$tpl = $templates[$type];
$autoload = __DIR__ . '/../vendor/autoload.php';

if (!file_exists($autoload)) {
    // Fallback: download as CSV
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="' . str_replace('.xlsx', '.csv', $tpl['filename']) . '"');
    $out = fopen('php://output', 'w');
    fputcsv($out, $tpl['headers']);
    fputcsv($out, $tpl['sample']);
    fclose($out);
    exit;
}

require_once $autoload;

$spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
$sheet       = $spreadsheet->getActiveSheet();
$sheet->setTitle('Template');

// Headers - bold + background
$col = 1;
foreach ($tpl['headers'] as $h) {
    $cell = $sheet->getCellByColumnAndRow($col, 1);
    $cell->setValue(strtoupper($h));
    $cell->getStyle()->getFont()->setBold(true);
    $cell->getStyle()->getFill()
         ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
         ->getStartColor()->setRGB('1a56db');
    $cell->getStyle()->getFont()->getColor()->setRGB('FFFFFF');
    $sheet->getColumnDimensionByColumn($col)->setAutoSize(true);
    $col++;
}

// Sample row
$col = 1;
foreach ($tpl['sample'] as $val) {
    $sheet->getCellByColumnAndRow($col++, 2)->setValue($val);
}

header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="' . $tpl['filename'] . '"');
header('Cache-Control: max-age=0');

$writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
$writer->save('php://output');
exit;