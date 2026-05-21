<?php
// App info
define('APP_NAME', 'NYH Cargo');
define('APP_VERSION', '1.0.0');
define('BASE_URL', 'http://localhost/nyh-cargo/');

// NAMYANG default shipper (MAWB)
define('DEFAULT_SHIPPER_NAME',    'NAMYANG GLOBAL CO.,LTD');
define('DEFAULT_SHIPPER_ADDRESS', 'Floor 14th, IDMC My Dinh Building, No 15 Pham Hung Street, Cau Giay Ward, Hanoi, Viet Nam.');
define('DEFAULT_SHIPPER_TEL',     '+84-4-37946116~118');
define('DEFAULT_SHIPPER_FAX',     '+84 (4) 37946119');
define('DEFAULT_SHIPPER_TAX',     '0108168022');

// HAWB format
define('HAWB_PREFIX', 'NYH');
define('HAWB_SUFFIX', 'A');

// Chargeable weight divisor
define('VOLUME_DIVISOR', 6000);

// Roles
define('ROLE_ADMIN',   'admin');
define('ROLE_MANAGER', 'manager');
define('ROLE_STAFF',   'staff');

// Manifest status
define('STATUS_DRAFT',     'draft');
define('STATUS_CONFIRMED', 'confirmed');
define('STATUS_COMPLETED', 'completed');