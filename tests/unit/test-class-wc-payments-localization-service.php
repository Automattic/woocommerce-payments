<?php
/**
 * Class WC_Payments_Localization_Service_Test
 *
 * @package WooCommerce\Payments\Tests
 */

/**
 * WC_Payments_Localization_Service_Test unit tests.
 */
class WC_Payments_Localization_Service_Test extends WP_UnitTestCase {
	/**
	 * WC_Payments_Localization_Service instance.
	 *
	 * @var WC_Payments_Localization_Service
	 */
	private $localization_service;

	/**
	 * Pre-test setup
	 */
	public function setUp() {
		parent::setUp();

		$this->localization_service = new WC_Payments_Localization_Service();
	}

	public function tearDown() {
		wp_set_current_user( 0 );
		remove_all_filters( 'locale' );
		remove_all_filters( 'wcpay_eur_format' );
	}

	public function test_get_currency_format_returns_default_format() {
		$this->assertSame(
			[
				'currency_pos' => 'left',
				'thousand_sep' => ',',
				'decimal_sep'  => '.',
				'num_decimals' => 2,
			],
			$this->localization_service->get_currency_format( 'FFF' )
		);
	}

	public function test_get_currency_format_returns_default_locale_format() {
		$this->assertSame(
			[
				'currency_pos' => 'right_space',
				'thousand_sep' => '.',
				'decimal_sep'  => ',',
				'num_decimals' => 2,
			],
			$this->localization_service->get_currency_format( 'EUR' )
		);
	}

	public function test_get_currency_format_returns_specific_locale_format() {
		$this->mock_locale( 'nl_NL' );

		$this->assertSame(
			[
				'currency_pos' => 'left_space',
				'thousand_sep' => '.',
				'decimal_sep'  => ',',
				'num_decimals' => 2,
			],
			$this->localization_service->get_currency_format( 'EUR' )
		);
	}

	public function test_get_currency_format_returns_filtered_format() {
		$filtered_format = [
			'currency_pos' => 'right_space',
			'thousand_sep' => ' ',
			'decimal_sep'  => '.',
			'num_decimals' => 3,
		];

		$this->mock_locale( 'nl_NL' );

		add_filter(
			'wcpay_eur_format',
			function ( $currency_format, $locale ) use ( $filtered_format ) {
				$this->assertSame( 'nl_NL', $locale );
				$this->assertSame(
					[
						'currency_pos' => 'left_space',
						'thousand_sep' => '.',
						'decimal_sep'  => ',',
						'num_decimals' => 2,
					],
					$currency_format
				);

				return $filtered_format;
			},
			10,
			2
		);

		$this->assertSame(
			$filtered_format,
			$this->localization_service->get_currency_format( 'EUR' )
		);
	}

	public function test_get_user_locale_returns_default_locale() {
		$this->assertSame( 'en_US', $this->localization_service->get_user_locale() );
	}

	public function test_get_user_locale_returns_filtered_locale() {
		$this->mock_locale( 'pt_BR' );

		$this->assertSame( 'pt_BR', $this->localization_service->get_user_locale() );
	}

	public function test_get_user_locale_returns_user_locale() {
		$this->mock_locale( 'pt_BR' ); // Make sure filtered locale is ignored.

		wp_set_current_user( 1 );
		wp_get_current_user()->locale = 'en_GB';

		$this->assertSame( 'en_GB', $this->localization_service->get_user_locale() );
	}

	public function test_get_country_locale_data() {
		$this->assertSame(
			[
				'currency_code'  => 'BRL',
				'currency_pos'   => 'left_space',
				'thousand_sep'   => '.',
				'decimal_sep'    => ',',
				'num_decimals'   => 2,
				'weight_unit'    => 'kg',
				'dimension_unit' => 'cm',
				'direction'      => 'ltr',
				'default_locale' => 'pt_BR',
				'name'           => 'Brazilian real',
				'singular'       => 'Brazilian real',
				'plural'         => 'Brazilian reals',
				'short_symbol'   => 'R$',
				'locales'        => [
					'default' => [
						'thousand_sep' => '.',
						'decimal_sep'  => ',',
						'direction'    => 'ltr',
						'currency_pos' => 'left_space',
					],
					'pt_BR'   => [
						'thousand_sep' => '.',
						'decimal_sep'  => ',',
						'direction'    => 'ltr',
						'currency_pos' => 'left_space',
					],
				],
			],
			$this->localization_service->get_country_locale_data( 'BR' )
		);
	}

	public function test_get_country_locale_data_for_invalid_country() {
		$this->assertSame(
			[],
			$this->localization_service->get_country_locale_data( 'COUNTRY' )
		);
	}

	public function test_transient_data_set() {
		$this->assertTrue( is_array( get_transient( WC_Payments_Localization_Service::WCPAY_CURRENCY_FORMAT_TRANSIENT ) ) );
		$this->assertTrue( is_array( get_transient( WC_Payments_Localization_Service::WCPAY_LOCALE_INFO_TRANSIENT ) ) );
	}

	private function mock_locale( $locale ) {
		add_filter(
			'locale',
			function () use ( $locale ) {
				return $locale;
			}
		);
	}
}
