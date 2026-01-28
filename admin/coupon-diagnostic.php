<?php
/**
 * Coupon Search Diagnostic Page
 * 
 * Access via: /wp-admin/admin.php?page=kiss-woo-coupon-diagnostic
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Add admin menu
add_action( 'admin_menu', function() {
    add_submenu_page(
        null, // No parent = hidden from menu
        'Coupon Search Diagnostic',
        'Coupon Diagnostic',
        'manage_woocommerce',
        'kiss-woo-coupon-diagnostic',
        'kiss_woo_render_coupon_diagnostic'
    );
}, 99 );

function kiss_woo_render_coupon_diagnostic() {
    if ( ! current_user_can( 'manage_woocommerce' ) ) {
        wp_die( 'Unauthorized' );
    }

    // Ensure WooCommerce is available
    if ( ! class_exists( 'WooCommerce' ) ) {
        echo '<div class="wrap"><h1>Coupon Search Diagnostic</h1>';
        echo '<div class="notice notice-error"><p>❌ WooCommerce is not active. Please activate WooCommerce first.</p></div>';
        echo '</div>';
        return;
    }

    // Manually load WooCommerce functions if not available
    if ( ! function_exists( 'wc_get_coupon' ) ) {
        // WooCommerce is active but functions not loaded yet
        // Include the core functions file
        $wc_path = WP_PLUGIN_DIR . '/woocommerce/includes/wc-coupon-functions.php';
        if ( file_exists( $wc_path ) ) {
            include_once $wc_path;
        }

        // If still not available, show error
        if ( ! function_exists( 'wc_get_coupon' ) ) {
            echo '<div class="wrap"><h1>Coupon Search Diagnostic</h1>';
            echo '<div class="notice notice-error"><p>❌ WooCommerce coupon functions are not available. This page must be accessed after WooCommerce is fully loaded.</p></div>';
            echo '<div class="notice notice-info"><p>Try using WP-CLI instead: <code>wp kiss-woo coupons backfill --batch=500</code></p></div>';
            echo '</div>';
            return;
        }
    }

    // Handle backfill action
    if ( isset( $_POST['backfill_coupons'] ) && check_admin_referer( 'kiss_woo_backfill' ) ) {
        $lookup = KISS_Woo_Coupon_Lookup::instance();
        $lookup->maybe_install();

        // Get current count to calculate start position
        global $wpdb;
        $table = $wpdb->prefix . 'kiss_woo_coupon_lookup';
        $max_id = $wpdb->get_var( "SELECT MAX(coupon_id) FROM $table" );
        $start_id = $max_id ? (int) $max_id : 0;

        $backfill = new KISS_Woo_Coupon_Backfill();
        $result = $backfill->run_batch( $start_id, 500 );

        if ( $result['processed'] > 0 ) {
            echo '<div class="notice notice-success"><p>✅ Backfilled ' . $result['processed'] . ' coupons (last_id=' . $result['last_id'] . ')</p></div>';
        } else {
            echo '<div class="notice notice-warning"><p>⚠️ Processed 0 coupons. Last ID checked: ' . $result['last_id'] . '. This might mean all coupons are already indexed or there\'s an issue with the upsert function.</p></div>';
        }
    }

    // Handle single coupon backfill
    if ( isset( $_POST['backfill_single'] ) && check_admin_referer( 'kiss_woo_backfill_single' ) ) {
        $coupon_code = sanitize_text_field( $_POST['coupon_code'] );

        // Get coupon ID by code - use direct database query instead of wc_get_coupon_id_by_code()
        global $wpdb;
        $coupon_id = $wpdb->get_var( $wpdb->prepare(
            "SELECT ID FROM {$wpdb->posts} WHERE post_title = %s AND post_type = 'shop_coupon' AND post_status != 'trash' LIMIT 1",
            $coupon_code
        ) );

        if ( $coupon_id ) {
            $lookup = KISS_Woo_Coupon_Lookup::instance();
            $lookup->maybe_install();

            // Debug: Check if coupon loads
            // Use WC_Coupon class directly instead of wc_get_coupon() helper
            try {
                $coupon = new WC_Coupon( $coupon_id );
                if ( ! $coupon || ! $coupon->get_id() ) {
                    echo '<div class="notice notice-error"><p>❌ Failed to load WC_Coupon object for ID: ' . $coupon_id . '</p></div>';
                    $coupon = null;
                }
            } catch ( Exception $e ) {
                echo '<div class="notice notice-error"><p>❌ Exception loading coupon: ' . esc_html( $e->getMessage() ) . '</p></div>';
                $coupon = null;
            }

            if ( $coupon ) {
                $result = $lookup->upsert_coupon( $coupon_id );

                if ( $result ) {
                    echo '<div class="notice notice-success"><p>✅ Successfully backfilled coupon: ' . $coupon_code . ' (ID: ' . $coupon_id . ')</p></div>';

                    // Verify it's in the table
                    global $wpdb;
                    $table = $wpdb->prefix . 'kiss_woo_coupon_lookup';
                    $row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table WHERE coupon_id = %d", $coupon_id ), ARRAY_A );
                    if ( $row ) {
                        echo '<div class="notice notice-info"><p>✅ Verified in lookup table: code_normalized = ' . esc_html( $row['code_normalized'] ) . '</p></div>';
                    } else {
                        echo '<div class="notice notice-warning"><p>⚠️ upsert returned true but coupon not found in table!</p></div>';
                    }
                } else {
                    echo '<div class="notice notice-error"><p>❌ Failed to backfill coupon: ' . $coupon_code . ' (upsert returned false)</p></div>';

                    // Debug: Check wpdb error
                    global $wpdb;
                    if ( $wpdb->last_error ) {
                        echo '<div class="notice notice-error"><p>Database error: ' . esc_html( $wpdb->last_error ) . '</p></div>';
                    }
                }
            }
        } else {
            echo '<div class="notice notice-error"><p>❌ Coupon not found: ' . $coupon_code . '</p></div>';
        }
    }

    global $wpdb;
    $table = $wpdb->prefix . 'kiss_woo_coupon_lookup';
    $exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );
    $table_exists = ( $exists === $table );
    
    $total_coupons_wp = $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'shop_coupon' AND post_status NOT IN ('trash', 'auto-draft')" );
    $total_in_lookup = $table_exists ? $wpdb->get_var( "SELECT COUNT(*) FROM $table" ) : 0;
    
    ?>
    <div class="wrap">
        <h1>Coupon Search Diagnostic</h1>
        
        <h2>Status</h2>
        <table class="widefat">
            <tr>
                <th>Lookup Table Exists</th>
                <td><?php echo $table_exists ? '✅ Yes' : '❌ No'; ?></td>
            </tr>
            <tr>
                <th>Total Coupons in WordPress</th>
                <td><?php echo number_format( $total_coupons_wp ); ?></td>
            </tr>
            <tr>
                <th>Total Coupons in Lookup Table</th>
                <td><?php echo number_format( $total_in_lookup ); ?></td>
            </tr>
            <tr>
                <th>Missing Coupons</th>
                <td><?php echo number_format( $total_coupons_wp - $total_in_lookup ); ?></td>
            </tr>
        </table>

        <?php if ( $total_coupons_wp > $total_in_lookup ): ?>
        <h2>Backfill Lookup Table</h2>
        <form method="post">
            <?php wp_nonce_field( 'kiss_woo_backfill' ); ?>
            <p>
                <button type="submit" name="backfill_coupons" class="button button-primary">
                    Backfill 500 Coupons
                </button>
            </p>
            <p class="description">This will add the next 500 coupons to the lookup table. Run multiple times if needed.</p>
        </form>
        <?php endif; ?>

        <h2>Test Single Coupon</h2>
        <form method="post">
            <?php wp_nonce_field( 'kiss_woo_backfill_single' ); ?>
            <p>
                <input type="text" name="coupon_code" placeholder="Enter coupon code" value="r1m8jj1xt2m1m" style="width: 300px;">
                <button type="submit" name="backfill_single" class="button">Backfill This Coupon</button>
            </p>
        </form>

        <?php if ( $table_exists ): ?>
        <h2>Recent Coupons in Lookup Table</h2>
        <?php
        $recent = $wpdb->get_results( "SELECT * FROM $table ORDER BY updated_at DESC LIMIT 10", ARRAY_A );
        if ( $recent ) {
            echo '<table class="widefat"><thead><tr><th>ID</th><th>Code</th><th>Code Normalized</th><th>Title</th><th>Status</th><th>Updated</th></tr></thead><tbody>';
            foreach ( $recent as $row ) {
                echo '<tr>';
                echo '<td>' . $row['coupon_id'] . '</td>';
                echo '<td>' . esc_html( $row['code'] ) . '</td>';
                echo '<td>' . esc_html( $row['code_normalized'] ) . '</td>';
                echo '<td>' . esc_html( $row['title'] ) . '</td>';
                echo '<td>' . esc_html( $row['status'] ) . '</td>';
                echo '<td>' . esc_html( $row['updated_at'] ) . '</td>';
                echo '</tr>';
            }
            echo '</tbody></table>';
        }
        ?>
        <?php endif; ?>
    </div>
    <?php
}

