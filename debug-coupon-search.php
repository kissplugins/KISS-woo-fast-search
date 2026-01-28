<?php
/**
 * Debug script to check coupon search functionality.
 */

define('WP_USE_THEMES', false);
require('/Users/noelsaw/Local Sites/bloomz-prod-08-15/app/public/wp-load.php');

// Check if coupon exists
$coupon_code = 'r1m8jj1xt2m1m';
echo "Searching for coupon: $coupon_code\n";
echo "=====================================\n\n";

// Try to get coupon by code
$coupon_id = wc_get_coupon_id_by_code($coupon_code);
echo "Coupon ID from wc_get_coupon_id_by_code(): $coupon_id\n";

if ($coupon_id) {
    $coupon = new WC_Coupon($coupon_id);
    echo "Coupon Code: " . $coupon->get_code() . "\n";
    echo "Coupon ID: " . $coupon->get_id() . "\n";
    echo "Status: " . get_post_status($coupon_id) . "\n";
    echo "Edit URL: https://bloomz-prod-08-15.local/wp-admin/post.php?post={$coupon_id}&action=edit&classic-editor\n\n";
}

// Check lookup table
global $wpdb;
$table = $wpdb->prefix . 'kiss_woo_coupon_lookup';
$exists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table));

if ($exists === $table) {
    echo "Lookup table exists: $table\n";
    $count = $wpdb->get_var("SELECT COUNT(*) FROM $table");
    echo "Total coupons in lookup table: $count\n\n";
    
    // Search for this specific coupon
    $normalized = strtolower(preg_replace('/[^a-z0-9]+/', '', $coupon_code));
    echo "Normalized code: $normalized\n";
    
    $row = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM $table WHERE code_normalized LIKE %s LIMIT 1",
        '%' . $wpdb->esc_like($normalized) . '%'
    ), ARRAY_A);
    
    if ($row) {
        echo "Found in lookup table:\n";
        print_r($row);
    } else {
        echo "NOT FOUND in lookup table!\n";
        echo "Checking if coupon ID $coupon_id is in table...\n";
        $by_id = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table WHERE coupon_id = %d",
            $coupon_id
        ), ARRAY_A);
        if ($by_id) {
            echo "Found by ID:\n";
            print_r($by_id);
        } else {
            echo "Coupon ID $coupon_id is NOT in the lookup table - needs backfill!\n\n";
            
            // Try to backfill this one coupon
            echo "Attempting to backfill this coupon...\n";
            $lookup = KISS_Woo_Coupon_Lookup::instance();
            $lookup->maybe_install();
            $result = $lookup->upsert_coupon($coupon_id);
            if ($result) {
                echo "✅ Successfully backfilled coupon $coupon_id\n";
                
                // Verify it's now in the table
                $verify = $wpdb->get_row($wpdb->prepare(
                    "SELECT * FROM $table WHERE coupon_id = %d",
                    $coupon_id
                ), ARRAY_A);
                if ($verify) {
                    echo "✅ Verified in lookup table:\n";
                    print_r($verify);
                }
            } else {
                echo "❌ Failed to backfill coupon $coupon_id\n";
            }
        }
    }
} else {
    echo "Lookup table does NOT exist: $table\n";
    echo "Creating table...\n";
    $lookup = KISS_Woo_Coupon_Lookup::instance();
    $lookup->maybe_install();
    echo "Table created. Run this script again to backfill.\n";
}

