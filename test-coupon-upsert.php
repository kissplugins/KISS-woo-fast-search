<?php
/**
 * Test coupon upsert functionality
 */

$lookup = KISS_Woo_Coupon_Lookup::instance();
$coupon_id = 1323821;
echo "Testing coupon ID: $coupon_id\n";

// Check if WC function exists
if (!function_exists('wc_get_coupon')) {
    echo "ERROR: wc_get_coupon function not found!\n";
    exit;
}

// Try to get the coupon
$coupon = wc_get_coupon($coupon_id);
if (!$coupon || !is_a($coupon, 'WC_Coupon')) {
    echo "ERROR: Could not load WC_Coupon object\n";
    echo "Coupon object type: " . gettype($coupon) . "\n";
    if (is_object($coupon)) {
        echo "Coupon class: " . get_class($coupon) . "\n";
    }
    exit;
}

echo "✓ Coupon loaded successfully\n";
echo "Code: " . $coupon->get_code() . "\n";
echo "ID: " . $coupon->get_id() . "\n";

// Try to upsert
$result = $lookup->upsert_coupon($coupon_id);
echo "Upsert result: " . ($result ? "SUCCESS" : "FAILED") . "\n";

// Check if it's in the table now
global $wpdb;
$table = $wpdb->prefix . 'kiss_woo_coupon_lookup';
$row = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE coupon_id = %d", $coupon_id), ARRAY_A);
if ($row) {
    echo "✓ Found in lookup table:\n";
    echo "  Code: " . $row['code'] . "\n";
    echo "  Code Normalized: " . $row['code_normalized'] . "\n";
} else {
    echo "ERROR: Not found in lookup table after upsert\n";
}

