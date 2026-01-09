<?php
/**
 * Shared utilities (helpers that don't belong to a specific domain object).
 *
 * @package KISS_Woo_Customer_Order_Search
 * @since   1.1.6
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class KISS_Woo_Utils {

    /**
     * Detect whether WooCommerce HPOS (High-Performance Order Storage) is enabled.
     *
     * Centralized to avoid repeating the same try/catch + class_exists logic.
     *
     * @return bool
     */
    public static function is_hpos_enabled(): bool {
        try {
            return class_exists( 'Automattic\\WooCommerce\\Utilities\\OrderUtil' )
                && method_exists( 'Automattic\\WooCommerce\\Utilities\\OrderUtil', 'custom_orders_table_usage_is_enabled' )
                && \Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled();
        } catch ( \Throwable $e ) {
            return false;
        }
    }
}
