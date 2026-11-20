<?php
/**
 * Agent purchase boundary tests.
 *
 * @package WooCommerce\Payments\Tests
 */

namespace WCPay\Tests\Internal\Service\AgentPurchases;

use WCPay\Internal\Service\AgentPurchases\Feature;
use WCPay\Internal\Service\AgentPurchases\QuoteService;
use WCPAY_UnitTestCase;
use WP_REST_Request;

/**
 * Verifies experiment isolation, authentication, and shopper approval boundaries.
 */
class FeatureTest extends WCPAY_UnitTestCase {

	/**
	 * Opting in cannot expose the experiment on a non-development WordPress site.
	 */
	public function test_enabled_option_cannot_bypass_wordpress_environment(): void {
		if ( 'development' === wp_get_environment_type() ) {
			$this->markTestSkipped( 'Requires the standard non-development WordPress test environment.' );
		}
		$mode     = \WC_Payments::mode();
		$was_dev  = $mode->is_dev();
		$was_test = $mode->is_test();
		try {
			$mode->dev();
			update_option( 'wcpay_agent_purchases_experiment_enabled', 'yes' );

			$this->assertTrue( $mode->is_dev() );
			$this->assertTrue( $mode->is_test() );
			$this->assertFalse( Feature::is_enabled() );
		} finally {
			$mode->live();
			if ( $was_dev ) {
				$mode->dev();
			} elseif ( $was_test ) {
				$mode->test();
			}
		}
	}

	/**
	 * Disabled experiments must not expose routes or change website checkout.
	 */
	public function test_disabled_experiment_has_no_public_surface(): void {
		delete_option( 'wcpay_agent_purchases_experiment_enabled' );
		update_option( 'wcpay_agent_purchases_web_enabled', 'no' );
		$feature  = new Feature( $this->createMock( QuoteService::class ) );
		$gateways = [
			'woocommerce_payments' => new \stdClass(),
			'bacs'                 => new \stdClass(),
		];
		$before   = rest_get_server()->get_routes();

		$feature->register();
		$feature->routes();

		$this->assertFalse( Feature::is_enabled() );
		$this->assertFalse( has_action( 'rest_api_init', [ $feature, 'routes' ] ) );
		$this->assertFalse( has_action( 'template_redirect', [ $feature, 'approval' ] ) );
		$this->assertSame( $before, rest_get_server()->get_routes() );
		$this->assertSame( $gateways, $feature->filter_website_gateways( $gateways ) );
	}

	/**
	 * A valid agent credential cannot turn on a disabled experiment.
	 */
	public function test_agent_key_cannot_bypass_disabled_experiment(): void {
		delete_option( 'wcpay_agent_purchases_experiment_enabled' );
		update_option( 'wcpay_agent_purchase_key_hash', hash( 'sha256', 'unit-test-agent-key' ) );
		$request = new WP_REST_Request( 'GET', '/wcpay/agent-purchases/v1/products' );
		$request->set_header( 'X-WCPay-Agent', 'unit-test-agent-key' );
		$feature = new Feature( $this->createMock( QuoteService::class ) );

		$result = $feature->auth( $request );

		$this->assertWPError( $result );
		$this->assertSame( 403, $result->get_error_data()['status'] );
	}

	/**
	 * Missing, incorrect, and correct credentials exercise the real key comparison.
	 */
	public function test_agent_key_is_required_even_in_an_allowed_environment(): void {
		$feature = $this->getMockBuilder( Feature::class )
			->setConstructorArgs( [ $this->createMock( QuoteService::class ) ] )
			->onlyMethods( [ 'guard' ] )
			->getMock();
		update_option( 'wcpay_agent_purchase_key_hash', hash( 'sha256', 'unit-test-agent-key' ) );
		$request = new WP_REST_Request( 'GET', '/wcpay/agent-purchases/v1/products' );

		$this->assertWPError( $feature->auth( $request ) );
		$request->set_header( 'X-WCPay-Agent', 'incorrect-key' );
		$this->assertWPError( $feature->auth( $request ) );
		$request->set_header( 'X-WCPay-Agent', 'unit-test-agent-key' );
		$this->assertTrue( $feature->auth( $request ) );
	}

