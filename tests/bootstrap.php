<?php
/**
 * PHPUnit Bootstrap for KISS Woo Fast Search tests.
 *
 * Uses Brain\Monkey to mock WordPress functions without loading WordPress core.
 *
 * @package KISS_Woo_Fast_Search\Tests
 */

// Composer autoloader.
require_once dirname( __DIR__ ) . '/vendor/autoload.php';

use Brain\Monkey;
use Brain\Monkey\Functions;

/**
 * Base test case with Brain\Monkey setup.
 */
abstract class KISS_Test_Case extends \PHPUnit\Framework\TestCase {

    protected function setUp(): void {
        parent::setUp();
        Monkey\setUp();

        // Mock common WordPress functions.
        Functions\stubs([
            'esc_html'       => function( $text ) { return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' ); },
            'esc_attr'       => function( $text ) { return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' ); },
            'esc_url'        => function( $url ) { return filter_var( $url, FILTER_SANITIZE_URL ); },
            'wp_json_encode' => function( $data ) { return json_encode( $data ); },
            'absint'         => function( $val ) { return abs( (int) $val ); },
            'sanitize_text_field' => function( $str ) { return trim( strip_tags( (string) $str ) ); },
            'wp_list_pluck'  => function( $list, $field ) {
                return array_map( function( $item ) use ( $field ) {
                    return is_object( $item ) ? $item->$field : $item[ $field ];
                }, $list );
            },
            'apply_filters'  => function( $tag, $value ) { return $value; },
            'get_edit_user_link' => function( $user_id ) { return "https://example.com/wp-admin/user-edit.php?user_id={$user_id}"; },
        ]);
    }

    protected function tearDown(): void {
        Monkey\tearDown();
        parent::tearDown();
    }
}

/**
 * Mock KISS_Woo_Debug_Tracer to prevent loading the real class.
 */
if ( ! class_exists( 'KISS_Woo_Debug_Tracer' ) ) {
    class KISS_Woo_Debug_Tracer {
        public static function start_timer( string $component, string $operation ): callable {
            return function( array $extra = [] ) { /* no-op */ };
        }
        public static function log( string $component, string $event, array $context = [], string $level = 'info' ): void {
            // Silent in tests.
        }
    }
}

/**
 * Mock KISS_Woo_Search_Cache for testing.
 */
if ( ! class_exists( 'KISS_Woo_Search_Cache' ) ) {
    class KISS_Woo_Search_Cache {
        private array $store = [];

        public function get_search_key( string $term, string $type ): string {
            return "kiss_{$type}_{$term}";
        }

        public function get( string $key ) {
            return $this->store[ $key ] ?? null;
        }

        public function set( string $key, $value, int $ttl = 3600 ): bool {
            $this->store[ $key ] = $value;
            return true;
        }

        public function delete( string $key ): bool {
            unset( $this->store[ $key ] );
            return true;
        }

        public function clear_all(): bool {
            $this->store = [];
            return true;
        }
    }
}

// Load the actual classes to test.
require_once dirname( __DIR__ ) . '/includes/class-kiss-woo-order-resolver.php';

