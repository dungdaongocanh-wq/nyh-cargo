<?php
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
requireLogin();
session_write_close();

set_time_limit(60);
ini_set('memory_limit', '128M');

$db  = getDB();
$hid = (int)($_GET['id'] ?? 0);
if (!$hid) die('Invalid HAWB ID.');

$stmt = $db->prepare("
    SELECT h.*,
           s.name    AS shipper_name,    s.address  AS shipper_address,
           s.city    AS shipper_city,    s.phone    AS shipper_phone,
           cn.name   AS cnee_name,       cn.address AS cnee_address,
           cn.city   AS cnee_city,       cn.phone   AS cnee_phone,
           cn.usci_no AS cnee_usci,      cn.account_no AS cnee_acct,
           ap1.iata_code AS origin_code,
           ap2.iata_code AS dest_code,
           m.mawb_no, m.flight_no, m.flight_date,
           al.code AS airline_code, al.name AS airline_name
    FROM hawbs h
    LEFT JOIN shippers   s   ON h.shipper_id    = s.id
    LEFT JOIN consignees cn  ON h.consignee_id  = cn.id
    LEFT JOIN airports   ap1 ON h.origin_id     = ap1.id
    LEFT JOIN airports   ap2 ON h.destination_id= ap2.id
    LEFT JOIN manifests  m   ON h.manifest_id   = m.id
    LEFT JOIN airlines   al  ON m.airline_id    = al.id
    WHERE h.id = ?
");
$stmt->bind_param('i', $hid);
$stmt->execute();
$h = $stmt->get_result()->fetch_assoc();
$stmt->close();
if (!$h) die('HAWB not found.');
$h = array_map(fn($v) => $v === null ? '' : (string)$v, $h);

$dimGroups = $db->query("
    SELECT length, width, height, qty_pieces
    FROM hawb_dim_groups WHERE hawb_id=$hid ORDER BY id
")->fetch_all(MYSQLI_ASSOC);

$templateFile = __DIR__ . '/../assets/templates/hawb_template.xlsx';
if (!file_exists($templateFile)) die('Template not found.');
$map = require __DIR__ . '/../config/hawb_excel_map.php';
if (!is_array($map)) die('Invalid cell map.');

function fmtNum($v): string {
    if ($v===null||$v==='') return '';
    $f=(float)$v; return ($f==floor($f))?(string)(int)$f:(string)round($f,2);
}

// Build data
$dimParts=[];
foreach ($dimGroups as $dg) {
    $l=fmtNum($dg['length']); $w=fmtNum($dg['width']);
    $hv=fmtNum($dg['height']); $q=(int)$dg['qty_pieces'];
    if ($l&&$w&&$hv&&$q) $dimParts[]="{$l}x{$w}x{$hv}/{$q}PCS";
}
$hp=[];
if ($h['notify_party']!='')  $hp[]=$h['notify_party'];
if ($h['handling_info']!='') $hp[]=$h['handling_info'];

$cellData=[
    'mawb_no'           => $h['mawb_no'],
    'hawb_no'           => $h['hawb_no'],
    'hawb_no_footer'    => $h['hawb_no'],
    'shipper_name'      => strtoupper($h['shipper_name']),
    'shipper_address'   => strtoupper($h['shipper_address']),
    'shipper_city'      => strtoupper($h['shipper_city']),
    'shipper_phone'     => $h['shipper_phone'],
    'consignee_name'    => strtoupper($h['cnee_name']),
    'consignee_address' => strtoupper($h['cnee_address']),
    'consignee_city'    => strtoupper($h['cnee_city']),
    'consignee_phone'   => $h['cnee_phone'],
    'consignee_acct'    => $h['cnee_acct'],
    'consignee_usci'    => $h['cnee_usci'],
    'issuing_carrier'   => strtoupper($h['airline_name']),
    'agent_name'        => COMPANY_NAME,
    'agent_iata'        => defined('COMPANY_IATA')?(string)COMPANY_IATA:'',
    'airport_departure' => $h['origin_code'],
    'airport_dest'      => $h['dest_code'],
    'routing_by1'       => $h['airline_code'],
    'flight_no'         => $h['flight_no'],
    'flight_date'       => $h['flight_date']!='' ? date('d-M-y',strtotime($h['flight_date'])) : '',
    'payment_term'      => $h['payment_term']?:'PP',
    'currency'          => $h['currency']?:'USD',
    'rate_class'        => $h['rate_class']?:'Q',
    'commodity_item_no' => $h['commodity_item_no'],
    'declared_carriage' => $h['declared_value_carriage']?:'NVD',
    'declared_customs'  => $h['declared_value_customs']?:'AS PER INV',
    'amount_insurance'  => $h['amount_insurance']?:'XXX',
    'accounting_info'   => $h['accounting_info']?:'FREIGHT PREPAID',
    'no_of_pieces'      => (string)(int)$h['no_of_pieces'],
    'gross_weight'      => (float)$h['gross_weight']>0      ? fmtNum($h['gross_weight'])      : '',
    'gross_weight_unit' => 'K',
    'volume_weight'     => (float)$h['volume_weight']>0     ? fmtNum($h['volume_weight'])     : '',
    'chargeable_weight' => (float)$h['chargeable_weight']>0 ? fmtNum($h['chargeable_weight']) : '',
    'commodity_line1'   => $h['commodity'],
    'commodity_line2'=>'','commodity_line3'=>'',
    'commodity_line4'=>'','commodity_line5'=>'',
    'dim_info'          => implode('  ',$dimParts),
    'handling_info'     => implode("\n",$hp),
    'execution_place'   => $h['origin_code'],
    'execution_date'    => date('d-M-Y'),
    'signature_origin'  => COMPANY_NAME,
];

$finalCellMap=[];
foreach ($map as $field=>$cell) {
    if (!is_string($cell)) continue;
    $cell=strtoupper(trim($cell));
    if ($cell===''||!isset($cellData[$field])) continue;
    $val=(string)$cellData[$field];
    if ($val!=='') $finalCellMap[$cell]=$val;
}
if (empty($finalCellMap)) die('Cell map empty.');

// Copy template
$outputDir=__DIR__.'/../assets/outputs/';
if (!is_dir($outputDir)) mkdir($outputDir,0755,true);
$safeNo=preg_replace('/[^A-Z0-9]/','',strtoupper($h['hawb_no']));
$outputFile=$outputDir.'HAWB_'.$safeNo.'_'.date('YmdHis').'.xlsx';
if (!copy($templateFile,$outputFile)) die('Cannot copy template.');

// Open ZIP
$zip=new ZipArchive();
if ($zip->open($outputFile)!==true){@unlink($outputFile);die('Cannot open zip.');}

// Find active sheet
$wbXml=(string)$zip->getFromName('xl/workbook.xml');
$wbRels=(string)$zip->getFromName('xl/_rels/workbook.xml.rels');
$relMap=[];
preg_match_all('/<Relationship\s[^>]*Id="([^"]*)"[^>]*Target="([^"]*)"/i',$wbRels,$rp,PREG_SET_ORDER);
foreach ($rp as $r){$t=$r[2];if(strpos($t,'xl/')!==0)$t='xl/'.ltrim($t,'/');$relMap[$r[1]]=$t;}
preg_match('/<workbookView[^>]*activeTab="(\d+)"/i',$wbXml,$am);
$activeTab=isset($am[1])?(int)$am[1]:0;
preg_match_all('/<sheet\b[^>]*r:id="([^"]*)"/i',$wbXml,$sr,PREG_SET_ORDER);
$activeRid=$sr[$activeTab][1]??($sr[0][1]??'');
$activeSheet=$relMap[$activeRid]??'xl/worksheets/sheet1.xml';
if (!$zip->getFromName($activeSheet)) $activeSheet='xl/worksheets/sheet1.xml';

// Load sharedStrings
$ssRaw=$zip->getFromName('xl/sharedStrings.xml');
$hasSS=(is_string($ssRaw)&&strlen($ssRaw)>0);

// Parse shared strings: value => index
$ssIndex=[];
if ($hasSS) {
    preg_match_all('/<si>(.*?)<\/si>/s',$ssRaw,$siM);
    foreach ($siM[1] as $idx=>$body) {
        $text='';
        preg_match_all('/<t[^>]*>(.*?)<\/t>/s',$body,$tM);
        foreach ($tM[1] as $t) $text.=html_entity_decode($t,ENT_XML1,'UTF-8');
        $ssIndex[$text]=$idx;
    }
}
$ssCount=count($ssIndex);

$sheetRaw=(string)$zip->getFromName($activeSheet);

// ══════════════════════════════════════════════════════
// CELL WRITER
// BUG FIX: bỏ \b sau closing quote — \b sau " không
// bao giờ match vì " là non-word character
// ══════════════════════════════════════════════════════
function writeFast(
    string &$sheet, string &$ssRaw,
    string $ref, string $value,
    bool $hasSS, array &$ssIndex, int &$ssCount
): void {
    if ($value==='') return;
    $isNum = is_numeric($value) && strpos($value,"\n")===false;

    // FIX: dùng (?=[\s">]) thay vì \b sau closing quote
    // Đảm bảo r="REF" không match r="REF0" hay r="REF1"
    $refQ  = preg_quote($ref,'/');
    $pat   = '/(<c\b[^>]*\br="'.$refQ.'(?=[\s">])[^>]*>)(.*?)(<\/c>)/s';

    if (preg_match($pat,$sheet)) {
        $sheet = preg_replace_callback($pat,
            function($m) use ($value,$isNum,$hasSS,&$ssRaw,&$ssIndex,&$ssCount) {
                $open  = $m[1];
                $close = $m[3];
                // Xoá attribute t="..." cũ
                $open  = preg_replace('/\s+t="[^"]*"/','',$open);

                if ($isNum) {
                    return $open.'<v>'.htmlspecialchars($value,ENT_XML1,'UTF-8').'</v>'.$close;
                } elseif ($hasSS) {
                    $idx = ssGet($value,$ssRaw,$ssIndex,$ssCount);
                    // Chèn t="s" trước dấu đóng >
                    $open = preg_replace('/>$/',' t="s">',$open);
                    return $open.'<v>'.$idx.'</v>'.$close;
                } else {
                    $open = preg_replace('/>$/',' t="inlineStr">',$open);
                    $sp=(strpos($value,"\n")!==false||$value!==trim($value))?' xml:space="preserve"':'';
                    $esc=htmlspecialchars($value,ENT_XML1,'UTF-8');
                    return $open.'<is><t'.$sp.'>'.$esc.'</t></is>'.$close;
                }
            },
            $sheet
        );
    } else {
        insertCell($sheet,$ssRaw,$ref,$value,$isNum,$hasSS,$ssIndex,$ssCount);
    }
}

function ssGet(string $v,string &$ssRaw,array &$ssIndex,int &$ssCount): int {
    if (isset($ssIndex[$v])) return (int)$ssIndex[$v];
    $idx=$ssCount;
    $ssIndex[$v]=$idx; $ssCount++;
    $sp=(strpos($v,"\n")!==false||$v!==trim($v))?' xml:space="preserve"':'';
    $esc=htmlspecialchars($v,ENT_XML1,'UTF-8');
    $ssRaw=str_replace('</sst>','<si><t'.$sp.'>'.$esc.'</t></si></sst>',$ssRaw);
    $ssRaw=preg_replace('/\bcount="\d+"/',"count=\"$ssCount\"",$ssRaw,1);
    $ssRaw=preg_replace('/\buniqueCount="\d+"/',"uniqueCount=\"$ssCount\"",$ssRaw,1);
    return $idx;
}

function colToNum2(string $col): int {
    $col=strtoupper($col);$r=0;
    for ($i=0;$i<strlen($col);$i++) $r=$r*26+(ord($col[$i])-64);
    return $r;
}

function insertCell(
    string &$sheet, string &$ssRaw,
    string $ref, string $value,
    bool $isNum, bool $hasSS,
    array &$ssIndex, int &$ssCount
): void {
    preg_match('/^([A-Z]+)(\d+)$/',$ref,$m);
    if (!$m) return;
    $rowNum=(int)$m[2];
    $colNum=colToNum2($m[1]);

    if ($isNum) {
        $cellXml='<c r="'.$ref.'"><v>'.htmlspecialchars($value,ENT_XML1,'UTF-8').'</v></c>';
    } elseif ($hasSS) {
        $idx=ssGet($value,$ssRaw,$ssIndex,$ssCount);
        $cellXml='<c r="'.$ref.'" t="s"><v>'.$idx.'</v></c>';
    } else {
        $sp=(strpos($value,"\n")!==false||$value!==trim($value))?' xml:space="preserve"':'';
        $cellXml='<c r="'.$ref.'" t="inlineStr"><is><t'.$sp.'>'.htmlspecialchars($value,ENT_XML1,'UTF-8').'</t></is></c>';
    }

    // Tìm row tương ứng — FIX: capture toàn bộ opening tag đúng cách
    $rowPat='/<row\b([^>]*)>(.*?)<\/row>/s';
    $found=false;
    $sheet=preg_replace_callback($rowPat,
        function($rm) use ($cellXml,$rowNum,$colNum,&$found) {
            // Lấy r="N" từ attributes
            if (!preg_match('/\br="(\d+)"/',$rm[1],$rn)) return $rm[0];
            if ((int)$rn[1]!==$rowNum) return $rm[0];
            $found=true;
            $inner=$rm[2];
            // Chèn cell vào đúng vị trí cột
            $inserted=false;
            $result=preg_replace_callback(
                '/<c\b[^>]*r="([A-Z]+\d+)"[^>]*>.*?<\/c>/s',
                function($cm) use ($cellXml,$colNum,&$inserted) {
                    if (!$inserted) {
                        preg_match('/^([A-Z]+)/',$cm[1],$cc);
                        if (colToNum2($cc[1])>$colNum) {
                            $inserted=true;
                            return $cellXml.$cm[0];
                        }
                    }
                    return $cm[0];
                },$inner
            );
            if (!$inserted) $result.=$cellXml;
            return '<row'.$rm[1].'>'.$result.'</row>';
        },$sheet);

    if (!$found) {
        // Row chưa tồn tại → tạo mới và chèn đúng thứ tự
        $newRow='<row r="'.$rowNum.'">'.$cellXml.'</row>';
        $inserted=false;
        $sheet=preg_replace_callback(
            '/<row\b[^>]*\br="(\d+)"[^>]*>/s',
            function($rm) use ($newRow,$rowNum,&$inserted) {
                if (!$inserted&&(int)$rm[1]>$rowNum) {
                    $inserted=true;
                    return $newRow.$rm[0];
                }
                return $rm[0];
            },$sheet);
        if (!$inserted) {
            $sheet=str_replace('</sheetData>',$newRow.'</sheetData>',$sheet);
        }
    }
}

// Ghi tất cả cells
foreach ($finalCellMap as $ref=>$value) {
    writeFast($sheetRaw,$ssRaw,$ref,$value,$hasSS,$ssIndex,$ssCount);
}

// Save ZIP
$zip->addFromString($activeSheet,$sheetRaw);
if ($hasSS) $zip->addFromString('xl/sharedStrings.xml',$ssRaw);
$zip->close();

$db->query("UPDATE hawbs SET is_printed=1,printed_at=NOW() WHERE id=$hid");

$filename='HAWB_'.$safeNo.'.xlsx';
while (ob_get_level()>0) ob_end_clean();
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="'.$filename.'"');
header('Content-Length: '.filesize($outputFile));
header('Cache-Control: no-store,no-cache,must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');
$fp=fopen($outputFile,'rb');
while(!feof($fp)){echo fread($fp,8192);flush();}
fclose($fp);
@unlink($outputFile);
exit;
