<?php

define('HAWB_TEMPLATE_FILE', __DIR__ . '/../assets/templates/hawb_template.xlsx');

return [

    // ── MAWB / HAWB ──────────────────────────────────────
    'mawb_no'           => 'B1',
    'hawb_no'           => 'Y1',
    'hawb_no_footer'    => 'Y71',
    'no_of_pieces_footer' => 'A71', // TODO: verify exact footer cell on real template
    'gross_weight_footer' => 'B71', // TODO: verify exact footer cell on real template

    // ── SHIPPER ───────────────────────────────────────────
    'shipper_name'      => 'A4',
    'shipper_address'   => 'A5',

    // ── CONSIGNEE ─────────────────────────────────────────
    'consignee_name'    => 'A10',
    'consignee_address' => 'A13',

    // ── ROUTING ───────────────────────────────────────────
    'airport_departure' => 'A25',
    'airport_dest'      => 'A27',
    'issuing_carrier'   => 'B27',  // By first carrier (airline name)

    // ── FLIGHT ────────────────────────────────────────────
    'flight_no'         => 'G30',
    'flight_date'       => 'K30',

    // ── HANDLING ──────────────────────────────────────────
    'notify_party'      => '', // TODO: map when template has dedicated Notify Party cell
    'handling_info'     => 'A33',

    // ── WEIGHT & PIECES ───────────────────────────────────
    'no_of_pieces'      => 'A40',
    'gross_weight'      => 'B40',
    'chargeable_weight' => 'I40',

    // ── COMMODITY ─────────────────────────────────────────
    'commodity_line1'   => 'Y40',

    // ── DIM ───────────────────────────────────────────────
    'dim_info'          => 'Y49',

    // ── DATE BILL ─────────────────────────────────────────
    'execution_date'    => 'N68',

    // ── (các field không dùng trong template này — để trống) ──
    'shipper_city'      => '',
    'shipper_phone'     => '',
    'consignee_city'    => '',
    'consignee_phone'   => '',
    'consignee_acct'    => '',
    'consignee_usci'    => '',
    'agent_name'        => '',
    'agent_iata'        => '',
    'routing_to1'       => '',
    'routing_by1'       => 'C27', // By first carrier (airline code)
    'routing_to2'       => '',
    'routing_by2'       => '',
    'payment_term'      => 'C40', // TODO: verify exact cell on real template
    'currency'          => 'D40', // TODO: verify exact cell on real template
    'rate_class'        => 'E40', // TODO: verify exact cell on real template
    'commodity_item_no' => '',
    'declared_carriage' => 'F40', // TODO: verify exact cell on real template
    'declared_customs'  => 'G40', // TODO: verify exact cell on real template
    'amount_insurance'  => 'H40', // TODO: verify exact cell on real template
    'accounting_info'   => 'A31', // TODO: verify exact cell on real template
    'gross_weight_unit' => '',
    'volume_weight'     => '',
    'commodity_line2'   => '',
    'commodity_line3'   => '',
    'commodity_line4'   => '',
    'commodity_line5'   => '',
    'execution_place'   => '',
    'signature_origin'  => '',

];