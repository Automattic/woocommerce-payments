<?php
/**
 * Multi-Currency must convert a flat-rate shipping formula once, not twice: the [cost]/[fee]
 * placeholders stand for cart totals that are already in the selected currency.
 *
 * @package WooCommerce\Payments\Tests
 */

use WCPay\MultiCurrency\MultiCurrency;
use WCPay\MultiCurrency\Utils;
use WCPay\MultiCurrency\Interfaces\MultiCurrencyAccountInterface;
use WCPay\MultiCurrency\Interfaces\MultiCurrencyApiClientInterface;
use WCPay\MultiCurrency\Interfaces\MultiCurrencyCacheInterface;
use WCPay\MultiCurrency\Interfaces\MultiCurrencySettingsInterface;
use WCPay\Constants\Currency_Code;

/**
 * Drives a real MultiCurrency instance against a real WooCommerce cart and flat-rate shipping.
 */
class WCPay_Multi_Currency_Shipping_Formula_Conversion_Test extends WCPAY_UnitTestCase {

	/**
	 * @var MultiCurrency
	 */
	private $multi_currency;

	/**
	 * The customer's address before the test, restored afterwards for the tests that run next.
	 *
	 * @var array
	 */
	private $customer_address;

	public function set_up() {
		parent::set_up();
		WCPay_Test_Request_Context::set( 'frontend' );

		// These cover the legacy `[fee]` store-currency overrides, which are not registered on the active currency path.

		$this->customer_address = [
			'shipping_country'  => WC()->customer->get_shipping_country(),
			'shipping_state'    => WC()->customer->get_shipping_state(),
			'shipping_postcode' => WC()->customer->get_shipping_postcode(),
			'billing_country'   => WC()->customer->get_billing_country(),
		];

		update_option( 'woocommerce_currency', Currency_Code::UNITED_STATES_DOLLAR );
		update_option( '_wcpay_feature_customer_multi_currency', '1' );
		update_option( 'woocommerce_calc_taxes', 'no' );
	}

	public function tear_down() {
		// The base WP_UnitTestCase restores $wp_filter and rolls back the DB each test, so hooks and
		// options need no manual cleanup. The WC() cart/session singletons are in-memory and do not.
		if ( WC()->cart ) {
			WC()->cart->empty_cart();
		}
		if ( WC()->session ) {
			WC()->session->__unset( MultiCurrency::CURRENCY_SESSION_KEY );
			WC()->session->__unset( 'chosen_shipping_methods' );
			WC()->session->__unset( 'shipping_for_package_0' );
		}
		WC()->customer->set_props( $this->customer_address );
		parent::tear_down();
	}

	/**
	 * Boots a real MultiCurrency with the store on USD and the given currency enabled and selected.
	 *
	 * @param string $code The currency to enable and select.
	 * @param float  $rate Its exchange rate against USD.
	 */
	private function use_currency( string $code, float $rate ) {
		update_option( 'wcpay_multi_currency_enabled_currencies', [ $code ] );

		$midnight   = strtotime( 'today midnight' );
		$mock_cache = $this->createMock( MultiCurrencyCacheInterface::class );
		$mock_cache->method( 'get_or_add' )->willReturn(
			[
				'currencies' => [
					'USD' => 1,
					$code => $rate,
				],
				'updated'    => $midnight,
				'expires'    => $midnight + DAY_IN_SECONDS,
			]
		);

		$mock_api_client = $this->createMock( MultiCurrencyApiClientInterface::class );
		$mock_api_client->method( 'is_server_connected' )->willReturn( true );

		$mock_account = $this->createMock( MultiCurrencyAccountInterface::class );
		$mock_account->method( 'is_provider_connected' )->willReturn( true );

		$this->multi_currency = new MultiCurrency(
			$this->createMock( MultiCurrencySettingsInterface::class ),
			$mock_api_client,
			$mock_account,
			new WC_Payments_Localization_Service(),
			$mock_cache,
			$this->createMock( Utils::class ),
			null
		);
		$this->multi_currency->init_widgets();
		$this->multi_currency->init();
		$this->multi_currency->update_selected_currency( $code );
	}

