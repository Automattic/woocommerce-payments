<?php
/**
 * Tests for WCPay\Internal\Abilities\Domain\GetAccount.
 *
 * @package WooCommerce\Payments\Tests
 */

namespace WCPay\Tests\Internal\Abilities\Domain;

use WCPAY_UnitTestCase;
use WCPay\Internal\Abilities\AbilitiesRegistrar;
use WCPay\Internal\Abilities\Domain\GetAccount;

/**
 * @coversDefaultClass \WCPay\Internal\Abilities\Domain\GetAccount
 */
class GetAccountTest extends WCPAY_UnitTestCase {

	public function test_name(): void {
		$this->assertSame( 'woocommerce-payments/get-account', GetAccount::get_name() );
	}

	public function test_registration_args_shape(): void {
		$args = GetAccount::get_registration_args();

		$this->assertSame( AbilitiesRegistrar::CATEGORY_SLUG, $args['category'] );
		$this->assertSame( [ GetAccount::class, 'execute' ], $args['execute_callback'] );
		$this->assertSame( [ AbilitiesRegistrar::class, 'current_user_can_manage_woocommerce' ], $args['permission_callback'] );
		$this->assertTrue( $args['meta']['show_in_rest'] );
		$this->assertTrue( $args['meta']['annotations']['readonly'] );
		$this->assertSame( 'object', $args['input_schema']['type'] );
		$this->assertFalse( $args['input_schema']['additionalProperties'] );
	}

	public function test_execute_returns_wp_error_when_account_data_is_empty_array(): void {
		// Simulate the init-failure path: WC_Payments_Account::get_cached_account_data()
		// returns false on an API error, the controller wraps it via
		// rest_ensure_response(false), and delegate_to_rest_controller() unwraps
		// the boolean false to []. The execute guard must convert that []
		// sentinel back into the documented WP_Error contract.
		$filter = function ( $result, $server, $request ) {
			if ( $request->get_route() === '/wc/v3/payments/accounts' ) {
				return new \WP_REST_Response( false, 200 );
			}
			return $result;
		};
		add_filter( 'rest_pre_dispatch', $filter, 10, 3 );

		try {
			$result = GetAccount::execute( null );
		} finally {
			remove_filter( 'rest_pre_dispatch', $filter, 10 );
		}

		$this->assertInstanceOf(
			\WP_Error::class,
			$result,
			'execute must convert the [] init-failure sentinel back into a WP_Error.'
		);
		$this->assertSame(
			'wcpay_not_initialized',
			$result->get_error_code(),
			'execute must surface the wcpay_not_initialized error code so callers using is_wp_error() can detect init failure.'
		);
	}

	public function test_execute_returns_account_data_on_success(): void {
		$account = [
			'status'  => 'complete',
			'country' => 'US',
		];
		$filter  = function ( $result, $server, $request ) use ( $account ) {
			if ( $request->get_route() === '/wc/v3/payments/accounts' ) {
				return new \WP_REST_Response( $account, 200 );
			}
			return $result;
		};
		add_filter( 'rest_pre_dispatch', $filter, 10, 3 );

		try {
			$result = GetAccount::execute( null );
		} finally {
			remove_filter( 'rest_pre_dispatch', $filter, 10 );
		}

		$this->assertSame( $account, $result );
	}
}
