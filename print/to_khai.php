<?php
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
checkLogin();
$pageTitle = 'Tra Cứu Cont — In Danh Sách Hàng Hoá';
?>
<!DOCTYPE html>
<html lang="vi">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title><?= $pageTitle ?> | <?= APP_NAME ?></title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
  <link href="<?= BASE_URL ?>assets/css/app.css" rel="stylesheet">
  <script src="https://cdn.jsdelivr.net/npm/xlsx/dist/xlsx.full.min.js"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/jsbarcode@3.11.6/dist/JsBarcode.all.min.js"></script>
  <style>
    body { font-family: Arial, sans-serif; background: #f0f2f5; margin: 0; padding: 0; }
    #preview-section { overflow: auto; }
    .preview-mode #sidebar, .preview-mode .navbar { display: none !important; }
    .preview-mode .layout-wrapper { margin-top: 0 !important; min-height: 100vh !important; }
    .preview-mode #main-content { padding: 0 !important; background: #e0e0e0 !important; }

    .upload-wrapper {
      max-width: 640px; margin: 40px auto; background: #fff;
      border-radius: 10px; padding: 32px; box-shadow: 0 2px 12px rgba(0,0,0,0.1);
    }
    .upload-wrapper h2 { text-align: center; color: #003087; margin-bottom: 24px; }
    .upload-area {
      border: 2px dashed #003087; border-radius: 8px; padding: 32px;
      text-align: center; cursor: pointer; color: #555; background: #f8faff;
      transition: background 0.2s;
    }
    .upload-area:hover, .upload-area.dragover { background: #dce8ff; }
    .upload-area input[type="file"] { display: none; }
    .upload-area .icon { font-size: 48px; margin-bottom: 8px; }
    .btn-row { display: flex; gap: 12px; margin-top: 20px; justify-content: center; flex-wrap: wrap; }
    .tc-btn { padding: 10px 24px; border: none; border-radius: 6px; font-size: 15px; cursor: pointer; font-weight: bold; }
    .tc-btn-primary   { background: #003087; color: #fff; }
    .tc-btn-primary:hover   { background: #00205c; }
    .tc-btn-success   { background: #28a745; color: #fff; }
    .tc-btn-success:hover   { background: #1e7e34; }
    .tc-btn-secondary { background: #6c757d; color: #fff; }
    .tc-btn-secondary:hover { background: #545b62; }
    .tc-btn-warning   { background: #fd7e14; color: #fff; }
    .tc-btn-warning:hover   { background: #e0650e; }
    .tc-btn-info      { background: #17a2b8; color: #fff; }
    .tc-btn-info:hover      { background: #117a8b; }
    .tc-btn:disabled  { opacity: 0.45; cursor: not-allowed; }

    .filename-label { margin-top: 12px; color: #003087; font-size: 13px; font-weight: bold; max-height: 120px; overflow-y: auto; }
    .filename-label div { padding: 2px 0; }
    .error-msg { color: red; margin-top: 10px; font-size: 13px; text-align: center; }

    #progress-wrap { display: none; margin-top: 16px; }
    #progress-bar-outer { background: #e0e0e0; border-radius: 6px; height: 14px; overflow: hidden; }
    #progress-bar-inner { background: #28a745; height: 100%; width: 0%; transition: width 0.3s; border-radius: 6px; }
    #progress-label { font-size: 13px; color: #333; margin-top: 6px; text-align: center; }

    /* ===== PREVIEW ===== */
    #preview-section { display: none; background: #e0e0e0; padding: 20px; }
    .print-controls {
      max-width: 794px; margin: 0 auto 12px auto;
      display: flex; gap: 10px; justify-content: space-between; align-items: center; flex-wrap: wrap;
    }
    .print-controls-left  { display: flex; gap: 10px; align-items: center; flex-wrap: wrap; }
    .print-controls-right { display: flex; gap: 10px; align-items: center; flex-wrap: wrap; }
    #file-nav-label { font-size: 13px; color: #333; font-weight: bold; white-space: nowrap; }

    /* Wrapper cố định 794px */
    #phieu-wrapper {
      width: 794px;
      margin: 0 auto;
      background: #fff;
      box-shadow: 0 2px 12px rgba(0,0,0,0.2);
      box-sizing: border-box;
    }

    /* ===== STYLES PHIẾU ===== */
    .TextMediumBold   { font-family:"Times New Roman",serif; color:#000; font-size:11pt; font-weight:bold; }
    .lbl_NgayThangNam { font-family:"Times New Roman",serif; color:#000; font-size:12pt; font-style:italic; }
    .TextLarge        { font-family:"Times New Roman",serif; color:#000; font-size:13pt; font-weight:bold; }
    .TieuDe3          { font-family:"Times New Roman",serif; color:#000; font-size:12pt; font-weight:bold; font-style:normal; }
    .TextMedium       { font-family:"Times New Roman",serif; color:#000; font-size:11pt; }
    .TextMediumItalic { font-family:"Times New Roman",serif; color:#000; font-size:11pt; font-style:italic; }
    .divGhiChuItalic  {
      width:100%; font-family:"Times New Roman",serif; color:#000; font-size:11pt;
      text-align:left; text-decoration:underline; font-style:italic;
    }
    .GridView { border:1px solid black; border-collapse:collapse; background:white; width:100%; table-layout:fixed; }
    .GridView th, .GridView td {
      border:1px solid black; padding:4px;
      font-family:"Times New Roman",serif; font-size:10.5pt;
      word-wrap: break-word; overflow-wrap: break-word;
    }
    .GridView th { font-weight:bold; text-align:center; }

    #phieu-inner { padding: 22px 30px 18px 30px; box-sizing: border-box; }

    .page-number { text-align:right; font-family:"Times New Roman",serif; font-size:11pt; color:#000; margin-bottom:4px; }

    #barcode-container { text-align:right; line-height:0; }
    #barcode-svg { display:inline-block; border:none; outline:none; box-shadow:none; }

    @media print {
      .upload-wrapper,.print-controls,#upload-section { display:none !important; }
      #preview-section { display:block !important; background:#fff !important; padding:0 !important; }
      #phieu-wrapper { box-shadow:none !important; width:100% !important; }
    }
  </style>
</head>
<body>
<?php require_once __DIR__ . '/../includes/navbar.php'; ?>
<div class="d-flex layout-wrapper" style="margin-top:56px;min-height:calc(100vh - 56px);">
<?php require_once __DIR__ . '/../includes/sidebar.php'; ?>

<main class="flex-grow-1 p-4" id="main-content">

<!-- ===== PHẦN UPLOAD ===== -->
<div id="upload-section">
  <div class="upload-wrapper">
    <h2>🗂️ IN DANH SÁCH HÀNG HOÁ — Upload Tờ Khai</h2>
    <div class="upload-area" id="drop-zone" onclick="document.getElementById('file-input').click()">
      <div class="icon">📄</div>
      <div>Kéo thả hoặc <strong>nhấn để chọn</strong> file tờ khai</div>
      <div style="font-size:12px;color:#888;margin-top:4px;">Hỗ trợ <strong>nhiều file cùng lúc</strong> — .xlsx / .xls</div>
      <input type="file" id="file-input" accept=".xlsx,.xls" multiple onchange="handleFileSelect(event)" />
    </div>
    <div class="filename-label" id="filename-label"></div>
    <div class="error-msg"     id="error-msg"></div>

    <div id="progress-wrap">
      <div id="progress-bar-outer"><div id="progress-bar-inner"></div></div>
      <div id="progress-label">Đang xử lý...</div>
    </div>

    <div class="btn-row">
      <button class="tc-btn tc-btn-primary" onclick="xemTruoc(0)">🔍 Xem Trước</button>
      <button class="tc-btn tc-btn-success" onclick="xuatMotFile(0)">⬇️ Xuất PDF (file đầu)</button>
      <button class="tc-btn tc-btn-warning" onclick="xuatLoat()">📦 Xuất PDF Loạt</button>
    </div>
  </div>
</div>

<!-- ===== PHẦN PREVIEW ===== -->
<div id="preview-section">
  <div class="print-controls">
    <div class="print-controls-left">
      <button class="tc-btn tc-btn-secondary" onclick="quayLai()">← Quay lại</button>
      <button class="tc-btn tc-btn-info"      id="btn-prev" onclick="navFile(-1)" disabled>‹ Trước</button>
      <span id="file-nav-label">1 / 1</span>
      <button class="tc-btn tc-btn-info"      id="btn-next" onclick="navFile(1)"  disabled>Sau ›</button>
    </div>
    <div class="print-controls-right">
      <button class="tc-btn tc-btn-success" onclick="xuatMotFile(currentFileIdx)">⬇️ Xuất PDF</button>
    </div>
  </div>

  <div id="phieu-wrapper">
    <div id="phieu-inner">
      <div class="page-number">Page 1 of 1</div>

      <!-- HEADER -->
      <table style="width:100%;border:none;border-collapse:collapse;">
        <tbody>
          <tr><td width="35%">&nbsp;</td><td width="65%">&nbsp;</td></tr>
          <tr>
            <td width="35%" style="text-align:center;vertical-align:bottom;">
              <span class="TextMediumBold" id="TenCucHaiQuan"></span>
            </td>
            <td width="65%"></td>
          </tr>
          <tr>
            <td width="35%" style="text-align:center;vertical-align:top;">
              <span class="TextMediumBold" id="lbl_TenChiCucHaiQuan"></span>
            </td>
            <td width="65%"></td>
          </tr>
          <tr>
            <td width="35%" style="text-align:center;">
              <hr width="45%" size="1" style="height:1px;color:#000;margin:6px auto;">
            </td>
            <td width="65%" style="text-align:right;vertical-align:middle;padding-right:0;">
              <div id="barcode-container">
                <svg id="barcode-svg"></svg>
              </div>
            </td>
          </tr>
          <tr>
            <td width="35%">&nbsp;</td>
            <td width="65%" style="text-align:right;">
              <span class="lbl_NgayThangNam" id="lbl_NgayThangNam"></span>
            </td>
          </tr>
          <tr><td colspan="2" style="height:14px;"></td></tr>
          <tr>
            <td colspan="2" style="text-align:center;padding:0;">
              <div class="TextLarge" style="line-height:1.5;">DANH SÁCH HÀNG HÓA</div>
              <div class="TextLarge" style="line-height:1.5;">ĐỦ ĐIỀU KIỆN QUA KHU VỰC GIÁM SÁT HẢI QUAN</div>
              <div class="TieuDe3"   style="line-height:1.6;margin-top:2px;">Tờ khai không phải niêm phong</div>
            </td>
          </tr>
          <tr><td colspan="2" style="height:12px;"></td></tr>
        </tbody>
      </table>

      <!-- THÔNG TIN TỜ KHAI -->
      <table style="width:100%;border:none;border-collapse:collapse;">
        <tbody>
          <tr>
            <td colspan="2" style="text-align:left;padding:2px 0;">
              <span class="TextMediumBold">&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;1. Chi cục hải quan giám sát:&nbsp;</span>
              <span class="TextMedium" id="lbl_ChiCucHaiQuanGiamSat"></span>
            </td>
          </tr>
          <tr>
            <td colspan="2" style="text-align:left;padding:2px 0;">
              <span class="TextMediumBold">&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;2. Đơn vị XNK:&nbsp;</span>
              <span class="TextMedium" id="lbl_TenDonViXNK"></span>
            </td>
          </tr>
          <tr>
            <td style="text-align:left;padding:2px 0;width:60%;">
              <span class="TextMediumBold">&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;3. Mã số thuế:&nbsp;</span>
              <span class="TextMedium" id="lbl_MaSoThue"></span>
            </td>
            <td style="text-align:left;padding:2px 0;width:40%;">
              <span class="TextMediumBold">6. Ngày tờ khai:&nbsp;</span>
              <span class="TextMedium" id="lbl_NgayToKhai"></span>
            </td>
          </tr>
          <tr>
            <td style="text-align:left;padding:2px 0;width:60%;">
              <span class="TextMediumBold">&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;4. Số tờ khai:&nbsp;</span>
              <span class="TextMedium" id="lbl_SoToKhai"></span>
            </td>
            <td style="text-align:left;padding:2px 0;width:40%;">
              <span class="TextMediumBold">7. Loại hình:</span>
              <span class="TextMedium" id="lbl_TenLoaiHinh"></span>
            </td>
          </tr>
          <tr>
            <td style="text-align:left;padding:2px 0;width:60%;">
              <span class="TextMediumBold">&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;5. Trạng thái tờ khai:&nbsp;</span>
              <span class="TextMedium" id="lbl_TrangThaiToKhai">Thông quan</span>
            </td>
            <td style="text-align:left;padding:2px 0;width:40%;">
              <span class="TextMediumBold">8. Luồng:</span>
              <span class="TextMedium" id="lbl_Luong"></span>
            </td>
          </tr>
          <tr>
            <td colspan="2" style="text-align:left;padding:2px 0;">
              <span class="TextMediumBold">&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;9. Số quản lý hàng hóa:&nbsp;</span>
              <span class="TextMedium" id="lbl_SoQuanLyHangHoa"></span>
            </td>
          </tr>
        </tbody>
      </table>

      <br>

      <!-- BẢNG HÀNG HÓA -->
      <table class="GridView">
        <colgroup>
          <col style="width:7%;">
          <col style="width:20%;">
          <col style="width:21%;">
          <col style="width:29%;">
          <col style="width:23%;">
        </colgroup>
        <tbody>
          <tr style="background:white;font-weight:bold;">
            <th>STT</th>
            <th>SỐ LƯỢNG HÀNG<br>(1)</th>
            <th>TỔNG TRỌNG LƯỢNG HÀNG<br>(2)</th>
            <th>LƯỢNG HÀNG HÓA<br>THỰC TẾ QUA KHU VỰC<br>GIÁM SÁT HẢI QUAN<br>(3)</th>
            <th>XÁC NHẬN CỦA<br>CÔNG CHỨC HẢI QUAN<br>(4)</th>
          </tr>
          <tr>
            <td align="right">1</td>
            <td align="left" id="td_SoKien"></td>
            <td align="left" id="td_TrongLuong"></td>
            <td align="center" valign="middle" style="height:55px;"></td>
            <td align="center" valign="middle"></td>
          </tr>
        </tbody>
      </table>

      <!-- GHI CHÚ -->
      <br>
      <div class="divGhiChuItalic">Ghi chú:</div>
      <div class="TextMedium" style="font-size:10pt;line-height:1.6;">
        - Cột số (1) lấy từ tiêu chí "Số lượng" trên phần "General" của tờ khai hải quan.<br>
        - Cột số (2) lấy từ tiêu chí "Tổng trọng lượng hàng" trên phần "General" của tờ khai hải quan.<br>
        - Trường hợp hàng hóa được đưa qua KVGS nhiều lần thì đối với từng lần đưa hàng qua KVGS, công chức hải quan thực hiện:<br>
        &nbsp;&nbsp;&nbsp;&nbsp;+ Cột số (3): ghi rõ lượng hàng từng lần qua KVGS.<br>
        &nbsp;&nbsp;&nbsp;&nbsp;+ Cột số (4): ghi ngày, tháng, năm; ký, đóng dấu công chức.<br>
        - Trường hợp giá trị tại cột (1):<br>
        &nbsp;&nbsp;&nbsp;&nbsp;+ khác 1 thì theo dõi lượng hàng tại cột (3) tương ứng theo cột (1);<br>
        &nbsp;&nbsp;&nbsp;&nbsp;+ bằng 1 thì theo dõi lượng hàng tại cột (3) tương ứng theo cột (2).<br>
      </div>

      <!-- FOOTER -->
      <hr width="100%" size="1" style="height:1px;color:#000;margin-top:16px;">
      <table style="width:100%;border:none;">
        <tbody>
          <tr>
            <td style="width:60%;text-align:left;">
              <span class="TextMediumItalic" id="lbl_ThoiGianLayDuLieu"></span>
            </td>
            <td style="width:40%;text-align:right;"></td>
          </tr>
        </tbody>
      </table>
    </div><!-- /phieu-inner -->
  </div><!-- /phieu-wrapper -->
</div><!-- /preview-section -->

</main>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?= BASE_URL ?>assets/js/app.js"></script>
<script>
document.getElementById('sidebarToggle')?.addEventListener('click', function () {
  document.getElementById('sidebar')?.classList.toggle('d-none');
});
</script>
<script>
// =====================================================================
// DATA — KHO
// =====================================================================
const KHO_DATA = [
  {maKho:'01DDEA0',tenKho:'KHO FEDEX'},{maKho:'01DDEA1',tenKho:'CN HN CT CPN SAI GON'},
  {maKho:'01DDEB0',tenKho:'KHO LIEN TINH'},{maKho:'01DDEC0',tenKho:'KHO ALSW'},
  {maKho:'01DDEC1',tenKho:'KHO DHL-'},{maKho:'01DDEC2',tenKho:'KHO EMS'},
  {maKho:'01DDEC3',tenKho:'KHO HOP NHAT'},{maKho:'01DDEC4',tenKho:'LOGISTICS CPN BD HN'},
  {maKho:'01DDEC5',tenKho:'CT TNHH S1 LOGISTICS'},{maKho:'01DDEC6',tenKho:'CT BUU CHINH VIETTEL'},
  {maKho:'01DDEC7',tenKho:'CT CHUYEN PHAT NHANH'},{maKho:'01DDEC8',tenKho:'CT CP CN THAN TOC'},
  {maKho:'01DDEC9',tenKho:'CT SG SAGAWA VN'},{maKho:'01DDECA',tenKho:'CTY VT TM QUANG VINH'},
  {maKho:'01DDECB',tenKho:'KHO UPS'},{maKho:'01DDECC',tenKho:'TNT EXPR WORLDWIDE'},
  {maKho:'01DDECD',tenKho:'CTY JETLINK VIET NAM'},{maKho:'01DDECE',tenKho:'CT INTERSERCO MYDINH'},
  {maKho:'01DDECI',tenKho:'CTY CPN QTE ATL'},{maKho:'01DDECK',tenKho:'CTY CP YG EXPRESS'},
  {maKho:'01DDECL',tenKho:'CTY ITS TOAN CAU'},{maKho:'01DDECM',tenKho:'CTY DV CPN TAN PHONG'},
  {maKho:'01DDECN',tenKho:'CTY CPN S F EXPRESS'},{maKho:'01DDECO',tenKho:'CTY FADO EXPRESS VN'},
  {maKho:'01DDECP',tenKho:'XD PT NGUON LUC VIET'},{maKho:'01DDECQ',tenKho:'CTY CPN HB'},
  {maKho:'01DDED0',tenKho:'CTY CP DV HK BAC NAM'},{maKho:'01DDED5',tenKho:'CT HAUCAN THONG TIEN'},
  {maKho:'01DDED6',tenKho:'CTY ATO EXPRESS'},{maKho:'01DDED7',tenKho:'CPN YD NGUON LUCVIET'},
  {maKho:'01DDED8',tenKho:'CPN SAO THANG 8 QT'},{maKho:'01DDED9',tenKho:'KHO CPBC DAI QUAN'},
  {maKho:'01DDEE0',tenKho:'KHO CPCN THAN TOC'},{maKho:'01DDEE1',tenKho:'KHO BUU CHINH EMS'},
  {maKho:'01DDEE2',tenKho:'KHO CPN EMS'},{maKho:'01B1A02',tenKho:'CTY DVHH NOI BAI (X)'},
  {maKho:'01B1A03',tenKho:'TTDV GA HH NOIBAI(X)'},{maKho:'01B1A04',tenKho:'CT TNHH MTV ALSC1'},
];

// =====================================================================
// DATA — HQ_FULL (tra cứu mã U30/K78 → tên chi cục giám sát)
// =====================================================================
const HQ_FULL = [
  {ma:'01B1',ten:'Hải quan cửa khẩu sân bay quốc tế Nội Bài',chiCuc:'01'},
  {ma:'01B5',ten:'Hải quan cửa khẩu sân bay quốc tế Nội Bài',chiCuc:'01'},
  {ma:'01B6',ten:'Hải quan cửa khẩu sân bay quốc tế Nội Bài',chiCuc:'01'},
  {ma:'01DD',ten:'Hải quan Chuyển phát nhanh',chiCuc:'01'},
  {ma:'01E1',ten:'Hải quan Bắc Hà Nội',chiCuc:'01'},
  {ma:'01IK',ten:'Hải quan Gia Thụy',chiCuc:'01'},
  {ma:'01M1',ten:'Hải quan Hòa Lạc',chiCuc:'01'},
  {ma:'01NV',ten:'Hải quan Khu công nghiệp Bắc Thăng Long',chiCuc:'01'},
  {ma:'01PJ',ten:'Hải quan Phú Thọ',chiCuc:'01'},
  {ma:'01PL',ten:'Hải quan Hòa Lạc',chiCuc:'01'},
  {ma:'01PQ',ten:'Hải quan Hòa Bình',chiCuc:'01'},
  {ma:'01PR',ten:'Hải quan Vĩnh Phúc',chiCuc:'01'},
  {ma:'01SI',ten:'Hải quan ga đường sắt quốc tế Yên Viên',chiCuc:'01'},
  {ma:'18A3',ten:'Hải quan Bắc Ninh',chiCuc:'18'},
  {ma:'18B1',ten:'Hải quan Thái Nguyên',chiCuc:'18'},
  {ma:'18B2',ten:'Hải quan Thái Nguyên',chiCuc:'18'},
  {ma:'18BC',ten:'Hải quan Bắc Giang',chiCuc:'18'},
  {ma:'18BE',ten:'Hải quan Yên Phong',chiCuc:'18'},
  {ma:'18ID',ten:'Hải quan Tiên Sơn',chiCuc:'18'},
  {ma:'02B1',ten:'Hải quan cửa khẩu sân bay quốc tế Tân Sơn Nhất',chiCuc:'02'},
  {ma:'02CI',ten:'Hải quan cửa khẩu cảng Sài Gòn khu vực 1',chiCuc:'02'},
  {ma:'02CV',ten:'Hải quan cửa khẩu cảng Sài Gòn khu vực 2',chiCuc:'02'},
  {ma:'02DS',ten:'Hải quan Chuyển phát nhanh',chiCuc:'02'},
  {ma:'02F1',ten:'Hải quan khu chế xuất Linh Trung',chiCuc:'02'},
  {ma:'02F2',ten:'Hải quan khu chế xuất Linh Trung',chiCuc:'02'},
  {ma:'02F3',ten:'Hải quan Khu công nghệ cao',chiCuc:'02'},
  {ma:'02H1',ten:'Hải quan cửa khẩu cảng Sài Gòn khu vực 3',chiCuc:'02'},
  {ma:'02H2',ten:'Hải quan cửa khẩu cảng Sài Gòn khu vực 3',chiCuc:'02'},
  {ma:'02H3',ten:'Hải quan cửa khẩu cảng Sài Gòn khu vực 3',chiCuc:'02'},
  {ma:'02IK',ten:'Hải quan cửa khẩu cảng Sài Gòn khu vực 4',chiCuc:'02'},
  {ma:'02PG',ten:'Hải quan khu công nghệ cao',chiCuc:'02'},
  {ma:'02PJ',ten:'Hải quan khu chế xuất Tân Thuận',chiCuc:'02'},
  {ma:'02XE',ten:'Hải quan khu chế xuất Tân Thuận',chiCuc:'02'},
  {ma:'03CC',ten:'Hải quan cửa khẩu cảng Hải Phòng khu vực 1',chiCuc:'03'},
  {ma:'03CD',ten:'Hải quan Thái Bình',chiCuc:'03'},
  {ma:'03CE',ten:'Hải quan cửa khẩu cảng Hải Phòng khu vực 2',chiCuc:'03'},
  {ma:'03EE',ten:'Hải quan cửa khẩu cảng Đình Vũ',chiCuc:'03'},
  {ma:'03NK',ten:'Hải quan khu chế xuất và khu công nghiệp Hải Phòng',chiCuc:'03'},
  {ma:'03PA',ten:'Hải quan khu chế xuất và khu công nghiệp Hải Phòng',chiCuc:'03'},
  {ma:'03PJ',ten:'Hải quan Hải Dương',chiCuc:'03'},
  {ma:'03PL',ten:'Hải quan Hưng Yên',chiCuc:'03'},
  {ma:'03TG',ten:'Hải quan cửa khẩu cảng Hải Phòng khu vực 3',chiCuc:'03'},
  {ma:'43CN',ten:'Hải quan cửa khẩu cảng tổng hợp Bình Dương',chiCuc:'02'},
  {ma:'43IH',ten:'Hải quan Sóng Thần',chiCuc:'02'},
  {ma:'43K1',ten:'Hải quan khu công nghiệp Mỹ Phước',chiCuc:'02'},
  {ma:'43K4',ten:'Hải quan khu công nghiệp Mỹ Phước',chiCuc:'02'},
  {ma:'43ND',ten:'Hải quan khu công nghiệp Sóng Thần',chiCuc:'02'},
  {ma:'43NF',ten:'Hải quan khu công nghiệp Việt Nam - Singapore',chiCuc:'02'},
  {ma:'43NG',ten:'Hải quan khu công nghiệp Việt Hương',chiCuc:'02'},
  {ma:'43PB',ten:'Hải quan khu công nghiệp Việt Hương',chiCuc:'02'},
  {ma:'43PC',ten:'Hải quan Thủ Dầu Một',chiCuc:'02'},
  {ma:'51BE',ten:'Hải quan cửa khẩu cảng Cát Lở',chiCuc:'02'},
  {ma:'51C1',ten:'Hải quan cửa khẩu cảng Phú Mỹ',chiCuc:'02'},
  {ma:'51C2',ten:'Hải quan cửa khẩu cảng Phú Mỹ',chiCuc:'02'},
  {ma:'51CB',ten:'Hải quan cửa khẩu cảng Vũng Tàu',chiCuc:'02'},
  {ma:'51CH',ten:'Hải quan cửa khẩu cảng Côn Đảo',chiCuc:'02'},
  {ma:'51CI',ten:'Hải quan cửa khẩu cảng Cái Mép',chiCuc:'02'},
];

// =====================================================================
// DATA — CHI_CUC_MAP (cột I → J của CSV)
// =====================================================================
const CHI_CUC_MAP = {
  '01': 'Chi cục Hải quan khu vực I',
  '02': 'Chi cục Hải quan khu vực II',
  '03': 'Chi cục Hải quan khu vực III',
  '18': 'Chi cục Hải quan khu vực V',
};

// =====================================================================
// DATA — HQ_BY_VIETTAT
// Tra cứu từ tên viết tắt (ô L7 hoặc J7) → tên chi cục + cục
// Logic: L7/J7 (viết tắt, cột D) → cột E (chiCuc) → CHI_CUC_MAP (cột J)
//                                 → cột C (tên đầy đủ)
// =====================================================================
const HQ_BY_VIETTAT = [
  // Chi cục 01
  {vietTat:'HQNOIBAI',   ten:'Hải quan cửa khẩu sân bay quốc tế Nội Bài',           chiCuc:'01'},
  {vietTat:'HQCPNHN',    ten:'Hải quan Chuyển phát nhanh',                            chiCuc:'01'},
  {vietTat:'HQBACHN',    ten:'Hải quan Bắc Hà Nội',                                   chiCuc:'01'},
  {vietTat:'HQGIATHUY',  ten:'Hải quan Gia Thụy',                                     chiCuc:'01'},
  {vietTat:'HQHOALAC',   ten:'Hải quan Hòa Lạc',                                      chiCuc:'01'},
  {vietTat:'HQBACTL',    ten:'Hải quan Khu công nghiệp Bắc Thăng Long',               chiCuc:'01'},
  {vietTat:'HQVIETTRI',  ten:'Hải quan Phú Thọ',                                      chiCuc:'01'},
  {vietTat:'HQHOABINH',  ten:'Hải quan Hòa Bình',                                     chiCuc:'01'},
  {vietTat:'HQVINHPHUC', ten:'Hải quan Vĩnh Phúc',                                    chiCuc:'01'},
  {vietTat:'HQYENVIEN',  ten:'Hải quan ga đường sắt quốc tế Yên Viên',               chiCuc:'01'},
  // Chi cục 18 (khu vực V)
  {vietTat:'HQBACNINH',  ten:'Hải quan Bắc Ninh',                                     chiCuc:'18'},
  {vietTat:'HQTNGUYEN',  ten:'Hải quan Thái Nguyên',                                  chiCuc:'18'},
  {vietTat:'HQBACGIANG', ten:'Hải quan Bắc Giang',                                    chiCuc:'18'},
  {vietTat:'HQYENPHONG', ten:'Hải quan Yên Phong',                                    chiCuc:'18'},
  {vietTat:'HQTIENSON',  ten:'Hải quan Tiên Sơn',                                     chiCuc:'18'},
  // Chi cục 02
  {vietTat:'HQTSNHAT',   ten:'Hải quan cửa khẩu sân bay quốc tế Tân Sơn Nhất',      chiCuc:'02'},
  {vietTat:'HQSGKV1',    ten:'Hải quan cửa khẩu cảng Sài Gòn khu vực 1',            chiCuc:'02'},
  {vietTat:'HQSGKV2',    ten:'Hải quan cửa khẩu cảng Sài Gòn khu vực 2',            chiCuc:'02'},
  {vietTat:'HQCPNHCM',   ten:'Hải quan Chuyển phát nhanh',                            chiCuc:'02'},
  {vietTat:'HQLTRUNG',   ten:'Hải quan khu chế xuất Linh Trung',                      chiCuc:'02'},
  {vietTat:'HQCNC',      ten:'Hải quan Khu công nghệ cao',                            chiCuc:'02'},
  {vietTat:'HQSGKV3',    ten:'Hải quan cửa khẩu cảng Sài Gòn khu vực 3',            chiCuc:'02'},
  {vietTat:'HQSGKV4',    ten:'Hải quan cửa khẩu cảng Sài Gòn khu vực 4',            chiCuc:'02'},
  {vietTat:'HQTTHUAN',   ten:'Hải quan khu chế xuất Tân Thuận',                       chiCuc:'02'},
  {vietTat:'CTHOPBD',    ten:'Hải quan cửa khẩu cảng tổng hợp Bình Dương',           chiCuc:'02'},
  {vietTat:'SONGTHANBD', ten:'Hải quan Sóng Thần',                                    chiCuc:'02'},
  {vietTat:'DNVMPBD',    ten:'Hải quan khu công nghiệp Mỹ Phước',                    chiCuc:'02'},
  {vietTat:'DBBHQMP',    ten:'Hải quan khu công nghiệp Mỹ Phước',                    chiCuc:'02'},
  {vietTat:'KCNSTHANBD', ten:'Hải quan khu công nghiệp Sóng Thần',                   chiCuc:'02'},
  {vietTat:'KCNVNSGBD',  ten:'Hải quan khu công nghiệp Việt Nam - Singapore',        chiCuc:'02'},
  {vietTat:'VHUONGBD',   ten:'Hải quan khu công nghiệp Việt Hương',                  chiCuc:'02'},
  {vietTat:'HQTDMOT',    ten:'Hải quan Thủ Dầu Một',                                  chiCuc:'02'},
  {vietTat:'CATLOVT',    ten:'Hải quan cửa khẩu cảng Cát Lở',                        chiCuc:'02'},
  {vietTat:'NVPMYBRVT',  ten:'Hải quan cửa khẩu cảng Phú Mỹ',                       chiCuc:'02'},
  {vietTat:'PSAPMVTAU',  ten:'Hải quan cửa khẩu cảng Phú Mỹ',                       chiCuc:'02'},
  {vietTat:'CSANBAYVT',  ten:'Hải quan cửa khẩu cảng Vũng Tàu',                     chiCuc:'02'},
  {vietTat:'CONDAOVT',   ten:'Hải quan cửa khẩu cảng Côn Đảo',                      chiCuc:'02'},
  {vietTat:'CAIMEPVT',   ten:'Hải quan cửa khẩu cảng Cái Mép',                      chiCuc:'02'},
  // Chi cục 03
  {vietTat:'HQHPKV1',    ten:'Hải quan cửa khẩu cảng Hải Phòng khu vực 1',          chiCuc:'03'},
  {vietTat:'HQTHAIBINH', ten:'Hải quan Thái Bình',                                    chiCuc:'03'},
  {vietTat:'HQHPKV2',    ten:'Hải quan cửa khẩu cảng Hải Phòng khu vực 2',          chiCuc:'03'},
  {vietTat:'HQDINHVU',   ten:'Hải quan cửa khẩu cảng Đình Vũ',                       chiCuc:'03'},
  {vietTat:'HQKCXKCNHP', ten:'Hải quan khu chế xuất và khu công nghiệp Hải Phòng',  chiCuc:'03'},
  {vietTat:'HQHAIDUONG', ten:'Hải quan Hải Dương',                                    chiCuc:'03'},
  {vietTat:'HQHUNGYEN',  ten:'Hải quan Hưng Yên',                                     chiCuc:'03'},
  {vietTat:'HQHPKV3',    ten:'Hải quan cửa khẩu cảng Hải Phòng khu vực 3',          chiCuc:'03'},
];

// =====================================================================
// DATA — LOẠI HÌNH
// =====================================================================
const LOAI_HINH_DATA = [
  {ma:'A11',ten:'Nhập kinh doanh tiêu dùng'},{ma:'A12',ten:'Nhập kinh doanh sản xuất'},
  {ma:'A21',ten:'Chuyển tiêu thụ nội địa từ nguồn tạm nhập'},{ma:'A31',ten:'Nhập hàng hóa đã xuất khẩu'},
  {ma:'A41',ten:'Nhập kinh doanh của doanh nghiệp thực hiện quyền nhập khẩu'},
  {ma:'A42',ten:'Thay đổi mục đích sử dụng hoặc chuyển tiêu thụ nội địa từ các loại hình khác, trừ tạm nhập'},
  {ma:'A43',ten:'Nhập khẩu hàng hóa thuộc Chương trình ưu đãi thuế'},
  {ma:'A44',ten:'Tạm nhập hàng hóa bán tại cửa hàng miễn thuế'},
  {ma:'A45',ten:'Nhập khẩu hàng chuyển phát nhanh'},
  {ma:'AAA',ten:'Loại hình dùng cho tờ khai nộp phí (OLA)'},{ma:'AEO',ten:'Loại hình dành cho doanh nghiệp ưu tiên'},
  {ma:'B11',ten:'Xuất kinh doanh'},{ma:'B12',ten:'Xuất sau khi đã tạm xuất'},
  {ma:'B13',ten:'Xuất khẩu hàng đã nhập khẩu'},{ma:'B14',ten:'Xuất khẩu hàng chuyển phát nhanh'},
  {ma:'C11',ten:'Hàng nước ngoài gửi kho ngoại quan'},{ma:'C12',ten:'Hàng xuất từ kho ngoại quan đi nước ngoài'},
  {ma:'C21',ten:'Hàng đưa vào khu phi thuế quan'},{ma:'C22',ten:'Hàng đưa ra khỏi khu phi thuế quan'},
  {ma:'CNC01',ten:'CT Nhập chế xuất sản xuất'},{ma:'CNC02',ten:'CT Nhập chế xuất đầu tư'},
  {ma:'CNC03',ten:'CT Nhập chế xuất tiêu dùng'},{ma:'CNC04',ten:'CT Nhập chế xuất cho mục đích khác'},
  {ma:'CXC01',ten:'CT Xuất chế xuất sản xuất'},{ma:'CXC02',ten:'CT Xuất chế xuất đầu tư'},
  {ma:'CXC04',ten:'CT Xuất chế xuất cho mục đích khác'},
  {ma:'E11',ten:'Nhập nguyên liệu của DNCX từ nước ngoài'},{ma:'E13',ten:'Nhập hàng hóa khác vào DNCX'},
  {ma:'E15',ten:'Nhập nguyên liệu của doanh nghiệp chế xuất từ nội địa'},
  {ma:'E21',ten:'Nhập nguyên liệu, vật tư để gia công cho thương nhân nước ngoài'},
  {ma:'E23',ten:'Nhập nguyên liệu, vật tư gia công từ hợp đồng khác chuyển sang'},
  {ma:'E31',ten:'Nhập nguyên liệu sản xuất xuất khẩu'},{ma:'E33',ten:'Nhập nguyên liệu vào kho bảo thuế'},
  {ma:'E41',ten:'Nhập sản phẩm thuê gia công ở nước ngoài'},
  {ma:'E42',ten:'Xuất khẩu sản phẩm của Doanh nghiệp chế xuất'},
  {ma:'E46',ten:'Hàng của Doanh nghiệp chế xuất vào nội địa để gia công'},
  {ma:'E52',ten:'Xuất sản phẩm gia công cho thương nhân nước ngoài'},
  {ma:'E54',ten:'Xuất nguyên liệu gia công từ hợp đồng khác sang'},
  {ma:'E56',ten:'Xuất sản phẩm gia công giao hàng tại nội địa'},
  {ma:'E62',ten:'Xuất sản phẩm Sản xuất xuất khẩu'},
  {ma:'E82',ten:'Xuất nguyên liệu, vật tư thuê gia công ở nước ngoài'},
  {ma:'G11',ten:'Tạm nhập hàng kinh doanh tạm nhập tái xuất'},
  {ma:'G12',ten:'Tạm nhập máy móc, thiết bị phục vụ dự án có thời hạn'},
  {ma:'G13',ten:'Tạm nhập miễn thuế'},{ma:'G14',ten:'Tạm nhập khác'},
  {ma:'G21',ten:'Tái xuất hàng kinh doanh tạm nhập tái xuất'},
  {ma:'G22',ten:'Tái xuất thiết bị, máy móc phục vụ dự án có thời hạn'},
  {ma:'G23',ten:'Tái xuất miễn thuế hàng tạm nhập'},{ma:'G24',ten:'Tái xuất khác'},
  {ma:'G51',ten:'Tái nhập hàng đã tạm xuất'},{ma:'G61',ten:'Tạm xuất hàng hóa'},
  {ma:'H11',ten:'Hàng nhập khẩu khác'},{ma:'H21',ten:'Xuất khẩu hàng khác'},
];

// =====================================================================
// HÀM TRA CỨU HEADER (từ tên viết tắt L7/J7)
// Dòng trên : viết tắt → chiCuc → CHI_CUC_MAP (cột J)
// Dòng dưới : viết tắt → tên đầy đủ chi cục (cột C)
// =====================================================================
function getTenCucByVietTat(vt) {
  const key = String(vt || '').trim().toUpperCase();
  const r = HQ_BY_VIETTAT.find(x => x.vietTat.toUpperCase() === key);
  if (!r) return key;
  return CHI_CUC_MAP[r.chiCuc] || ('Chi cục ' + r.chiCuc);
}
function getTenChiCucByVietTat(vt) {
  const key = String(vt || '').trim().toUpperCase();
  const r = HQ_BY_VIETTAT.find(x => x.vietTat.toUpperCase() === key);
  return r ? r.ten : key;
}

// =====================================================================
// HÀM TRA CỨU GIÁM SÁT THEO MÃ U30/K78
// =====================================================================
function getChiCucGiamSat(u30) {
  u30 = String(u30 || '').trim();
  const p4   = u30.substring(0, 4).toUpperCase();
  const hq   = HQ_FULL.find(r => r.ma.trim().toUpperCase() === p4);
  const kh   = KHO_DATA.find(r => r.maKho.trim().toUpperCase() === u30.toUpperCase());
  const tenHQ  = hq  ? hq.ten  : p4;
  const tenKho = kh  ? kh.tenKho : '';
  return tenKho ? `${tenHQ} - ${u30}: ${tenKho} - 1` : `${tenHQ} - ${u30}`;
}

function getTenLoaiHinh(code) {
  code = String(code || '').trim().toUpperCase();
  let r = LOAI_HINH_DATA.find(r => r.ma.trim().toUpperCase() === code);
  if (!r) r = LOAI_HINH_DATA.find(r => code.startsWith(r.ma.trim().toUpperCase()));
  return r ? r.ten : code;
}

// =====================================================================
// ĐỌC CELL
// =====================================================================
function stripTime(s) {
  return String(s || '').trim().replace(/\s+\d{1,2}:\d{2}(:\d{2})?(\s*(AM|PM))?/gi, '').trim();
}
function getCellValue(sheet, addr) {
  const cell = sheet[addr];
  if (!cell) return '';
  if (cell.t === 'd') {
    const d = cell.v;
    return `${String(d.getDate()).padStart(2,'0')}/${String(d.getMonth()+1).padStart(2,'0')}/${d.getFullYear()}`;
  }
  return stripTime(cell.w !== undefined ? cell.w : (cell.v !== undefined ? String(cell.v) : ''));
}
function formatLuong(v) {
  v = String(v).trim().toUpperCase();
  if (v === '1') return 'Xanh';
  if (v === '2') return 'Vàng';
  if (v === '3' || v === '3D' || v === '3C') return 'Đỏ';
  return v;
}
function formatNgay(d) {
  return `Ngày  ${String(d.getDate()).padStart(2,'0')}  tháng    ${String(d.getMonth()+1).padStart(2,'0')}  năm    ${d.getFullYear()}`;
}
function formatThoiGian(d) {
  const h = d.getHours() % 12 || 12;
  const ampm = d.getHours() >= 12 ? 'PM' : 'AM';
  return `${String(d.getDate()).padStart(2,'0')}/${String(d.getMonth()+1).padStart(2,'0')}/${d.getFullYear()} ${String(h).padStart(2,'0')}:${String(d.getMinutes()).padStart(2,'0')} ${ampm}`;
}
function taoBarcode(val) {
  try {
    val = String(val || '').replace(/\s/g, '');
    if (!val) return;
    JsBarcode('#barcode-svg', val, {
      format:'CODE128', width:1.5, height:50,
      displayValue:false, margin:2, lineColor:'#000', background:'#fff'
    });
    const s = document.getElementById('barcode-svg');
    s.setAttribute('width','200'); s.setAttribute('height','50');
    s.style.cssText = 'border:none;outline:none;box-shadow:none;';
  } catch(e) { console.warn(e); }
}

// =====================================================================
// ĐIỀN PHIẾU
// Nhận biết loại tờ khai theo ký tự đầu của số tờ khai (E4):
//   "1" → Nhập khẩu
//   "3" → Xuất khẩu
// =====================================================================
function dienVaoPhieu(wb) {
  const sheet    = wb.Sheets[wb.SheetNames[0]];
  const soToKhai = getCellValue(sheet, 'E4');
  const firstDigit = String(soToKhai).trim().charAt(0);

  let loaiHinhMa, u30Val, l7Val, maSoThue, ngayFrom, ngayTo,
      donViXNK, luong, soQuanLy, soKien, trongLuong;

  if (firstDigit === '3') {
    // ===== TỜ KHAI XUẤT KHẨU =====
    loaiHinhMa = getCellValue(sheet, 'L6');
    u30Val     = getCellValue(sheet, 'K78');
    l7Val      = getCellValue(sheet, 'J7');
    maSoThue   = getCellValue(sheet, 'F13');
    ngayFrom   = getCellValue(sheet, 'F8');
    ngayTo     = getCellValue(sheet, 'I46');
    donViXNK   = getCellValue(sheet, 'F14');
    luong      = getCellValue(sheet, 'F6');
    soQuanLy   = getCellValue(sheet, 'H39');
    soKien     = getCellValue(sheet, 'H40');
    trongLuong = getCellValue(sheet, 'H41');
  } else {
    // ===== TỜ KHAI NHẬP KHẨU (số đầu "1") =====
    loaiHinhMa = getCellValue(sheet, 'P6');
    u30Val     = getCellValue(sheet, 'U30');
    l7Val      = getCellValue(sheet, 'L7');
    maSoThue   = getCellValue(sheet, 'H10');
    ngayFrom   = getCellValue(sheet, 'G8');
    ngayTo     = getCellValue(sheet, 'U35');
    donViXNK   = getCellValue(sheet, 'H11');
    luong      = getCellValue(sheet, 'I6');
    soQuanLy   = getCellValue(sheet, 'D31');
    soKien     = getCellValue(sheet, 'K36');
    trongLuong = getCellValue(sheet, 'K37');
  }

  const now = new Date();

  // HEADER — dòng trên: L7/J7 → chiCuc → CHI_CUC_MAP
  //          dòng dưới: L7/J7 → tên đầy đủ
  document.getElementById('TenCucHaiQuan').textContent        = getTenCucByVietTat(l7Val);
  document.getElementById('lbl_TenChiCucHaiQuan').textContent = getTenChiCucByVietTat(l7Val);

  document.getElementById('lbl_NgayThangNam').textContent         = formatNgay(now);
  document.getElementById('lbl_ChiCucHaiQuanGiamSat').textContent = getChiCucGiamSat(u30Val);
  document.getElementById('lbl_TenDonViXNK').textContent          = donViXNK;
  document.getElementById('lbl_MaSoThue').textContent             = maSoThue;
  document.getElementById('lbl_NgayToKhai').textContent           = `${ngayFrom} - ${ngayTo}`;
  document.getElementById('lbl_SoToKhai').textContent             = soToKhai;
  document.getElementById('lbl_TenLoaiHinh').textContent          = getTenLoaiHinh(loaiHinhMa);
  document.getElementById('lbl_TrangThaiToKhai').textContent      = 'Thông quan';
  document.getElementById('lbl_Luong').textContent                = formatLuong(luong);
  document.getElementById('lbl_SoQuanLyHangHoa').textContent      = soQuanLy;
  document.getElementById('td_SoKien').textContent                = `${soKien}  PACKAGE`;
  document.getElementById('td_TrongLuong').textContent            = `${trongLuong}  Kilogam`;
  document.getElementById('lbl_ThoiGianLayDuLieu').textContent    = 'Kết xuất dữ liệu lúc: ' + formatThoiGian(now);
  taoBarcode(soToKhai);
  return { soToKhai, soQuanLy };
}

// =====================================================================
// QUẢN LÝ FILE
// =====================================================================
let fileList = [];
let currentFileIdx = 0;

function setPreviewMode(enabled) {
  const mainContent = document.getElementById('main-content');
  document.body.classList.toggle('preview-mode', !!enabled);
  if (mainContent) {
    mainContent.style.padding = enabled ? '0' : '1.5rem';
    mainContent.style.background = enabled ? '#e0e0e0' : '';
  }
}

const dropZone = document.getElementById('drop-zone');
dropZone.addEventListener('dragover',  e => { e.preventDefault(); dropZone.classList.add('dragover'); });
dropZone.addEventListener('dragleave', () => dropZone.classList.remove('dragover'));
dropZone.addEventListener('drop', e => {
  e.preventDefault(); dropZone.classList.remove('dragover');
  setFiles(Array.from(e.dataTransfer.files));
});
function handleFileSelect(e) { setFiles(Array.from(e.target.files)); }
function setFiles(files) {
  const valid = files.filter(f => f.name.match(/\.(xlsx|xls)$/i));
  if (!valid.length) { document.getElementById('error-msg').textContent = '⚠️ Không có file .xlsx/.xls'; return; }
  document.getElementById('error-msg').textContent = '';
  fileList = valid;
  document.getElementById('filename-label').innerHTML =
    `<strong>Đã chọn ${valid.length} file:</strong>` +
    valid.map((f, i) => `<div>📎 ${i+1}. ${f.name}</div>`).join('');
}
function readFileAsWB(file) {
  return new Promise((res, rej) => {
    const r = new FileReader();
    r.onload = e => {
      try { res(XLSX.read(new Uint8Array(e.target.result), { type:'array', cellText:false, cellDates:true })); }
      catch(err) { rej(err); }
    };
    r.onerror = rej;
    r.readAsArrayBuffer(file);
  });
}

// =====================================================================
// ĐIỀU HƯỚNG FILE TRONG PREVIEW
// =====================================================================
function updateNavUI() {
  const total = fileList.length;
  document.getElementById('file-nav-label').textContent =
    `${currentFileIdx + 1} / ${total}  —  ${fileList[currentFileIdx].name}`;
  document.getElementById('btn-prev').disabled = (currentFileIdx === 0);
  document.getElementById('btn-next').disabled = (currentFileIdx === total - 1);
}

async function navFile(delta) {
  const newIdx = currentFileIdx + delta;
  if (newIdx < 0 || newIdx >= fileList.length) return;
  currentFileIdx = newIdx;
  const wb = await readFileAsWB(fileList[currentFileIdx]);
  dienVaoPhieu(wb);
  updateNavUI();
}

// =====================================================================
// PDF — dùng kỹ thuật của Version8:
//   window.scrollTo(0,0) + scrollX/scrollY:0
//   KHÔNG đặt width/windowWidth — để html2canvas tự đo element
//   → tránh blank PDF và tránh lệch trái
// =====================================================================
async function savePdf(filename) {
  // Chờ barcode render xong
  await new Promise(r => setTimeout(r, 400));

  // Scroll về đầu trang để html2canvas tính đúng offset (fix lệch trái)
  window.scrollTo(0, 0);
  await new Promise(r => setTimeout(r, 80));

  await html2pdf()
    .set({
      margin:   0,
      filename,
      image:       { type: 'jpeg', quality: 0.98 },
      html2canvas: {
        scale:   2,
        useCORS: true,
        logging: false,
        scrollX: 0,
        scrollY: 0,
        // KHÔNG đặt width/windowWidth để tránh blank PDF
      },
      jsPDF: { unit: 'mm', format: 'a4', orientation: 'portrait' },
    })
    .from(document.getElementById('phieu-wrapper'))
    .save();
}

// =====================================================================
// ACTIONS
// =====================================================================
async function xemTruoc(idx) {
  if (!fileList.length) { document.getElementById('error-msg').textContent = '⚠️ Chưa chọn file!'; return; }
  currentFileIdx = typeof idx === 'number' ? idx : 0;
  const wb = await readFileAsWB(fileList[currentFileIdx]);
  dienVaoPhieu(wb);
  document.getElementById('upload-section').style.display  = 'none';
  document.getElementById('preview-section').style.display = 'block';
  setPreviewMode(true);
  updateNavUI();
}

function quayLai() {
  document.getElementById('upload-section').style.display  = 'block';
  document.getElementById('preview-section').style.display = 'none';
  setPreviewMode(false);
}

async function xuatMotFile(idx) {
  if (!fileList.length) { document.getElementById('error-msg').textContent = '⚠️ Chưa chọn file!'; return; }
  currentFileIdx = typeof idx === 'number' ? idx : 0;
  const wb = await readFileAsWB(fileList[currentFileIdx]);
  const { soToKhai, soQuanLy } = dienVaoPhieu(wb);
  document.getElementById('upload-section').style.display  = 'none';
  document.getElementById('preview-section').style.display = 'block';
  setPreviewMode(true);
  updateNavUI();
  await savePdf(`${soToKhai || 'ToKhai'}_${soQuanLy || 'HangHoa'}.pdf`);
}

async function xuatLoat() {
  if (!fileList.length) { document.getElementById('error-msg').textContent = '⚠️ Chưa chọn file!'; return; }
  const total = fileList.length;
  const pw = document.getElementById('progress-wrap');
  const pb = document.getElementById('progress-bar-inner');
  const pl = document.getElementById('progress-label');
  pw.style.display = 'block'; pb.style.width = '0%';
  document.getElementById('upload-section').style.display  = 'none';
  document.getElementById('preview-section').style.display = 'block';
  setPreviewMode(true);

  let ok = 0; const errs = [];
  for (let i = 0; i < total; i++) {
    currentFileIdx = i;
    pl.textContent = `Đang xuất ${i+1}/${total}: ${fileList[i].name}`;
    pb.style.width = `${Math.round(i / total * 100)}%`;
    updateNavUI();
    try {
      const wb = await readFileAsWB(fileList[i]);
      const { soToKhai, soQuanLy } = dienVaoPhieu(wb);
      await savePdf(`${soToKhai || 'ToKhai'}_${soQuanLy || 'HangHoa'}.pdf`);
      ok++;
    } catch(e) { console.error(fileList[i].name, e); errs.push(fileList[i].name); }
  }
  pb.style.width = '100%';
  pl.textContent = errs.length
    ? `✅ Xong ${ok}/${total}. ❌ Lỗi: ${errs.join(', ')}`
    : `✅ Đã xuất xong ${ok} file PDF!`;

  setTimeout(() => {
    document.getElementById('upload-section').style.display  = 'block';
    document.getElementById('preview-section').style.display = 'none';
    setPreviewMode(false);
    pw.style.display = 'none';
  }, 3000);
}
</script>
</body>
</html>