	/**
	 * WC_Shipping_Flat_Rate::fee() reads `percent`, `min_fee` and `max_fee` with the store's decimal
	 * separator from WooCommerce 11.3 (woocommerce/woocommerce#69294). Before that it reads only a dot.
	 */
	private function skip_before_wc_reads_fee_separator() {
		if ( version_compare( WC_VERSION, '11.3.0-dev', '<' ) ) {
			$this->markTestSkipped( 'WooCommerce reads fee attributes with the store decimal separator from 11.3.' );
		}
	}

	/**
	 * Quotes the shipping total for a zone flat rate with the given cost formula on a $1000 cart,
	 * in whichever currency use_currency() has selected.
	 *
	 * @param string $cost_formula  The flat-rate `cost` option.
	 * @param float  $product_price The store-currency price of the single cart product.
	 * @return float
	 */
	private function shipping_total_for( string $cost_formula, float $product_price = 1000 ): float {
		$product = WC_Helper_Product::create_simple_product();
		$product->set_regular_price( (string) $product_price );
		$product->set_price( (string) $product_price );
		$product->set_tax_status( 'none' );
		$product->set_virtual( false );
		$product->save();

		// The zone-based flat rate fires woocommerce_evaluate_shipping_cost_args; the legacy global method does not.
		$zone        = WC_Shipping_Zones::get_zone( 0 );
		$instance_id = $zone->add_shipping_method( 'flat_rate' );
		$zone->save();
		$method = WC_Shipping_Zones::get_shipping_method( $instance_id );
		update_option(
			$method->get_instance_option_key(),
			[
				'title'      => 'Flat rate',
				'tax_status' => 'none',
				'cost'       => $cost_formula,
			],
			'yes'
		);
		WC_Cache_Helper::get_transient_version( 'shipping', true );

		WC()->customer->set_shipping_country( 'US' );
		WC()->customer->set_shipping_state( 'NY' );
		WC()->customer->set_shipping_postcode( '10001' );
		WC()->customer->set_billing_country( 'US' );
		WC()->customer->save();

		WC()->cart->add_to_cart( $product->get_id(), 1 );
		WC()->session->set( 'chosen_shipping_methods', [ 'flat_rate:' . $instance_id ] );
		WC()->cart->calculate_totals();

		return (float) WC()->cart->get_shipping_total();
	}

	/**
	 * 10% of a $1000 cart is 80.00 GBP, not 64.00 (80 * 0.8 double-converted).
	 */
	public function test_percentage_fee_is_converted_once() {
		$this->use_currency( Currency_Code::POUND_STERLING, 0.8 );
		$this->assertSame( 80.0, $this->shipping_total_for( '[fee percent="10"]' ) );
	}

	/**
	 * Fixed $10 plus 5% of $1000 both convert once: (10 + 50) * 0.8 = 48.00 GBP, not 40.00.
	 */
	public function test_mixed_fixed_and_percentage_fee_is_converted_once() {
		$this->use_currency( Currency_Code::POUND_STERLING, 0.8 );
		$this->assertSame( 48.0, $this->shipping_total_for( '10 + [fee percent="5"]' ) );
	}

	/**
	 * The [cost] placeholder (cart total) converts once: get_price(1000) = 800.00 GBP, not 640.00.
	 */
	public function test_cost_placeholder_is_converted_once() {
		$this->use_currency( Currency_Code::POUND_STERLING, 0.8 );
		$this->assertSame( 800.0, $this->shipping_total_for( '[cost]' ) );
	}

	/**
	 * max_fee is compared in the store currency: $100 capped to $90, then converted to 72.00 GBP, not 64.00.
	 */
	public function test_max_fee_is_compared_in_store_currency() {
		$this->use_currency( Currency_Code::POUND_STERLING, 0.8 );
		$this->assertSame( 72.0, $this->shipping_total_for( '[fee percent="10" max_fee="90"]' ) );
	}

