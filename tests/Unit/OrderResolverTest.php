<?php
/**
 * Tests for KISS_Woo_Order_Resolver.
 *
 * @package KISS_Woo_Fast_Search\Tests\Unit
 */

namespace KISS\Tests\Unit;

use Brain\Monkey\Functions;
use KISS_Woo_Order_Resolver;
use KISS_Woo_Search_Cache;
use Mockery;

class OrderResolverTest extends \KISS_Test_Case {

    private KISS_Woo_Search_Cache $cache;
    private KISS_Woo_Order_Resolver $resolver;

    protected function setUp(): void {
        parent::setUp();
        $this->cache    = new KISS_Woo_Search_Cache();
        $this->resolver = new KISS_Woo_Order_Resolver( $this->cache );
    }

    protected function tearDown(): void {
        Mockery::close();
        parent::tearDown();
    }

    // =========================================================================
    // looks_like_order_number() Tests
    // =========================================================================

    /**
     * @dataProvider orderNumberPatternProvider
     */
    public function test_looks_like_order_number( string $input, bool $expected ): void {
        $result = $this->resolver->looks_like_order_number( $input );
        $this->assertSame( $expected, $result, "Input: '{$input}'" );
    }

    public function orderNumberPatternProvider(): array {
        return [
            // Valid order numbers.
            [ '12345', true ],
            [ 'B12345', true ],
            [ 'b12345', true ],
            [ 'D12345', true ],
            [ 'd99999', true ],
            [ '#12345', true ],
            [ '#B12345', true ],
            [ '#D12345', true ],
            [ '  B12345  ', true ],  // Trimmed.

            // Invalid patterns.
            [ 'john smith', false ],
            [ 'john@example.com', false ],
            [ 'AB12345', false ],    // Invalid prefix.
            [ 'X12345', false ],     // X not in allowed prefixes.
            [ 'B', false ],          // Prefix only, no number.
            [ '', false ],           // Empty.
            [ 'B12A34', false ],     // Mixed alphanumeric.
            [ '12345abc', false ],   // Trailing letters.
        ];
    }

    // =========================================================================
    // resolve() Tests - Cache Behavior
    // =========================================================================

    public function test_resolve_returns_cached_order(): void {
        // Pre-populate cache with order ID 999.
        $cache_key = $this->cache->get_search_key( 'b12345', 'order' );
        $this->cache->set( $cache_key, 999 );

        // Mock wc_get_order to return a fake order.
        $mock_order = Mockery::mock( 'WC_Order' );
        $mock_order->shouldReceive( 'get_id' )->andReturn( 999 );

        Functions\expect( 'wc_get_order' )
            ->once()
            ->with( 999 )
            ->andReturn( $mock_order );

        $result = $this->resolver->resolve( 'B12345' );

        $this->assertSame( $mock_order, $result['order'] );
        $this->assertSame( 'cache', $result['source'] );
        $this->assertTrue( $result['cached'] );
    }

    public function test_resolve_returns_cached_miss(): void {
        // Pre-populate cache with 0 (cached "not found").
        $cache_key = $this->cache->get_search_key( 'b99999', 'order' );
        $this->cache->set( $cache_key, 0 );

        $result = $this->resolver->resolve( 'B99999' );

        $this->assertNull( $result['order'] );
        $this->assertSame( 'cache', $result['source'] );
        $this->assertTrue( $result['cached'] );
    }

    public function test_resolve_returns_invalid_for_non_order_input(): void {
        $result = $this->resolver->resolve( 'john@example.com' );

        $this->assertNull( $result['order'] );
        $this->assertSame( 'invalid', $result['source'] );
        $this->assertFalse( $result['cached'] );
    }

    // =========================================================================
    // resolve() Tests - Direct ID Fallback
    // =========================================================================

    public function test_resolve_falls_back_to_direct_id(): void {
        // No sequential plugin, wc_get_order returns valid order.
        $mock_order = Mockery::mock( 'WC_Order' );
        $mock_order->shouldReceive( 'get_id' )->andReturn( 12345 );
        $mock_order->shouldReceive( 'get_order_number' )->andReturn( '12345' );

        Functions\expect( 'wc_get_order' )
            ->once()
            ->with( 12345 )
            ->andReturn( $mock_order );

        $result = $this->resolver->resolve( '12345' );

        $this->assertSame( $mock_order, $result['order'] );
        $this->assertSame( 'direct_id', $result['source'] );
        $this->assertFalse( $result['cached'] );
    }

    public function test_resolve_caches_not_found_result(): void {
        Functions\expect( 'wc_get_order' )
            ->once()
            ->with( 99999 )
            ->andReturn( false );

        $result = $this->resolver->resolve( '99999' );

        $this->assertNull( $result['order'] );
        $this->assertSame( 'not_found', $result['source'] );
        $this->assertFalse( $result['cached'] );

        // Verify cache was set to 0.
        $cache_key = $this->cache->get_search_key( '99999', 'order' );
        $this->assertSame( 0, $this->cache->get( $cache_key ) );
    }

    public function test_resolve_rejects_mismatched_order_number(): void {
        // User searches for B12345, but order 12345 displays as D12345.
        $mock_order = Mockery::mock( 'WC_Order' );
        $mock_order->shouldReceive( 'get_id' )->andReturn( 12345 );
        $mock_order->shouldReceive( 'get_order_number' )->andReturn( 'D12345' );

        Functions\expect( 'wc_get_order' )
            ->once()
            ->with( 12345 )
            ->andReturn( $mock_order );

        $result = $this->resolver->resolve( 'B12345' );

        $this->assertNull( $result['order'] );
        $this->assertSame( 'not_found', $result['source'] );
    }
}

