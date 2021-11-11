<?php
/**
 * Class WC_Payments_Utils_Test
 *
 * @package WooCommerce\Payments\Tests
 */

use PHPUnit\Framework\MockObject\MockObject;
use WCPay\Exceptions\Amount_Too_Small_Exception;

/**
 * WC_Payments_Utils unit tests.
 */
class WC_Payments_Utils_Test extends WP_UnitTestCase {
	public function test_esc_interpolated_html_returns_raw_string() {
		$result = WC_Payments_Utils::esc_interpolated_html(
			'hello world',
			[
				'span' => '<span/>',
			]
		);
		$this->assertEquals( 'hello world', $result );
	}

	public function test_esc_interpolated_html_allows_self_closing_tag_without_attrs() {
		$result = WC_Payments_Utils::esc_interpolated_html(
			'line 1<br/>line 2',
			[
				'br' => '<br>',
			]
		);
		$this->assertEquals( 'line 1<br/>line 2', $result );
	}

	public function test_esc_interpolated_html_allows_self_closing_tag_with_attrs() {
		$result = WC_Payments_Utils::esc_interpolated_html(
			'this is an image: <img/>.',
			[
				'img' => '<img src="#"/>',
			]
		);
		$this->assertEquals( 'this is an image: <img src="#"/>.', $result );
	}

	public function test_esc_interpolated_html_allows_opening_and_closing_tag_without_attrs() {
		$result = WC_Payments_Utils::esc_interpolated_html(
			'here is a <strong>text</strong>: hello',
			[
				'strong' => '<strong>',
			]
		);
		$this->assertEquals( 'here is a <strong>text</strong>: hello', $result );
	}

	public function test_esc_interpolated_html_allows_opening_and_closing_tag_with_attrs() {
		$result = WC_Payments_Utils::esc_interpolated_html(
			'click <a>here</a> for a link',
			[
				'a' => '<a href="#"/>',
			]
		);
		$this->assertEquals( 'click <a href="#">here</a> for a link', $result );
	}

	public function test_esc_interpolated_html_allows_custom_map_keys() {
		$result = WC_Payments_Utils::esc_interpolated_html(
			'click <foo>here</foo> for a link',
			[
				'foo' => '<a href="abc.def/hello"/>',
			]
		);
		$this->assertEquals( 'click <a href="abc.def/hello">here</a> for a link', $result );
	}

	public function test_esc_interpolated_html_allows_tag_at_the_beginning_of_string() {
		$result = WC_Payments_Utils::esc_interpolated_html(
			'<strong>qwerty</strong>uiop',
			[
				'strong' => '<strong/>',
			]
		);
		$this->assertEquals( '<strong>qwerty</strong>uiop', $result );
	}

	public function test_esc_interpolated_html_allows_tag_at_the_end_of_string() {
		$result = WC_Payments_Utils::esc_interpolated_html(
			'qwerty<strong>uiop</strong>',
			[
				'strong' => '<strong/>',
			]
		);
		$this->assertEquals( 'qwerty<strong>uiop</strong>', $result );
	}

	public function test_esc_interpolated_html_allows_tag_at_the_beginning_and_end_of_string() {
		$result = WC_Payments_Utils::esc_interpolated_html(
			'<strong>qwertyuiop</strong>',
			[
				'strong' => '<strong/>',
			]
		);
		$this->assertEquals( '<strong>qwertyuiop</strong>', $result );
	}

	public function test_esc_interpolated_html_allows_multiple_tags() {
		$result = WC_Payments_Utils::esc_interpolated_html(
			'this is <strong>bold text</strong>, this is <a>a link</a>, this is an image <img/>.',
			[
				'strong' => '<strong/>',
				'a'      => '<a href="#">',
				'img'    => '<img src="#">',
			]
		);
		$this->assertEquals( 'this is <strong>bold text</strong>, this is <a href="#">a link</a>, this is an image <img src="#"/>.', $result );
	}

	public function test_esc_interpolated_html_escapes_unrecognized_tags() {
		$result = WC_Payments_Utils::esc_interpolated_html(
			'<strong>hello world</strong>',
			[
				'span' => '<span/>',
			]
		);
		$this->assertEquals( '&lt;strong&gt;hello world&lt;/strong&gt;', $result );
	}

