<?php
/**
 * Class SettingsRedirectServiceTest
 *
 * @package WooCommerce\Payments\Tests
 */

namespace WCPay\Tests\Internal\Service;

use Exception;
use WC_Payments;
use WCPay\Internal\Proxy\LegacyProxy;
use WCPay\Internal\Service\SettingsRedirectService;
use WCPAY_UnitTestCase;

/**
 * SettingsRedirectService unit tests.
 */
class SettingsRedirectServiceTest extends WCPAY_UnitTestCase {
	const REGISTERED_METHOD   = 'klarna';
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

	public function set_up() {
		parent::set_up();

		$this->original_get = $_GET;

		$mock_legacy_proxy = $this->createMock( LegacyProxy::class );
		$mock_legacy_proxy->method( 'call_static' )
			->with( WC_Payments::class, 'get_payment_gateway_map' )
			->willReturn(
				[
					'card'                  => new \stdClass(),
					self::REGISTERED_METHOD => new \stdClass(),
				]
			);

		$this->sut               = new SettingsRedirectService( $mock_legacy_proxy );
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
			'klarna-rates'                              => '',
			'campaign'                                  => 'spring 2026',
		];

		$this->run_redirect();

		$this->assertSame(
			admin_url( 'admin.php?page=wc-settings&tab=checkout&section=woocommerce_payments&anchor=%23payment-method-klarna&woopayments-flag-klarna-rates=1&woopayments-flag-campaign=spring%202026' ),
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

	public function test_flags_cannot_override_settings_page_args() {
		$_GET = [
			SettingsRedirectService::REDIRECT_QUERY_ARG => '',
			'page'                                      => 'evil',
			'anchor'                                    => '#elsewhere',
		];

		$this->run_redirect();

		$this->assertSame(
			admin_url( 'admin.php?page=wc-settings&tab=checkout&section=woocommerce_payments&woopayments-flag-page=evil&woopayments-flag-anchor=%23elsewhere' ),
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

	public function test_settings_url_sanitizes_and_caps_flags() {
		$flags = [
			'Mixed Case!' => 'value',
			'array'       => [ 'not', 'forwarded' ],
			'tagged'      => '<b>bold</b>',
			'long'        => str_repeat( 'a', 150 ),
		];
		for ( $i = 0; $i < 12; $i++ ) {
			$flags[ 'extra' . $i ] = '1';
		}

		$url   = $this->sut->get_settings_url( '', $flags );
		$query = [];
		wp_parse_str( (string) wp_parse_url( $url, PHP_URL_QUERY ), $query );

		$this->assertSame( 'value', $query['woopayments-flag-mixedcase'] );
		$this->assertArrayNotHasKey( 'woopayments-flag-array', $query );
		$this->assertSame( 'bold', $query['woopayments-flag-tagged'] );
		$this->assertSame( str_repeat( 'a', 100 ), $query['woopayments-flag-long'] );
		$this->assertCount( 10, preg_grep( '/^woopayments-flag-/', array_keys( $query ) ) );
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
