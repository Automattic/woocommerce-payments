<?php
/**
 * Class SettingsRedirectServiceTest
 *
 * @package WooCommerce\Payments\Tests
 */

namespace WCPay\Tests\Internal\Service;

use Exception;
use WC_Payments;
use WCPay\Internal\Proxy\HooksProxy;
use WCPay\Internal\Proxy\LegacyProxy;
use WCPay\Internal\Service\SettingsRedirectService;
use WCPAY_UnitTestCase;

/**
 * SettingsRedirectService unit tests.
 */
class SettingsRedirectServiceTest extends WCPAY_UnitTestCase {
	const REGISTERED_METHOD   = 'card';
	const UNREGISTERED_METHOD = 'not_a_method';

	/**
	 * Service under test.
	 *
	 * @var SettingsRedirectService
	 */
	private $sut;

	/**
	 * `$_GET` before the test.
	 *
	 * @var array
	 */
	private $original_get;

	/**
	 * Location passed to `wp_redirect`, if any.
	 *
	 * @var string|null
	 */
	private $redirect_location;

	/**
	 * Hooks proxy mock.
	 *
	 * @var HooksProxy|\PHPUnit\Framework\MockObject\MockObject
	 */
	private $mock_hooks_proxy;

	public function set_up() {
		parent::set_up();

		$this->original_get = $_GET;

		$this->mock_hooks_proxy  = $this->createMock( HooksProxy::class );
		$this->sut               = new SettingsRedirectService( $this->create_legacy_proxy( $this->registered_gateway_map() ), $this->mock_hooks_proxy );
		$this->redirect_location = null;

		add_filter( 'wp_redirect', [ $this, 'capture_redirect' ] );
	}

	public function tear_down() {
		remove_filter( 'wp_redirect', [ $this, 'capture_redirect' ] );
		$_GET = $this->original_get;

		parent::tear_down();
	}

	/**
	 * Records the redirect and stops before `exit`.
	 *
	 * @param string $location Redirect location.
	 *
	 * @throws Exception Always, to stop the redirect.
	 */
	public function capture_redirect( $location ) {
		$this->redirect_location = $location;
		throw new Exception( 'redirected' );
	}

	public function test_redirects_to_settings_page() {
		$_GET = [ SettingsRedirectService::REDIRECT_QUERY_ARG => '' ];

		$this->run_redirect();

		$this->assertSame(
			admin_url( 'admin.php?page=wc-settings&tab=checkout&section=woocommerce_payments' ),
			$this->redirect_location
		);
	}

	public function test_redirect_scrolls_to_registered_method_and_forwards_flags() {
		$_GET = [
			SettingsRedirectService::REDIRECT_QUERY_ARG => '',
			'method'                                    => self::REGISTERED_METHOD,
			'rates-update'                              => '',
			'campaign'                                  => 'spring 2026',
		];

		$this->run_redirect();

		$this->assertSame(
			admin_url( 'admin.php?page=wc-settings&tab=checkout&section=woocommerce_payments&anchor=%23payment-method-card&woopayments-flag-rates-update=1&woopayments-flag-campaign=spring%202026' ),
			$this->redirect_location
		);
	}

	public function test_redirect_drops_unregistered_method() {
		$_GET = [
			SettingsRedirectService::REDIRECT_QUERY_ARG => '',
			'method'                                    => self::UNREGISTERED_METHOD,
		];

		$this->run_redirect();

		$this->assertSame(
			admin_url( 'admin.php?page=wc-settings&tab=checkout&section=woocommerce_payments' ),
			$this->redirect_location
		);
	}

	public function test_redirect_drops_flags_that_are_not_allowed() {
		$_GET = [
			SettingsRedirectService::REDIRECT_QUERY_ARG => '',
			'utm_source'                                => 'email',
			'fbclid'                                    => 'abc',
			'page'                                      => 'evil',
			'anchor'                                    => '#elsewhere',
			'rates-update'                              => '',
		];

		$this->run_redirect();

		$this->assertSame(
			admin_url( 'admin.php?page=wc-settings&tab=checkout&section=woocommerce_payments&woopayments-flag-rates-update=1' ),
			$this->redirect_location
		);
	}

	public function test_redirect_ignores_array_method() {
		$_GET = [
			SettingsRedirectService::REDIRECT_QUERY_ARG => '',
			'method'                                    => [ self::REGISTERED_METHOD ],
		];

		$this->run_redirect();

		$this->assertSame(
			admin_url( 'admin.php?page=wc-settings&tab=checkout&section=woocommerce_payments' ),
			$this->redirect_location
		);
	}

	public function test_redirect_drops_array_flags() {
		$_GET = [
			SettingsRedirectService::REDIRECT_QUERY_ARG => '',
			'campaign'                                  => [ 'a', 'b' ],
		];

		$this->run_redirect();

		$this->assertSame(
			admin_url( 'admin.php?page=wc-settings&tab=checkout&section=woocommerce_payments' ),
			$this->redirect_location
		);
	}

	public function test_redirect_continues_the_request_when_the_redirect_is_not_sent() {
		remove_filter( 'wp_redirect', [ $this, 'capture_redirect' ] );
		$record_and_cancel = function ( $location ) {
			$this->redirect_location = $location;

			return false;
		};
		add_filter( 'wp_redirect', $record_and_cancel );
		$_GET = [ SettingsRedirectService::REDIRECT_QUERY_ARG => '' ];

		try {
			$this->sut->maybe_redirect();
		} finally {
			remove_filter( 'wp_redirect', $record_and_cancel );
		}

		$this->assertSame(
			admin_url( 'admin.php?page=wc-settings&tab=checkout&section=woocommerce_payments' ),
			$this->redirect_location
		);
	}

