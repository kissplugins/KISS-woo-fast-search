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

        $normalized_code = KISS_Woo_Coupon_Lookup::normalize_code( $term );
        if ( '' === $normalized_code ) {
            $normalized_code = '__kiss_none__';
        }

        $normalized_text = KISS_Woo_Coupon_Lookup::normalize_text( $term );
        if ( '' === $normalized_text ) {
            $normalized_text = strtolower( $term );
        }

        // Prepare search term for FULLTEXT search (BOOLEAN MODE).
        // Strip FULLTEXT boolean operators to prevent query manipulation,
        // then add wildcard for prefix matching: "summer*" matches "summer", "summer2024", etc.
        $fulltext_term = preg_replace( '/[+\-~<>()\"@]/', '', $term ) . '*';
        $code_prefix = $wpdb->esc_like( $normalized_code ) . '%';

        $sql = $wpdb->prepare(
            "SELECT coupon_id, code, title, description, amount, discount_type, expiry_date, usage_limit,
                    usage_limit_per_user, usage_count, free_shipping, status, source_flags,
                    CASE
                        WHEN code_normalized = %s THEN 100
                        WHEN code_normalized LIKE %s THEN 90
                        WHEN title = %s THEN 70
                        ELSE 10
                    END AS score
               FROM {$table}
              WHERE blog_id = %d
                AND status NOT IN ('trash', 'auto-draft')
                AND MATCH(code_normalized, title, description_normalized) AGAINST(%s IN BOOLEAN MODE)
           ORDER BY score DESC, updated_at DESC
              LIMIT %d",
            $normalized_code,
            $code_prefix,
            $term,
            $blog_id,
            $fulltext_term,
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

}

