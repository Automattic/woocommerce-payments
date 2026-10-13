<?php
/**
 * Agent purchase quote boundary tests.
 *
 * @package WooCommerce\Payments
 */

namespace WCPay\Tests\Internal\Service\AgentPurchases;

use RuntimeException;
use WC_Product_Simple;
use WCPay\Internal\Service\AgentPurchases\QuoteService;
use WCPAY_UnitTestCase;

/** Verify quote restrictions and storefront isolation. */
class QuoteServiceTest extends WCPAY_UnitTestCase {
	/**
	 * Reject ordinary products unless the merchant has explicitly enabled them.
	 */
	public function test_product_requires_explicit_allowlist(): void {
		$product = new WC_Product_Simple();
		$product->set_name( 'Quote test product' );
		$product->set_regular_price( '12.00' );
		$product->save();
		$original_allowlist = get_option( 'wcpay_agent_purchase_product_ids', [] );
		update_option( 'wcpay_agent_purchase_product_ids', [] );
		try {
			$this->expectException( RuntimeException::class );
			$this->expectExceptionMessage( 'Choose an available agent purchase product' );
			( new QuoteService() )->calculate( $product->get_id(), 1, [] );
		} finally {
			update_option( 'wcpay_agent_purchase_product_ids', $original_allowlist );
			$product->delete( true );
		}
	}

	/**
	 * A failed quote restores the live cart and invokes integration cleanup.
	 */
	public function test_failed_quote_restores_storefront_state_and_runs_cleanup(): void {
		$original = [ WC()->cart, WC()->customer, WC()->session, WC()->shipping()->packages ];
		$cleaned  = false;
		$cleanup  = static function () use ( &$cleaned ) {
			$cleaned = true;
		};
		add_action( 'wcpay_agent_purchase_after_quote', $cleanup );
		try {
			try {
				( new QuoteService() )->calculate( 0, 1, [] );
				$this->fail( 'An invalid product must not produce a quote.' );
			} catch ( RuntimeException $error ) {
				$this->assertStringContainsString( 'available agent purchase product', $error->getMessage() );
			}
			$this->assertSame( $original, [ WC()->cart, WC()->customer, WC()->session, WC()->shipping()->packages ] );
			$this->assertTrue( $cleaned );
		} finally {
			remove_action( 'wcpay_agent_purchase_after_quote', $cleanup );
		}
	}

	/**
	 * Enabled products still require a bounded quantity.
	 */
	public function test_enabled_product_rejects_excess_quantity(): void {
		$product = new WC_Product_Simple();
		$product->set_name( 'Quote test product' );
		$product->set_regular_price( '12.00' );
		$product->save();
		$original_allowlist = get_option( 'wcpay_agent_purchase_product_ids', [] );
		update_option( 'wcpay_agent_purchase_product_ids', [ $product->get_id() ] );
		try {
			$this->expectException( RuntimeException::class );
			$this->expectExceptionMessage( 'quantity from 1 to 5' );
			( new QuoteService() )->calculate( $product->get_id(), 6, [] );
		} finally {
			update_option( 'wcpay_agent_purchase_product_ids', $original_allowlist );
			$product->delete( true );
		}
	}
}