	public function test_redirect_ignores_requests_without_query_arg() {
		$_GET = [ 'method' => self::REGISTERED_METHOD ];

		$this->sut->maybe_redirect();

		$this->assertNull( $this->redirect_location );
	}

	public function test_redirect_skips_ajax_requests() {
		$_GET = [ SettingsRedirectService::REDIRECT_QUERY_ARG => '' ];
		add_filter( 'wp_doing_ajax', '__return_true' );

		try {
			$this->sut->maybe_redirect();
		} finally {
			remove_filter( 'wp_doing_ajax', '__return_true' );
		}

		$this->assertNull( $this->redirect_location );
	}

	public function test_init_hooks_registers_the_redirect_early_on_template_redirect() {
		$this->mock_hooks_proxy->expects( $this->once() )
			->method( 'add_action' )
			->with( 'template_redirect', [ $this->sut, 'maybe_redirect' ], 1 );

		$this->sut->init_hooks();
	}

	public function test_settings_url_sanitizes_flag_names_and_values() {
		$flags = [
			'Rates-Update' => 'value',
			'campaign'     => str_repeat( 'a', 150 ),
		];

		$this->assertSame(
			admin_url( 'admin.php?page=wc-settings&tab=checkout&section=woocommerce_payments&woopayments-flag-rates-update=value&woopayments-flag-campaign=aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa' ),
			$this->sut->get_settings_url( '', $flags )
		);
	}

	public function test_settings_url_keeps_the_last_value_of_colliding_flag_names() {
		$flags = [
			'Rates-Update' => 'first',
			'rates-update' => 'last',
		];

		$this->assertSame(
			admin_url( 'admin.php?page=wc-settings&tab=checkout&section=woocommerce_payments&woopayments-flag-rates-update=last' ),
			$this->sut->get_settings_url( '', $flags )
		);
	}

	public function test_settings_url_replaces_empty_sanitized_values_with_one() {
		$flags = [
			'rates-update' => '<b></b>',
			'campaign'     => '   ',
		];

		$this->assertSame(
			admin_url( 'admin.php?page=wc-settings&tab=checkout&section=woocommerce_payments&woopayments-flag-rates-update=1&woopayments-flag-campaign=1' ),
			$this->sut->get_settings_url( '', $flags )
		);
	}

	public function test_settings_url_converts_bool_and_int_flag_values() {
		$this->assertSame(
			admin_url( 'admin.php?page=wc-settings&tab=checkout&section=woocommerce_payments&woopayments-flag-rates-update=0&woopayments-flag-campaign=5' ),
			$this->sut->get_settings_url(
				'',
				[
					'rates-update' => false,
					'campaign'     => 5,
				]
			)
		);
	}

	public function test_settings_url_omits_anchor_without_a_gateway_map() {
		$sut = new SettingsRedirectService( $this->create_legacy_proxy( null ), $this->mock_hooks_proxy );

		$url = $sut->get_settings_url( self::REGISTERED_METHOD );

		$this->assertSame(
			admin_url( 'admin.php?page=wc-settings&tab=checkout&section=woocommerce_payments' ),
			$url
		);
	}

	/**
	 * @dataProvider provider_methods_without_a_payment_method_row
	 */
	public function test_settings_url_anchors_methods_to_their_own_row( string $method, string $expected_row_id ) {
		$sut = new SettingsRedirectService(
			$this->create_legacy_proxy(
				[
					$method => new \stdClass(),
				]
			),
			$this->mock_hooks_proxy
		);

		$url = $sut->get_settings_url( $method );

		$this->assertSame(
			admin_url( 'admin.php?page=wc-settings&tab=checkout&section=woocommerce_payments&anchor=%23' . $expected_row_id ),
			$url
		);
	}

	public function provider_methods_without_a_payment_method_row(): array {
		return [
			'link'       => [ 'link', 'express-checkouts-link' ],
			'apple_pay'  => [ 'apple_pay', 'express-checkouts-apple-google-pay' ],
			'google_pay' => [ 'google_pay', 'express-checkouts-apple-google-pay' ],
			'amazon_pay' => [ 'amazon_pay', 'express-checkouts-amazon-pay' ],
		];
	}

	/**
	 * Registered gateways, keyed by payment method ID.
	 *
	 * @return array
	 */
	private function registered_gateway_map(): array {
		return [
			self::REGISTERED_METHOD => new \stdClass(),
		];
	}

	/**
	 * Legacy proxy that returns the given gateway map and runs the settings URL helper for real.
	 *
	 * @param array|null $gateway_map Gateway map to return.
	 *
	 * @return LegacyProxy
	 */
	private function create_legacy_proxy( $gateway_map ): LegacyProxy {
		$mock_legacy_proxy = $this->createMock( LegacyProxy::class );
		$mock_legacy_proxy->method( 'call_static' )->willReturnCallback(
			function ( string $class_name, string $method_name, ...$parameters ) use ( $gateway_map ) {
				if ( WC_Payments::class === $class_name && 'get_payment_gateway_map' === $method_name ) {
					return $gateway_map;
				}

				return call_user_func_array( [ $class_name, $method_name ], $parameters );
			}
		);

		return $mock_legacy_proxy;
	}

	/**
	 * Runs the redirect, which the `wp_redirect` filter stops with an exception.
	 */
	private function run_redirect(): void {
		try {
			$this->sut->maybe_redirect();
			$this->fail( 'Expected a redirect.' );
		} catch ( Exception $e ) {
			$this->assertSame( 'redirected', $e->getMessage() );
		}
	}
}
