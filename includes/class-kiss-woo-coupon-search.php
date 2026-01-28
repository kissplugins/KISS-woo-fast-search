<?php
/**
 * Coupon search service (lookup-table backed).
 *
 * SINGLE WRITE PATH: All coupon searches go through this class.
 *
 * @package KISS_Woo_Customer_Order_Search
 * @since   1.3.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class KISS_Woo_Coupon_Search {

    /**
     * Search coupons by term using the lookup table.
     *
     * @param string $term Search term.
     * @param int    $limit Max results.
     * @return array
     */
    public function search_coupons( string $term, int $limit = 20 ): array {
        $term = trim( $term );

        if ( '' === $term ) {
            return array();
        }

        if ( $limit < 1 ) {
            $limit = 1;
        }
        if ( $limit > 100 ) {
            $limit = 100;
        }

        $lookup = KISS_Woo_Coupon_Lookup::instance();
        if ( ! $lookup->is_table_ready() ) {
            return array();
        }

        $cache = new KISS_Woo_Search_Cache();
        $cache_key = $cache->get_search_key( $term, 'coupon' );
        $cached = $cache->get( $cache_key );
        if ( null !== $cached ) {
            return $cached;
        }

        global $wpdb;

        $table = $lookup->get_table_name();
        $blog_id = (int) get_current_blog_id();

        $normalized_code = $this->normalize_code( $term );
        if ( '' === $normalized_code ) {
            $normalized_code = '__kiss_none__';
        }

        $normalized_text = $this->normalize_text( $term );
        if ( '' === $normalized_text ) {
            $normalized_text = strtolower( $term );
        }

        $term_like = '%' . $wpdb->esc_like( $term ) . '%';
        $term_prefix = $wpdb->esc_like( $term ) . '%';
        $code_prefix = $wpdb->esc_like( $normalized_code ) . '%';
        $desc_like = '%' . $wpdb->esc_like( $normalized_text ) . '%';

        $sql = $wpdb->prepare(
            "SELECT coupon_id, code, title, description, amount, discount_type, expiry_date, usage_limit,
                    usage_limit_per_user, usage_count, free_shipping, status, source_flags,
                    CASE
                        WHEN code_normalized = %s THEN 100
                        WHEN code_normalized LIKE %s THEN 90
                        WHEN title = %s THEN 70
                        WHEN title LIKE %s THEN 60
                        WHEN description_normalized LIKE %s THEN 40
                        ELSE 10
                    END AS score
               FROM {$table}
              WHERE blog_id = %d
                AND status NOT IN ('trash', 'auto-draft')
                AND (
                    code_normalized LIKE %s
                    OR title LIKE %s
                    OR description_normalized LIKE %s
                )
           ORDER BY score DESC, updated_at DESC
              LIMIT %d",
            $normalized_code,
            $code_prefix,
            $term,
            $term_prefix,
            $desc_like,
            $blog_id,
            $code_prefix,
            $term_like,
            $desc_like,
            $limit
        );

        $rows = $wpdb->get_results( $sql, ARRAY_A );

        // Lazy backfill: If no results from lookup table, try fallback query
        if ( empty( $rows ) ) {
            $fallback_results = $this->fallback_search( $term, $limit );

            if ( ! empty( $fallback_results ) ) {
                // Found results via fallback - backfill them asynchronously
                $this->lazy_backfill_coupons( $fallback_results );

                $cache->set( $cache_key, $fallback_results, 60 );
                return $fallback_results;
            }

            $cache->set( $cache_key, array(), 60 );
            return array();
        }

        $results = array();
        foreach ( $rows as $row ) {
            $results[] = KISS_Woo_Coupon_Formatter::format_from_row( $row );
        }

        $cache->set( $cache_key, $results, 60 );

        return $results;
    }

    /**
     * Fallback search: Query wp_posts directly when lookup table has no results.
     * This handles cases where coupons haven't been backfilled yet.
     *
     * @param string $term Search term.
     * @param int    $limit Max results.
     * @return array
     */
    private function fallback_search( string $term, int $limit ): array {
        global $wpdb;

        $term_like = '%' . $wpdb->esc_like( $term ) . '%';

        // Search wp_posts for shop_coupon post type
        $sql = $wpdb->prepare(
            "SELECT ID
               FROM {$wpdb->posts}
              WHERE post_type = 'shop_coupon'
                AND post_status NOT IN ('trash', 'auto-draft')
                AND post_title LIKE %s
           ORDER BY post_title ASC
              LIMIT %d",
            $term_like,
            $limit
        );

        $coupon_ids = $wpdb->get_col( $sql );

        if ( empty( $coupon_ids ) ) {
            return array();
        }

        // Load WC_Coupon objects and format them
        $results = array();
        foreach ( $coupon_ids as $coupon_id ) {
            if ( ! class_exists( 'WC_Coupon' ) ) {
                break;
            }

            try {
                $coupon = new WC_Coupon( $coupon_id );
                if ( $coupon && $coupon->get_id() ) {
                    $results[] = KISS_Woo_Coupon_Formatter::format_from_coupon( $coupon );
                }
            } catch ( Exception $e ) {
                // Skip invalid coupons
                continue;
            }
        }

        return $results;
    }

    /**
     * Lazy backfill: Index coupons found via fallback search.
     * This gradually populates the lookup table as users search.
     *
     * @param array $coupon_results Coupon results from fallback search.
     * @return void
     */
    private function lazy_backfill_coupons( array $coupon_results ): void {
        if ( empty( $coupon_results ) ) {
            return;
        }

        $lookup = KISS_Woo_Coupon_Lookup::instance();

        // Backfill in background (don't block the search response)
        // Only backfill up to 10 coupons per search to avoid performance impact
        $count = 0;
        foreach ( $coupon_results as $result ) {
            if ( $count >= 10 ) {
                break;
            }

            if ( isset( $result['id'] ) ) {
                $lookup->upsert_coupon( (int) $result['id'] );
                $count++;
            }
        }
    }

    /**
     * Normalize coupon code for indexed search.
     *
     * @param string $code Coupon code.
     * @return string
     */
    private function normalize_code( string $code ): string {
        $code = strtolower( trim( $code ) );
        $code = preg_replace( '/[^a-z0-9]+/', '', $code );

        return $code;
    }

    /**
     * Normalize general text for indexed search.
     *
     * @param string $text Raw text.
     * @return string
     */
    private function normalize_text( string $text ): string {
        $text = wp_strip_all_tags( $text );
        $text = strtolower( trim( $text ) );
        $text = preg_replace( '/\\s+/', ' ', $text );

        return $text;
    }
}