	public function test_esc_interpolated_html_escapes_unrecognized_tags_but_allows_defined_tags() {
		$result = WC_Payments_Utils::esc_interpolated_html(
			'this is <strong>bold text</strong>, <span>this should not be here</span>, this is <a>a link</a>, this is an image <img/>.',
			[
				'strong' => '<strong/>',
				'a'      => '<a href="#">',
				'img'    => '<img src="#">',
			]
		);
		$this->assertEquals( 'this is <strong>bold text</strong>, &lt;span&gt;this should not be here&lt;/span&gt;, this is <a href="#">a link</a>, this is an image <img src="#"/>.', $result );
	}

	public function test_esc_interpolated_html_does_not_escape_sprintf_placeholders() {
		$result = WC_Payments_Utils::esc_interpolated_html(
			'A payment of %1$s was <strong>authorized</strong> using WooCommerce Payments (<code>%2$s</code>).',
			[
				'strong' => '<strong/>',
				'code'   => '<code>',
			]
		);
		$this->assertEquals( 'A payment of %1$s was <strong>authorized</strong> using WooCommerce Payments (<code>%2$s</code>).', $result );
	}

	public function test_esc_interpolated_html_handles_nested_tags() {
		$result = WC_Payments_Utils::esc_interpolated_html(
			'Hello <strong>there, <em>John Doe</em> <img/></strong>',
			[
				'strong' => '<strong/>',
				'em'     => '<em>',
				'img'    => '<img src="test"/>',
			]
		);
		$this->assertEquals( 'Hello <strong>there, <em>John Doe</em> <img src="test"/></strong>', $result );
	}

	public function test_get_charge_ids_from_search_term_order_returns_charge_id() {
		$charge_id = 'ch_test_charge';

		// Create an order with charge_id to test with.
		$order = WC_Helper_Order::create_order();
		$order->update_meta_data( '_charge_id', $charge_id );
		$order->save();

		$result = WC_Payments_Utils::get_charge_ids_from_search_term( 'Order #' . $order->get_id() );
		$this->assertEquals( [ $charge_id ], $result );
	}

	public function test_get_charge_ids_from_search_term_subscription_returns_charge_ids() {
		$charge_ids = [ 'ch_test_charge_1', 'ch_test_charge_2' ];

		WC_Subscriptions::set_wcs_get_subscription(
			function ( $id ) use ( $charge_ids ) {
				$subscription = new WC_Subscription();

				$order1 = WC_Helper_Order::create_order();
				$order1->update_meta_data( '_charge_id', $charge_ids[0] );
				$order1->save();
				$order2 = WC_Helper_Order::create_order();
				$order2->update_meta_data( '_charge_id', $charge_ids[1] );
				$order2->save();
				$subscription->set_related_orders( [ $order1, $order2 ] );

				return $subscription;
			}
		);

		$result = WC_Payments_Utils::get_charge_ids_from_search_term( 'Subscription #123' );
		$this->assertEquals( $charge_ids, $result );
	}

	public function test_get_charge_ids_from_search_term_skips_invalid_terms() {
		$result = WC_Payments_Utils::get_charge_ids_from_search_term( 'invalid term' );
		$this->assertEquals( [], $result );
	}

	public function test_get_charge_ids_from_search_term_handles_invalid_order() {
		$result = WC_Payments_Utils::get_charge_ids_from_search_term( 'Order #897' );
		$this->assertEquals( [], $result );
	}

	public function test_get_charge_ids_from_search_term_handles_invalid_subscription() {
		WC_Subscriptions::set_wcs_get_subscription(
			function ( $id ) {
				return false;
			}
		);
		$result = WC_Payments_Utils::get_charge_ids_from_search_term( 'Subscription #897' );
		$this->assertEquals( [], $result );
	}

	public function test_map_search_orders_to_charge_ids() {
		$charge_id = 'ch_test_charge';
		// Create an order with charge_id to test with.
		$order = WC_Helper_Order::create_order();
		$order->update_meta_data( '_charge_id', $charge_id );
		$order->save();

		$result = WC_Payments_Utils::map_search_orders_to_charge_ids( [ 'First term', "Order #{$order->get_id()}", 'Another term' ] );
		$this->assertEquals( [ 'First term', $charge_id, 'Another term' ], $result );
	}