	/**
	 * min_fee is compared in the store currency: $50 stays above the $45 floor, then 50 * 0.8 = 40.00 GBP.
	 * Double conversion would compare the GBP fee (40) against 45, clamp up, and quote 36.00.
	 */
	public function test_min_fee_is_compared_in_store_currency() {
		$this->use_currency( Currency_Code::POUND_STERLING, 0.8 );
		$this->assertSame( 40.0, $this->shipping_total_for( '[fee percent="5" min_fee="45"]' ) );
	}

	/**
	 * Weaker selected currency (rate > 1) must not overcharge: 10% of $1000 = $100, 100 * 1.5 = 150.00 AUD,
	 * not the double-converted 225.00.
	 */
	public function test_percentage_fee_not_overcharged_in_weaker_currency() {
		$this->use_currency( Currency_Code::AUSTRALIAN_DOLLAR, 1.5 );
		$this->assertSame( 150.0, $this->shipping_total_for( '[fee percent="10"]' ) );
	}

	/**
	 * A fee typed in the store's number format is read with the store's decimal separator, not the selected
	 * currency's: 2.5% of $1000 is $25, 20.00 GBP. Reading "2,5" with GBP's dot gives 2% and 16.00 GBP.
	 */
	public function test_fee_percent_is_read_with_store_decimal_separator() {
		$this->skip_before_wc_reads_fee_separator();
		update_option( 'woocommerce_price_decimal_sep', ',' );
		update_option( 'woocommerce_price_thousand_sep', '.' );
		$this->use_currency( Currency_Code::POUND_STERLING, 0.8 );
		$this->assertSame( 20.0, $this->shipping_total_for( '[fee percent="2,5"]' ) );
	}

	/**
	 * max_fee is read with the store's decimal separator: 10% of $225 is $22.50, under the $22.90 cap, so
	 * 18.00 GBP. Read with GBP's dot, "22,9" caps the fee at $22.90 and quotes 19.00, on PHP 7.4 (which
	 * compares the cap as 22) and PHP 8 (which compares it as a string) alike.
	 */
	public function test_max_fee_is_read_with_store_decimal_separator() {
		$this->skip_before_wc_reads_fee_separator();
		update_option( 'woocommerce_price_decimal_sep', ',' );
		update_option( 'woocommerce_price_thousand_sep', '.' );
		$this->use_currency( Currency_Code::POUND_STERLING, 0.8 );
		$this->assertSame( 18.0, $this->shipping_total_for( '[fee percent="10" max_fee="22,9"]', 225 ) );
	}

	/**
	 * A flat-rate subclass that evaluates a formula without adding a rate must not leave the store
	 * currency's decimals on for the rest of the request.
	 */
	public function test_evaluating_without_adding_a_rate_keeps_selected_currency_decimals() {
		$this->use_currency( Currency_Code::JAPANESE_YEN, 150 );
		$method = new class() extends WC_Shipping_Flat_Rate {
			public function evaluate( string $formula, float $cost ) {
				return $this->evaluate_cost(
					$formula,
					[
						'qty'  => 1,
						'cost' => $cost,
					]
				);
			}
		};

		$method->evaluate( '[fee percent="10"]', 15000 );

		$this->assertSame( 0, wc_get_price_decimals() );
	}

	/**
	 * A plugin that short-circuits [fee] must not switch the rest of the cart calculation
	 * to the store currency's decimals. Its $10 is 1,500 JPY and the total 151,500 JPY. With two decimals
	 * switched on after the item totals are taken, WooCommerce divides those items by 100 and quotes 3,000.
	 */
	public function test_short_circuited_fee_keeps_cart_total_in_selected_currency() {
		$this->use_currency( Currency_Code::JAPANESE_YEN, 150 );
		$this->assertSame( 15000.0, $this->shipping_total_for( '[fee percent="10"]' ) );

		add_filter(
			'pre_do_shortcode_tag',
			function ( $output, $tag ) {
				return 'fee' === $tag ? '10' : $output;
			},
			PHP_INT_MAX,
			2
		);
		WC()->session->__unset( 'shipping_for_package_0' );
		WC()->cart->calculate_totals();

		$this->assertSame( 1500.0, (float) WC()->cart->get_shipping_total() );
		$this->assertSame( 151500.0, (float) WC()->cart->get_total( 'edit' ) );
		$this->assertSame( 0, wc_get_price_decimals() );
	}