	/**
	 * No approval means no quote recalculation or order creation.
	 */
	public function test_completion_requires_explicit_shopper_approval(): void {
		$quotes = $this->createMock( QuoteService::class );
		$quotes->expects( $this->never() )->method( 'calculate' );
		$quotes->expects( $this->never() )->method( 'make_order' );
		$feature = $this->getMockBuilder( Feature::class )
			->setConstructorArgs( [ $quotes ] )
			->onlyMethods( [ 'guard' ] )
			->getMock();
		$id      = str_repeat( 'a', 32 );
		update_option( 'wcpay_agent_purchases_enabled', 'yes' );
		update_option(
			'wcpay_agent_purchase_quote_' . $id,
			[
				'id'         => $id,
				'owner'      => 1,
				'state'      => 'awaiting_approval',
				'order_id'   => null,
				'quote'      => [],
				'expires_at' => time() + 600,
			]
		);
		$request       = new WP_REST_Request( 'POST', '/wcpay/agent-purchases/v1/quotes/' . $id . '/complete' );
		$request['id'] = $id;
		$cleanup_count = did_action( 'wcpay_agent_purchase_after_payment' );

		$result = $feature->request( $request );

		$this->assertWPError( $result );
		$this->assertSame( 409, $result->get_error_data()['status'] );
		$this->assertStringContainsString( 'approval', strtolower( $result->get_error_message() ) );
		$this->assertSame( 'awaiting_approval', get_option( 'wcpay_agent_purchase_quote_' . $id )['state'] );
		$this->assertFalse( get_option( 'wcpay_agent_purchase_quote_' . $id . '_lock' ) );
		$this->assertSame( $cleanup_count, did_action( 'wcpay_agent_purchase_after_payment' ), 'A rejected quote must not clean up a payment provider that was never started.' );
	}

	/**
	 * Expired or changed approval must not create an order or start a payment.
	 *
	 * @dataProvider invalid_approval_provider
	 * @param string $scenario Rejection scenario.
	 * @param string $message Expected rejection message.
	 */
	public function test_completion_rejects_invalid_approval( string $scenario, string $message ): void {
		$quote = [
			'product_id'       => 123,
			'quantity'         => 1,
			'shipping_address' => [],
			'fingerprint'      => 'quoted-fingerprint',
		];
		$fresh = $quote;
		if ( 'changed' === $scenario ) {
			$fresh['fingerprint'] = 'new-price-fingerprint';
		}
		$quotes = $this->createMock( QuoteService::class );
		$quotes->expects( 'expired' === $scenario ? $this->never() : $this->once() )
			->method( 'calculate' )->willReturn( $fresh );
		$quotes->expects( $this->never() )->method( 'make_order' );
		$feature = $this->getMockBuilder( Feature::class )
			->setConstructorArgs( [ $quotes ] )
			->onlyMethods( [ 'guard' ] )
			->getMock();
		$id      = str_repeat( 'b', 32 );
		update_option( 'wcpay_agent_purchases_enabled', 'yes' );
		update_option(
			'wcpay_agent_purchase_quote_' . $id,
			[
				'id'                   => $id,
				'owner'                => 1,
				'state'                => 'approved',
				'order_id'             => null,
				'quote'                => $quote,
				'expires_at'           => time() + ( 'expired' === $scenario ? -60 : 600 ),
				'approved_fingerprint' => 'mismatched' === $scenario ? 'other-purchase' : $quote['fingerprint'],
			]
		);
		$request       = new WP_REST_Request( 'POST', '/wcpay/agent-purchases/v1/quotes/' . $id . '/complete' );
		$request['id'] = $id;

		$result = $feature->request( $request );

		$this->assertWPError( $result );
		$this->assertSame( 409, $result->get_error_data()['status'] );
		$this->assertStringContainsString( $message, $result->get_error_message() );
		$this->assertNull( get_option( 'wcpay_agent_purchase_quote_' . $id )['order_id'] );
	}

	/**
	 * Invalid approval cases.
	 *
	 * @return array
	 */
	public function invalid_approval_provider(): array {
		return [
			'expired quote'         => [ 'expired', 'expired' ],
			'changed quote'         => [ 'changed', 'changed' ],
			'mismatched approval'   => [ 'mismatched', 'Approval does not match' ],
			'no payment authorizer' => [ 'missing_provider', 'No payment authorization provider' ],
		];
	}
}
