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
         * [--reset]
         * : Reset progress and start from beginning.
         *
         * ## EXAMPLES
         *
         *     wp kiss-woo coupons backfill --batch=500
         *     wp kiss-woo coupons backfill --start=12000 --batch=1000
         *     wp kiss-woo coupons backfill --max=5000
         *     wp kiss-woo coupons backfill --reset
         *
         * @param array $args       CLI args.
         * @param array $assoc_args CLI assoc args.
         * @return void
         */
        public function backfill( $args, $assoc_args ): void {
            $batch = isset( $assoc_args['batch'] ) ? (int) $assoc_args['batch'] : 500;
            $start = isset( $assoc_args['start'] ) ? (int) $assoc_args['start'] : 0;
            $max   = isset( $assoc_args['max'] ) ? (int) $assoc_args['max'] : 0;
            $reset = isset( $assoc_args['reset'] );

            // Use shared builder class.
            $builder = new KISS_Woo_Coupon_Lookup_Builder();

            // Reset if requested.
            if ( $reset ) {
                $builder->reset_progress();
                \WP_CLI::log( 'Progress reset.' );
            }

            // Ensure table exists.
            $lookup = KISS_Woo_Coupon_Lookup::instance();
            $lookup->maybe_install();

            // If start ID specified, update progress manually.
            if ( $start > 0 ) {
                $progress = $builder->get_progress();
                $progress['last_id'] = $start;
                $builder->reset_progress();
                update_option( 'kiss_woo_coupon_build_progress', $progress, false );
                \WP_CLI::log( sprintf( 'Starting from coupon ID: %d', $start ) );
            }

            $processed_total = 0;

            while ( true ) {
                // Run batch with force=true to bypass rate limiting.
                $result = $builder->run_batch( $batch, true );

                if ( ! $result['success'] ) {
                    \WP_CLI::error( $result['message'] );
                    break;
                }

                $processed_total += (int) $result['processed'];

                \WP_CLI::log( $result['message'] );

                if ( $max > 0 && $processed_total >= $max ) {
                    \WP_CLI::warning( 'Max limit reached; stopping early.' );
                    break;
                }

                if ( $result['done'] ) {
                    break;
                }
            }

            \WP_CLI::success( sprintf( 'Backfill complete. Total processed: %d', $processed_total ) );
        }
    }

    \WP_CLI::add_command( 'kiss-woo coupons', 'KISS_Woo_Coupon_CLI' );
}

