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
     * Fallback search: Query wp_posts + wp_postmeta directly when lookup table has no results.
     * This handles cases where coupons haven't been backfilled yet.
     *
     * Uses 2 batch queries (posts + postmeta) instead of N+1 WC_Coupon loads,
     * then feeds rows through the existing format_from_row() formatter.
     *
     * @since 1.2.15
     * @param string $term Search term.
     * @param int    $limit Max results.
     * @return array
     */
    private function fallback_search( string $term, int $limit ): array {
        global $wpdb;

        $term_like = '%' . $wpdb->esc_like( $term ) . '%';

        // Query 1: Fetch matching coupon posts with core fields in a single query.
        $sql = $wpdb->prepare(
            "SELECT ID, post_title, post_excerpt, post_status
               FROM {$wpdb->posts}
              WHERE post_type = 'shop_coupon'
                AND post_status NOT IN ('trash', 'auto-draft')
                AND post_title LIKE %s
           ORDER BY post_title ASC
              LIMIT %d",
            $term_like,
            $limit
        );

        $posts = $wpdb->get_results( $sql );

        if ( empty( $posts ) ) {
            return array();
        }

        // Build ID list for batch meta query.
        $coupon_ids   = wp_list_pluck( $posts, 'ID' );
        $id_count     = count( $coupon_ids );
        $placeholders = implode( ',', array_fill( 0, $id_count, '%d' ) );

        // Query 2: Batch-fetch all needed postmeta in one query.
        $meta_keys = array(
            'discount_type',
            'coupon_amount',
            'date_expires',
            'usage_limit',
            'usage_limit_per_user',
            'usage_count',
            'free_shipping',
        );
        $key_count       = count( $meta_keys );
        $key_placeholders = implode( ',', array_fill( 0, $key_count, '%s' ) );

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- placeholders are safe.
        $meta_sql = $wpdb->prepare(
            "SELECT post_id, meta_key, meta_value
               FROM {$wpdb->postmeta}
              WHERE post_id IN ({$placeholders})
                AND meta_key IN ({$key_placeholders})",
            array_merge( $coupon_ids, $meta_keys )
        );

        $meta_rows = $wpdb->get_results( $meta_sql );

        // Index meta by post_id for O(1) lookups.
        $meta_map = array();
        if ( ! empty( $meta_rows ) ) {
            foreach ( $meta_rows as $m ) {
                $meta_map[ (int) $m->post_id ][ $m->meta_key ] = $m->meta_value;
            }
        }

        // Build rows matching format_from_row() contract, then format.
        $results = array();
        foreach ( $posts as $post ) {
            $pid  = (int) $post->ID;
            $meta = isset( $meta_map[ $pid ] ) ? $meta_map[ $pid ] : array();

            // Convert date_expires timestamp to datetime string.
            $expiry_date = '';
            if ( ! empty( $meta['date_expires'] ) ) {
                $expiry_date = gmdate( 'Y-m-d H:i:s', (int) $meta['date_expires'] );
            }

            $row = array(
                'coupon_id'          => $pid,
                'code'               => $post->post_title,
                'title'              => $post->post_title,
                'description'        => $post->post_excerpt,
                'discount_type'      => isset( $meta['discount_type'] ) ? $meta['discount_type'] : '',
                'amount'             => isset( $meta['coupon_amount'] ) ? (float) $meta['coupon_amount'] : 0.0,
                'expiry_date'        => $expiry_date,
                'usage_limit'        => isset( $meta['usage_limit'] ) ? (int) $meta['usage_limit'] : 0,
                'usage_limit_per_user' => isset( $meta['usage_limit_per_user'] ) ? (int) $meta['usage_limit_per_user'] : 0,
                'usage_count'        => isset( $meta['usage_count'] ) ? (int) $meta['usage_count'] : 0,
                'free_shipping'      => ! empty( $meta['free_shipping'] ) && 'yes' === $meta['free_shipping'] ? 1 : 0,
                'status'             => $post->post_status,
                'source_flags'       => 'fallback',
            );

            $results[] = KISS_Woo_Coupon_Formatter::format_from_row( $row );
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

