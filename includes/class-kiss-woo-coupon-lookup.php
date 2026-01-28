<?php
/**
 * Coupon lookup table and indexing.
 *
 * SINGLE WRITE PATH: All coupon lookup writes go through this class.
 *
 * @package KISS_Woo_Customer_Order_Search
 * @since   1.3.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class KISS_Woo_Coupon_Lookup {

    /**
     * Schema version for the coupon lookup table.
     *
     * @var string
     */
    private const DB_VERSION = '1.0';

    /**
     * Option name that stores the schema version.
     *
     * @var string
     */
    private const DB_OPTION = 'kiss_woo_coupon_lookup_db_version';

    /**
     * Singleton instance.
     *
     * @var KISS_Woo_Coupon_Lookup|null
     */
    protected static $instance = null;

    /**
     * Track whether the table exists to avoid redundant checks.
     *
     * @var bool
     */
    private bool $table_ready = false;

    /**
     * Get singleton instance.
     *
     * @return KISS_Woo_Coupon_Lookup
     */
    public static function instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    /**
     * Constructor.
     */
    private function __construct() {
        add_action( 'admin_init', array( $this, 'maybe_install' ) );
        add_action( 'save_post_shop_coupon', array( $this, 'handle_coupon_save' ), 10, 3 );
        add_action( 'before_delete_post', array( $this, 'handle_coupon_delete' ) );
        add_action( 'trashed_post', array( $this, 'handle_coupon_delete' ) );
        add_action( 'untrashed_post', array( $this, 'handle_coupon_untrash' ) );
    }

    /**
     * Get the lookup table name.
     *
     * @return string
     */
    public function get_table_name(): string {
        global $wpdb;

        return $wpdb->prefix . 'kiss_woo_coupon_lookup';
    }

    /**
     * Ensure the lookup table exists and is up to date.
     *
     * @return void
     */
    public function maybe_install(): void {
        if ( $this->table_ready ) {
            return;
        }

        $installed_version = get_option( self::DB_OPTION, '' );
        if ( self::DB_VERSION === $installed_version && $this->table_exists() ) {
            $this->table_ready = true;
            return;
        }

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        dbDelta( $this->get_schema_sql() );
        update_option( self::DB_OPTION, self::DB_VERSION );
        $this->table_ready = true;
    }

    /**
     * Handle coupon save/update.
     *
     * @param int     $post_id Post ID.
     * @param WP_Post $post    Post object.
     * @param bool    $update  Whether this is an update.
     * @return void
     */
    public function handle_coupon_save( $post_id, $post, $update ): void {
        if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
            return;
        }

        if ( empty( $post ) || 'shop_coupon' !== $post->post_type ) {
            return;
        }

        if ( 'trash' === $post->post_status || 'auto-draft' === $post->post_status ) {
            $this->delete_coupon( (int) $post_id );
            return;
        }

        $this->upsert_coupon( (int) $post_id );
    }

    /**
     * Handle coupon deletion or trashing.
     *
     * @param int $post_id Post ID.
     * @return void
     */
    public function handle_coupon_delete( $post_id ): void {
        $post = get_post( $post_id );
        if ( ! $post || 'shop_coupon' !== $post->post_type ) {
            return;
        }

        $this->delete_coupon( (int) $post_id );
    }

    /**
     * Handle coupon untrash.
     *
     * @param int $post_id Post ID.
     * @return void
     */
    public function handle_coupon_untrash( $post_id ): void {
        $post = get_post( $post_id );
        if ( ! $post || 'shop_coupon' !== $post->post_type ) {
            return;
        }

        $this->upsert_coupon( (int) $post_id );
    }

    /**
     * Upsert a coupon into the lookup table.
     *
     * @param int $coupon_id Coupon ID.
     * @return bool
     */
    public function upsert_coupon( int $coupon_id ): bool {
        $debug = defined( 'WP_CLI' ) && WP_CLI;

        if ( $coupon_id <= 0 ) {
            if ( $debug ) {
                WP_CLI::debug( "upsert_coupon($coupon_id): invalid coupon_id" );
            }
            return false;
        }

        if ( ! $this->ensure_table_ready() ) {
            if ( $debug ) {
                WP_CLI::debug( "upsert_coupon($coupon_id): table not ready" );
            }
            return false;
        }

        // Use WC_Coupon class directly instead of wc_get_coupon() helper function
        // The helper function may not be loaded in all contexts (e.g., WP-CLI)
        if ( ! class_exists( 'WC_Coupon' ) ) {
            if ( $debug ) {
                WP_CLI::debug( "upsert_coupon($coupon_id): WC_Coupon class not found" );
            }
            return false;
        }

        try {
            $coupon = new WC_Coupon( $coupon_id );
        } catch ( Exception $e ) {
            if ( $debug ) {
                WP_CLI::debug( "upsert_coupon($coupon_id): Exception creating WC_Coupon: " . $e->getMessage() );
            }
            return false;
        }

        if ( ! $coupon || ! $coupon->get_id() ) {
            if ( $debug ) {
                WP_CLI::debug( "upsert_coupon($coupon_id): failed to load WC_Coupon object or invalid ID" );
            }
            return false;
        }

        $row = $this->build_row_from_coupon( $coupon );
        if ( empty( $row ) ) {
            if ( $debug ) {
                WP_CLI::debug( "upsert_coupon($coupon_id): build_row_from_coupon returned empty" );
            }
            return false;
        }

        global $wpdb;

        $format = array(
            '%d', // coupon_id
            '%d', // blog_id
            '%s', // code
            '%s', // code_normalized
            '%s', // title
            '%s', // description
            '%s', // description_normalized
            '%f', // amount
            '%s', // discount_type
            '%s', // expiry_date
            '%d', // usage_limit
            '%d', // usage_limit_per_user
            '%d', // usage_count
            '%d', // free_shipping
            '%s', // status
            '%s', // source_flags
            '%s', // updated_at
        );

        $result = $wpdb->replace( $this->get_table_name(), $row, $format );

        if ( $debug ) {
            if ( false === $result ) {
                WP_CLI::debug( "upsert_coupon($coupon_id): wpdb->replace failed. Error: " . $wpdb->last_error );
            } else {
                WP_CLI::debug( "upsert_coupon($coupon_id): SUCCESS" );
            }
        }

        return false !== $result;
    }

    /**
     * Delete a coupon from the lookup table.
     *
     * @param int $coupon_id Coupon ID.
     * @return bool
     */
    public function delete_coupon( int $coupon_id ): bool {
        if ( $coupon_id <= 0 ) {
            return false;
        }

        if ( ! $this->ensure_table_ready() ) {
            return false;
        }

        global $wpdb;

        $result = $wpdb->delete(
            $this->get_table_name(),
            array(
                'coupon_id' => $coupon_id,
            ),
            array( '%d' )
        );

        return false !== $result;
    }

    /**
     * Build a lookup row from a WooCommerce coupon object.
     *
     * @param WC_Coupon $coupon Coupon object.
     * @return array
     */
    private function build_row_from_coupon( WC_Coupon $coupon ): array {
        $coupon_id = (int) $coupon->get_id();
        $code      = (string) $coupon->get_code();
        $title     = (string) get_the_title( $coupon_id );
        $desc      = (string) $coupon->get_description();
        $expires   = $coupon->get_date_expires();
        $status    = (string) get_post_status( $coupon_id );

        $row = array(
            'coupon_id'             => $coupon_id,
            'blog_id'               => (int) get_current_blog_id(),
            'code'                  => $code,
            'code_normalized'       => $this->normalize_code( $code ),
            'title'                 => $title,
            'description'           => $desc,
            'description_normalized'=> $this->normalize_text( $desc ),
            'amount'                => (float) $coupon->get_amount(),
            'discount_type'         => (string) $coupon->get_discount_type(),
            'expiry_date'           => $expires ? $expires->date( 'Y-m-d H:i:s' ) : null,
            'usage_limit'           => $coupon->get_usage_limit(),
            'usage_limit_per_user'  => $coupon->get_usage_limit_per_user(),
            'usage_count'           => $coupon->get_usage_count(),
            'free_shipping'         => $coupon->get_free_shipping() ? 1 : 0,
            'status'                => $status ? $status : 'publish',
            'source_flags'          => implode( ',', $this->get_source_flags( $coupon ) ),
            'updated_at'            => current_time( 'mysql', true ),
        );

        /**
         * Allow other code to modify the lookup row before save.
         *
         * @param array     $row    Lookup row.
         * @param WC_Coupon $coupon Coupon object.
         */
        return apply_filters( 'kiss_woo_coupon_lookup_row', $row, $coupon );
    }

    /**
     * Determine source flags for a coupon.
     *
     * @param WC_Coupon $coupon Coupon object.
     * @return array
     */
    private function get_source_flags( WC_Coupon $coupon ): array {
        $flags = array( 'core' );

        $meta_map = apply_filters(
            'kiss_woo_coupon_source_meta_keys',
            array(
                'smart'    => array(),
                'advanced' => array(),
            )
        );

        foreach ( $meta_map as $flag => $keys ) {
            foreach ( (array) $keys as $key ) {
                if ( '' !== $coupon->get_meta( $key, true ) ) {
                    $flags[] = (string) $flag;
                    break;
                }
            }
        }

        /**
         * Allow other code to adjust source flags.
         *
         * @param array     $flags  Source flags.
         * @param WC_Coupon $coupon Coupon object.
         */
        $flags = apply_filters( 'kiss_woo_coupon_source_flags', $flags, $coupon );

        return array_values( array_unique( $flags ) );
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

    /**
     * Check if the lookup table exists.
     *
     * @return bool
     */
    private function table_exists(): bool {
        global $wpdb;

        $table  = $this->get_table_name();
        $exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );

        return $exists === $table;
    }

    /**
     * Ensure the lookup table is ready.
     *
     * @return bool
     */
    private function ensure_table_ready(): bool {
        if ( $this->table_ready ) {
            return true;
        }

        if ( $this->table_exists() ) {
            $this->table_ready = true;
            return true;
        }

        $this->maybe_install();

        return $this->table_exists();
    }

    /**
     * Public check for table readiness.
     *
     * @return bool
     */
    public function is_table_ready(): bool {
        return $this->ensure_table_ready();
    }

    /**
     * Get the SQL schema for the lookup table.
     *
     * @return string
     */
    private function get_schema_sql(): string {
        global $wpdb;

        $table_name      = $this->get_table_name();
        $charset_collate = $wpdb->get_charset_collate();

        return "CREATE TABLE {$table_name} (
            coupon_id BIGINT UNSIGNED NOT NULL,
            blog_id BIGINT UNSIGNED NOT NULL DEFAULT 1,
            code VARCHAR(200) NOT NULL DEFAULT '',
            code_normalized VARCHAR(200) NOT NULL DEFAULT '',
            title VARCHAR(200) NOT NULL DEFAULT '',
            description TEXT NULL,
            description_normalized TEXT NULL,
            amount DECIMAL(19,4) NULL,
            discount_type VARCHAR(50) NULL,
            expiry_date DATETIME NULL,
            usage_limit INT NULL,
            usage_limit_per_user INT NULL,
            usage_count INT NULL,
            free_shipping TINYINT(1) NOT NULL DEFAULT 0,
            status VARCHAR(20) NOT NULL DEFAULT 'publish',
            source_flags VARCHAR(100) NOT NULL DEFAULT 'core',
            updated_at DATETIME NOT NULL,
            PRIMARY KEY  (coupon_id),
            KEY idx_code_normalized (code_normalized),
            KEY idx_title (title),
            KEY idx_expiry (expiry_date),
            KEY idx_blog_id (blog_id)
        ) {$charset_collate};";
    }
}