	public function test_map_search_orders_to_charge_ids_subscription_term() {
		$charge_ids = [ 'ch_test_charge_1', 'ch_test_charge_2' ];

		WC_Subscriptions::set_wcs_get_subscription(
			function ( $id ) use ( $charge_ids ) {
				$subscription = new WC_Subscription();

				$order1 = WC_Helper_Order::create_order();
				$order1->update_meta_data( '_charge_id', $charge_ids[0] );
				$order1->save();
				$order2 = WC_Helper_Order::create_order();
				$order2->update_meta_data( '_charge_id', $charge_ids[1] );
				$order2->save();
				$subscription->set_related_orders( [ $order1, $order2 ] );

				return $subscription;
			}
		);

		$result = WC_Payments_Utils::map_search_orders_to_charge_ids( [ 'First term', 'Subscription #123', 'Another term' ] );
		$this->assertEquals( [ 'First term', $charge_ids[0], $charge_ids[1], 'Another term' ], $result );
	}

	public function test_redact_array_redacts() {
		$array          = [
			'nice_key1' => 123,
			'nice_key2' => [
				'nested_nice_key' => 456,
				'nested_bad_key'  => 'hello',
			],
			'bad_key1'  => 'test',
			'bad_key2'  => [
				'nested_key' => 'foo',
			],
		];
		$keys_to_redact = [ 'nested_bad_key', 'bad_key1', 'bad_key2' ];

		$expected = [
			'nice_key1' => 123,
			'nice_key2' => [
				'nested_nice_key' => 456,
				'nested_bad_key'  => '(redacted)',
			],
			'bad_key1'  => '(redacted)',
			'bad_key2'  => '(redacted)',
		];

		$result = WC_Payments_Utils::redact_array( $array, $keys_to_redact );

		$this->assertEquals( $expected, $result );
	}

	public function test_redact_array_handles_recursion() {
		$array              = [
			'test' => 'test',
		];
		$array['recursive'] = &$array;

		$result = WC_Payments_Utils::redact_array( $array, [] );

		$node = $result;
		for ( $i = 0; $i < WC_Payments_Utils::MAX_ARRAY_DEPTH; $i++ ) {
			$node = $node['recursive'];
		}

		$this->assertEquals(
			'(recursion limit reached)',
			$node
		);
	}

	public function test_redact_array_handles_non_arrays() {
		$array = [
			'object'  => new stdClass(),
			'integer' => 123,
			'float'   => 1.23,
			'string'  => 'test',
			'boolean' => true,
			'null'    => null,
		];

		$expected = [
			'object'  => 'stdClass()',
			'integer' => 123,
			'float'   => 1.23,
			'string'  => 'test',
			'boolean' => true,
			'null'    => null,
		];

		$result = WC_Payments_Utils::redact_array( $array, [] );

		$this->assertEquals( $expected, $result );
	}

	public function test_get_order_intent_currency() {
		$order = WC_Helper_Order::create_order();

		$this->assertEquals( WC_Payments_Utils::get_order_intent_currency( $order ), $order->get_currency() );

		WC_Payments_Utils::set_order_intent_currency( $order, 'EUR' );
		$this->assertEquals( WC_Payments_Utils::get_order_intent_currency( $order ), 'EUR' );
	}

	public function test_prepare_amount() {
		$this->assertEquals( 24500, WC_Payments_Utils::prepare_amount( 245 ) );
		$this->assertEquals( 10000, WC_Payments_Utils::prepare_amount( 100, 'USD' ) );
		$this->assertEquals( 100, WC_Payments_Utils::prepare_amount( 100, 'JPY' ) );
		$this->assertEquals( 500, WC_Payments_Utils::prepare_amount( 500, 'jpy' ) );

	}

	public function test_interpret_stripe_amount() {
		$this->assertEquals( 1, WC_Payments_Utils::interpret_stripe_amount( 100 ) );
		$this->assertEquals( 1, WC_Payments_Utils::interpret_stripe_amount( 100, 'usd' ) );
		$this->assertEquals( 1, WC_Payments_Utils::interpret_stripe_amount( 100, 'eur' ) );
		$this->assertEquals( 100, WC_Payments_Utils::interpret_stripe_amount( 100, 'jpy' ) );
		$this->assertEquals( 100, WC_Payments_Utils::interpret_stripe_amount( 100, 'bif' ) );
	}

