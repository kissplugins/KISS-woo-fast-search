<?php
/**
 * Coupon lookup backfill utilities.
 *
 * SINGLE WRITE PATH: Backfill writes go through this class.
 *
 * @package KISS_Woo_Customer_Order_Search
 * @since   1.3.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class KISS_Woo_Coupon_Backfill {

    /**
     * Run a single backfill batch.
     *
     * @param int $last_id Last processed post ID.
     * @param int $limit   Batch size.
     * @return array{processed:int,last_id:int,done:bool}
     */
    public function run_batch( int $last_id = 0, int $limit = 500 ): array {
        global $wpdb;

        $limit = max( 1, min( 2000, $limit ) );

        $ids = $wpdb->get_col(
            $wpdb->prepare(
                "SELECT ID
                   FROM {$wpdb->posts}
                  WHERE post_type = 'shop_coupon'
                    AND post_status NOT IN ('trash', 'auto-draft')
                    AND ID > %d
               ORDER BY ID ASC
                  LIMIT %d",
                $last_id,
                $limit
            )
        );

        if ( empty( $ids ) ) {
            return array(
                'processed' => 0,
                'last_id'   => $last_id,
                'done'      => true,
            );
        }

        $lookup = KISS_Woo_Coupon_Lookup::instance();
        $processed = 0;
        $current_last = $last_id;

        foreach ( $ids as $id ) {
            $id = (int) $id;
            if ( $lookup->upsert_coupon( $id ) ) {
                $processed++;
            }
            $current_last = $id;
        }

        return array(
            'processed' => $processed,
            'last_id'   => $current_last,
            'done'      => count( $ids ) < $limit,
        );
    }
}

