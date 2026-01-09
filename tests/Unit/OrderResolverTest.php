<?php
/**
 * Tests for KISS_Woo_Order_Resolver.
 *
 * @package KISS_Woo_Fast_Search\Tests\Unit
 */

namespace KISS\Tests\Unit;

use Brain\Monkey\Functions;
use KISS_Woo_Order_Resolver;
use Mockery;

class OrderResolverTest extends \KISS_Test_Case {

    private \KISS_Woo_Search_Cache $cache;
    private KISS_Woo_Order_Resolver $resolver;

    protected function setUp(): void {
        parent::setUp();
        $this->cache    = new \KISS_Woo_Search_Cache();
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
    // resolve() Tests - Invalid Input
    // =========================================================================

    public function test_resolve_returns_invalid_for_non_order_input(): void {
        $result = $this->resolver->resolve( 'john@example.com' );

        $this->assertNull( $result['order'] );
        $this->assertSame( 'invalid', $result['source'] );
        $this->assertFalse( $result['cached'] );
    }

    public function test_resolve_returns_invalid_for_empty_input(): void {
        $result = $this->resolver->resolve( '' );

        $this->assertNull( $result['order'] );
        $this->assertSame( 'invalid', $result['source'] );
        $this->assertFalse( $result['cached'] );
    }

    public function test_resolve_returns_invalid_for_prefix_only(): void {
        $result = $this->resolver->resolve( 'B' );

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

    public function test_resolve_returns_not_found_when_order_doesnt_exist(): void {
        Functions\expect( 'wc_get_order' )
            ->once()
            ->with( 99999 )
            ->andReturn( false );

        $result = $this->resolver->resolve( '99999' );

        $this->assertNull( $result['order'] );
        $this->assertSame( 'not_found', $result['source'] );
        $this->assertFalse( $result['cached'] );
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

    public function test_resolve_accepts_matching_order_number_case_insensitive(): void {
        $mock_order = Mockery::mock( 'WC_Order' );
        $mock_order->shouldReceive( 'get_id' )->andReturn( 12345 );
        $mock_order->shouldReceive( 'get_order_number' )->andReturn( 'B12345' );

        Functions\expect( 'wc_get_order' )
            ->once()
            ->with( 12345 )
            ->andReturn( $mock_order );

        // Search with lowercase 'b'.
        $result = $this->resolver->resolve( 'b12345' );

        $this->assertSame( $mock_order, $result['order'] );
        $this->assertSame( 'direct_id', $result['source'] );
    }

    public function test_resolve_handles_hash_prefix(): void {
        $mock_order = Mockery::mock( 'WC_Order' );
        $mock_order->shouldReceive( 'get_id' )->andReturn( 12345 );
        $mock_order->shouldReceive( 'get_order_number' )->andReturn( '12345' );

        Functions\expect( 'wc_get_order' )
            ->once()
            ->with( 12345 )
            ->andReturn( $mock_order );

        $result = $this->resolver->resolve( '#12345' );

        $this->assertSame( $mock_order, $result['order'] );
        $this->assertSame( 'direct_id', $result['source'] );
    }
}