	public function test_interpret_stripe_exchange_rate() {
		$this->assertEquals( 1.00, WC_Payments_Utils::interpret_string_exchange_rate( 1.00, 'USD', 'USD' ) );
		$this->assertEquals( 0.63, WC_Payments_Utils::interpret_string_exchange_rate( 0.63, 'USD', 'EUR' ) );
		$this->assertEquals( 0.63, WC_Payments_Utils::interpret_string_exchange_rate( 0.0063, 'USD', 'JPY' ) );
		$this->assertEquals( 0.0063, WC_Payments_Utils::interpret_string_exchange_rate( 0.63, 'JPY', 'USD' ) );
	}

	public function test_is_zero_decimal_currency() {
		$this->assertEquals( false, WC_Payments_Utils::is_zero_decimal_currency( 'usd' ) );
		$this->assertEquals( true, WC_Payments_Utils::is_zero_decimal_currency( 'jpy' ) );
	}

	public function test_it_returns_is_payment_settings_page_for_main_settings_page() {
		global $current_section, $current_tab;

		$this->set_is_admin( true );
		$current_section = 'woocommerce_payments';
		$current_tab     = 'checkout';

		$this->assertTrue( WC_Payments_Utils::is_payments_settings_page() );
	}

	public function test_it_returns_is_payment_settings_page_for_payment_method_settings_page() {
		global $current_section, $current_tab;

		$this->set_is_admin( true );
		$current_section = 'woocommerce_payments_foo';
		$current_tab     = 'checkout';

		$this->assertTrue( WC_Payments_Utils::is_payments_settings_page() );
	}

	/**
	 * @dataProvider not_payment_settings_page_conditions_provider
	 */
	public function test_it_returns_it_is_not_payment_settings_page( $is_admin, $section, $tab ) {
		global $current_section, $current_tab;

		$this->set_is_admin( $is_admin );
		$current_section = $section;
		$current_tab     = $tab;

		$this->assertFalse( WC_Payments_Utils::is_payments_settings_page() );
	}

	public function test_convert_to_stripe_locale() {
		$result = WC_Payments_Utils::convert_to_stripe_locale( 'en_GB' );
		$this->assertEquals( 'en-GB', $result );

		$result = WC_Payments_Utils::convert_to_stripe_locale( 'fr_FR' );
		$this->assertEquals( 'fr', $result );

		$result = WC_Payments_Utils::convert_to_stripe_locale( 'fr_CA' );
		$this->assertEquals( 'fr-CA', $result );

		$result = WC_Payments_Utils::convert_to_stripe_locale( 'es_UY' );
		$this->assertEquals( 'es', $result );
	}

	public function not_payment_settings_page_conditions_provider(): array {
		return [
			'is_admin() is false'                 => [ false, 'woocommerce_payments_foo', 'checkout' ],
			'section is not woocommerce_payments' => [ true, 'foo', 'checkout' ],
			'tab is not checkout'                 => [ true, 'woocommerce_payments', 'shipping' ],
		];
	}

	/**
	 * @param bool $is_admin
	 */
	private function set_is_admin( bool $is_admin ) {
		global $current_screen;

		if ( ! $is_admin ) {
			$current_screen = null;
			return;
		}

		$current_screen = $this->getMockBuilder( \stdClass::class )
			->setMethods( [ 'in_admin' ] )
			->getMock();

		$current_screen->method( 'in_admin' )->willReturn( $is_admin );
	}

	public function test_get_cached_minimum_amount_returns_amount() {
		// Note: WP stores options as strings.
		set_transient( 'wcpay_minimum_amount_usd', '500', DAY_IN_SECONDS );
		$result = WC_Payments_Utils::get_cached_minimum_amount( 'usd' );
		$this->assertSame( 500, $result );
	}

	public function test_get_cached_minimum_amount_returns_null_without_cache() {
		delete_transient( 'wcpay_minimum_amount_usd' );
		$result = WC_Payments_Utils::get_cached_minimum_amount( 'usd' );
		$this->assertNull( $result );
	}
}
