<?php
/**
 * Class WC_Payments_Utils_Test
 *
 * @package WooCommerce\Payments\Tests
 */

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

	public function test_get_charge_id_from_search_term_returns_charge_id() {
		$charge_id = 'ch_test_charge';

		// Create an order with charge_id to test with.
		$order = WC_Helper_Order::create_order();
		$order->update_meta_data( '_charge_id', $charge_id );
		$order->save();

		$result = WC_Payments_Utils::get_charge_id_from_search_term( 'Order #' . $order->get_id() );
		$this->assertEquals( $charge_id, $result );
	}

	public function test_get_charge_id_from_search_term_skips_invalid_terms() {
		$result = WC_Payments_Utils::get_charge_id_from_search_term( 'invalid term' );
		$this->assertEquals( '', $result );
	}

	public function test_get_charge_id_from_search_term_handles_invalid_order() {
		$result = WC_Payments_Utils::get_charge_id_from_search_term( 'Order #897' );
		$this->assertEquals( '', $result );
	}

	public function test_map_search_orders_to_charge_ids() {
		$charge_id = 'ch_test_charge';
		// Create an order with charge_id to test with.
		$order = WC_Helper_Order::create_order();
		$order->update_meta_data( '_charge_id', $charge_id );
		$order->save();

		$result = WC_Payments_Utils::map_search_orders_to_charge_ids( "First term,Order #{$order->get_id()},Another term" );
		$this->assertEquals( "First term,$charge_id,Another term", $result );
	}
}
