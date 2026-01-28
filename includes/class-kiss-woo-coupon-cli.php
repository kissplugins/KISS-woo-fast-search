<?php
/**
 * WP-CLI commands for coupon tooling.
 *
 * @package KISS_Woo_Customer_Order_Search
 * @since   1.3.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( defined( 'WP_CLI' ) && WP_CLI ) {

    class KISS_Woo_Coupon_CLI {

        /**
         * Backfill the coupon lookup table.
         *
         * ## OPTIONS
         *
         * [--batch=<number>]
         * : Batch size (default: 500, max: 2000).
         *
         * [--start=<id>]
         * : Start after this coupon ID (default: 0).
         *
         * [--max=<number>]
         * : Max coupons to process before stopping (default: 0 = no limit).
         *
         * ## EXAMPLES
         *
         *     wp kiss-woo coupons backfill --batch=500
         *     wp kiss-woo coupons backfill --start=12000 --batch=1000
         *     wp kiss-woo coupons backfill --max=5000
         *
         * @param array $args       CLI args.
         * @param array $assoc_args CLI assoc args.
         * @return void
         */
        public function backfill( $args, $assoc_args ): void {
            $batch = isset( $assoc_args['batch'] ) ? (int) $assoc_args['batch'] : 500;
            $start = isset( $assoc_args['start'] ) ? (int) $assoc_args['start'] : 0;
            $max   = isset( $assoc_args['max'] ) ? (int) $assoc_args['max'] : 0;

            $lookup = KISS_Woo_Coupon_Lookup::instance();
            $lookup->maybe_install();

            $backfill = new KISS_Woo_Coupon_Backfill();

            $processed_total = 0;
            $last_id = $start;

            while ( true ) {
                $result = $backfill->run_batch( $last_id, $batch );
                $processed_total += (int) $result['processed'];
                $last_id = (int) $result['last_id'];

                \WP_CLI::log( sprintf( 'Processed: %d (last_id=%d)', $processed_total, $last_id ) );

                if ( $max > 0 && $processed_total >= $max ) {
                    \WP_CLI::warning( 'Max limit reached; stopping early.' );
                    break;
                }

                if ( $result['done'] ) {
                    break;
                }
            }

            \WP_CLI::success( sprintf( 'Backfill complete. Total processed: %d (last_id=%d)', $processed_total, $last_id ) );
        }
    }

    \WP_CLI::add_command( 'kiss-woo coupons', 'KISS_Woo_Coupon_CLI' );
}