	/**
	 * A flat-rate subclass that renders [fee] without the cost filter, such as one overriding evaluate_cost(),
	 * keeps its cost in the selected currency. After another formula was converted, its fee must still round
	 * to JPY's decimals: 10% of 3,497 JPY is 350, not 349.7.
	 */
	public function test_fee_rendered_without_the_cost_filter_keeps_selected_currency_decimals() {
		if ( version_compare( WC_VERSION, '10.6.0-dev', '<' ) ) {
			$this->markTestSkipped( 'WooCommerce rounds the fee to wc_get_price_decimals() from 10.6 (woocommerce/woocommerce#62722).' );
		}

		$this->use_currency( Currency_Code::JAPANESE_YEN, 150 );
		$this->assertSame( 15000.0, $this->shipping_total_for( '[fee percent="10"]' ) );
		$method = new class() extends WC_Shipping_Flat_Rate {
			protected function evaluate_cost( $sum, $args = [] ) {
				$this->fee_cost = $args['cost'];
				add_shortcode( 'fee', [ $this, 'fee' ] );
				$sum = do_shortcode( $sum );
				remove_shortcode( 'fee' );
				return $sum;
			}

			public function evaluate( string $formula, float $cost ) {
				return $this->evaluate_cost(
					$formula,
					[
						'qty'  => 1,
						'cost' => $cost,
					]
				);
			}
		};

		$this->assertSame( '350', $method->evaluate( '[fee percent="10"]', 3497 ) );
	}

	/**
	 * A fee() override that throws skips the end of its [fee] shortcode. If its caller catches the exception,
	 * the next cart calculation must still take the item totals at JPY's decimals: 150,000 JPY of items plus
	 * 15,000 JPY of shipping. With the store's two decimals left on, the item totals come out 100 times off.
	 * A plugin that sets item prices when the calculation starts must also see JPY's decimal separator, not
	 * the store's comma.
	 */
	public function test_fee_that_throws_does_not_leave_store_currency_decimals_for_the_next_cart_calculation() {
		update_option( 'woocommerce_price_decimal_sep', ',' );
		update_option( 'woocommerce_price_thousand_sep', '.' );
		$this->use_currency( Currency_Code::JAPANESE_YEN, 150 );
		$this->assertSame( 15000.0, $this->shipping_total_for( '[fee percent="10"]' ) );
		$method = new class() extends WC_Shipping_Flat_Rate {
			public function fee( $atts ) {
				shortcode_atts( [ 'percent' => '' ], $atts, 'fee' );
				throw new Exception( 'The fee could not be worked out.' );
			}

			public function evaluate( string $formula, float $cost ) {
				return $this->evaluate_cost(
					$formula,
					[
						'qty'  => 1,
						'cost' => $cost,
					]
				);
			}
		};

		try {
			$method->evaluate( '[fee percent="10"]', 15000 );
		} catch ( Exception $e ) {
			unset( $e );
		}
		remove_shortcode( 'fee' );
		$separator_seen_by_pricing_plugins = null;
		add_action(
			'woocommerce_before_calculate_totals',
			function () use ( &$separator_seen_by_pricing_plugins ) {
				$separator_seen_by_pricing_plugins = wc_get_price_decimal_separator();
			}
		);
		WC()->session->__unset( 'shipping_for_package_0' );
		WC()->cart->calculate_totals();

		$this->assertSame( 165000.0, (float) WC()->cart->get_total( 'edit' ) );
		$this->assertSame( '.', $separator_seen_by_pricing_plugins );
	}

	/**
	 * A manual rate left on the store currency (set before it became the store currency) must not
	 * skew the conversion: shipping matches get_price(), which treats the store currency as 1.
	 */
	public function test_stale_manual_rate_on_store_currency_is_ignored() {
		update_option( 'wcpay_multi_currency_exchange_rate_usd', 'manual' );
		update_option( 'wcpay_multi_currency_manual_rate_usd', 0.5 );
		$this->use_currency( Currency_Code::POUND_STERLING, 0.8 );
		$this->assertSame( 80.0, $this->shipping_total_for( '[fee percent="10"]' ) );
	}
}
