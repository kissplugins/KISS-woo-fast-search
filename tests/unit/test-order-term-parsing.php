<?php
/**
 * Unit tests for order term parsing
 *
 * Run with: wp eval-file tests/unit/test-order-term-parsing.php
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

require_once KISS_WOO_COS_PATH . 'includes/class-kiss-woo-search.php';

class KISS_Order_Term_Parsing_Test {

    private $search;
    private $passed = 0;
    private $failed = 0;

    public function __construct() {
        $this->search = new KISS_Woo_COS_Search();
    }

    private function assert_equals( $expected, $actual, $message ) {
        if ( $expected === $actual ) {
            $this->passed++;
            echo "✓ PASS: {$message}\n";
        } else {
            $this->failed++;
            echo "✗ FAIL: {$message}\n";
            echo "  Expected: " . var_export( $expected, true ) . "\n";
            echo "  Actual:   " . var_export( $actual, true ) . "\n";
        }
    }

    private function assert_true( $actual, $message ) {
        $this->assert_equals( true, $actual, $message );
    }

    private function assert_false( $actual, $message ) {
        $this->assert_equals( false, $actual, $message );
    }

    public function run_tests() {
        echo "\n=== KISS Order Term Parsing Tests ===\n\n";

        // Test is_order_like_term()
        $this->assert_true(
            $this->search->is_order_like_term( '12345' ),
            'Numeric order ID should be order-like'
        );

        $this->assert_true(
            $this->search->is_order_like_term( '#12345' ),
            'Order ID with # prefix should be order-like'
        );

        $this->assert_true(
            $this->search->is_order_like_term( 'B349445' ),
            'B-prefix order number should be order-like'
        );

        $this->assert_true(
            $this->search->is_order_like_term( 'b349445' ),
            'Lowercase b-prefix should be order-like (case-insensitive)'
        );

        $this->assert_true(
            $this->search->is_order_like_term( '#B349445' ),
            'B-prefix with # should be order-like'
        );

        $this->assert_true(
            $this->search->is_order_like_term( 'D349445' ),
            'D-prefix order number should be order-like'
        );

        $this->assert_true(
            $this->search->is_order_like_term( '#D349445' ),
            'D-prefix with # should be order-like'
        );

        $this->assert_false(
            $this->search->is_order_like_term( 'john smith' ),
            'Name should NOT be order-like'
        );

        $this->assert_false(
            $this->search->is_order_like_term( 'test@example.com' ),
            'Email should NOT be order-like'
        );

        $this->assert_true(
            $this->search->is_order_like_term( 'B349' ),
            'B-prefix with digits should be order-like (B349 is valid)'
        );

        $this->assert_false(
            $this->search->is_order_like_term( 'B' ),
            'B-prefix without digits should NOT be order-like'
        );

        $this->assert_false(
            $this->search->is_order_like_term( '#B' ),
            '#B without digits should NOT be order-like'
        );

        $this->assert_false(
            $this->search->is_order_like_term( 'BABC123' ),
            'B-prefix with non-numeric chars should NOT be order-like'
        );

        $this->assert_false(
            $this->search->is_order_like_term( '' ),
            'Empty string should NOT be order-like'
        );

        $this->assert_false(
            $this->search->is_order_like_term( '#' ),
            'Just # should NOT be order-like'
        );

        // Summary
        echo "\n=== Test Summary ===\n";
        echo "Passed: {$this->passed}\n";
        echo "Failed: {$this->failed}\n";
        echo "Total:  " . ( $this->passed + $this->failed ) . "\n";

        if ( $this->failed === 0 ) {
            echo "\n✓ All tests passed!\n\n";
        } else {
            echo "\n✗ Some tests failed.\n\n";
        }

        return $this->failed === 0;
    }
}

// Run tests
$test = new KISS_Order_Term_Parsing_Test();
$test->run_tests();

