<?php
// ── APP ──────────────────────────────────────────────────
define('APP_NAME',    'NYH Cargo');
define('APP_VERSION', '1.0');
define('BASE_URL',    'http://localhost/nyh-cargo/');

// ── ROLES ────────────────────────────────────────────────
define('ROLE_ADMIN',   'admin');
define('ROLE_MANAGER', 'manager');
define('ROLE_STAFF',   'staff');

// ── HAWB NUMBER FORMAT ───────────────────────────────────
define('HAWB_PREFIX', 'NYH');   // NYH + yy + mm + seq + suffix
define('HAWB_SUFFIX', 'A');     // ví dụ: NYH2605001A

// ── COMPANY INFO (dùng trong print/export) ───────────────
define('COMPANY_NAME',       'NAMYANG GLOBAL CO.,LTD');
define('COMPANY_SHORT_NAME', 'NYH CARGO');
define('COMPANY_ADDRESS',    'Floor 14th, IDMC My Dinh Building, No 15 Pham Hung Street, Cau Giay Ward, Hanoi, Viet Nam');
define('COMPANY_TEL',        '+84-4-37946116~118');
define('COMPANY_FAX',        '+84 (4) 37946119');
define('COMPANY_TAX',        '0108168022');
define('COMPANY_IATA',       '');   // IATA Agent Code nếu có

// ── DEFAULT SHIPPER ON MAWB (Fixed) ──────────────────────
define('DEFAULT_SHIPPER_NAME',    'NAMYANG GLOBAL CO.,LTD');
define('DEFAULT_SHIPPER_ADDRESS', 'Floor 14th, IDMC My Dinh Building, No 15 Pham Hung Street, Cau Giay Ward, Hanoi, Viet Nam');
define('DEFAULT_SHIPPER_TEL',     '+84-4-37946116~118');
define('DEFAULT_SHIPPER_FAX',     '+84 (4) 37946119');
define('DEFAULT_SHIPPER_TAX',     '0108168022');