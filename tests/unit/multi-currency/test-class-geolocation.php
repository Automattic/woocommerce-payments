<?php
/**
 * Class WCPay_Multi_Currency_Geolocation_Tests
 *
 * @package WooCommerce\Payments\Tests
 */

/**
 * WCPay\MultiCurrency\Geolocation unit tests.
 */
class WCPay_Multi_Currency_Geolocation_Tests extends WP_UnitTestCase {
	/**
	 * WC_Payments_Localization_Service mock.
	 *
	 * @var WC_Payments_Localization_Service
	 */
	private $mock_localization_service;

	/**
	 * WCPay\MultiCurrency\Geolocation instance.
	 *
	 * @var WCPay\MultiCurrency\Geolocation
	 */
	private $geolocation;

	/**
	 * Pre-test setup
	 */
	public function setUp() {
		parent::setUp();

		$this->mock_localization_service = $this->createMock( WC_Payments_Localization_Service::class );
		$this->geolocation               = new WCPay\MultiCurrency\Geolocation( $this->mock_localization_service );
	}

	public function test_get_country_by_customer_location_returns_geolocation_country() {
		add_filter(
			'woocommerce_geolocate_ip',
			function() {
				return 'CA';
			}
		);
		$this->assertSame( 'CA', $this->geolocation->get_country_by_customer_location() );
	}

	public function test_get_country_by_customer_location_returns_default_country_when_no_geolocation() {
		add_filter(
			'woocommerce_geolocate_ip',
			function() {
				return '';
			}
		);

		add_filter(
			'woocommerce_customer_default_location',
			function() {
				return 'BR';
			}
		);

		$this->assertSame( 'BR', $this->geolocation->get_country_by_customer_location() );
	}

	public function test_get_currency_by_customer_location_returns_geolocation_currency_code() {
		$this->mock_localization_service->method( 'get_country_locale_data' )->with( 'CA' )->willReturn( [ 'currency_code' => 'CAD' ] );

		add_filter(
			'woocommerce_geolocate_ip',
			function() {
				return 'CA';
			}
		);

		$this->assertSame( 'CAD', $this->geolocation->get_currency_by_customer_location() );
	}

	public function test_get_currency_by_customer_location_returns_default_currency_code() {
		$this->mock_localization_service->method( 'get_country_locale_data' )->with( 'BR' )->willReturn( [ 'currency_code' => 'BRL' ] );

		add_filter(
			'woocommerce_geolocate_ip',
			function() {
				return '';
			}
		);
		add_filter(
			'woocommerce_customer_default_location',
			function() {
				return 'BR';
			}
		);

		$this->assertSame( 'BRL', $this->geolocation->get_currency_by_customer_location() );
	}

	public function test_get_currency_by_customer_location_returns_null() {
		add_filter(
			'woocommerce_geolocate_ip',
			function() {
				return '';
			}
		);
		add_filter(
			'woocommerce_customer_default_location',
			function() {
				return '';
			}
		);
		$this->assertSame( null, $this->geolocation->get_currency_by_customer_location() );
	}
}
