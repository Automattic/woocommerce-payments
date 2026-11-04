<?php
/**
 * Class LoggerContextTest
 *
 * @package WooCommerce\Payments
 */

namespace WCPay\Tests;

use PHPUnit\Framework\MockObject\MockObject;
use WCPAY_UnitTestCase;
use WCPay\Internal\LoggerContext;
use WCPay\Internal\Logger;
use WC_Log_Levels;
use WCPay\Logger_Context;
use WC_Payments;

/**
 * Internal Logger Context tests.
 *
 */
class LoggerContextTest extends WCPAY_UnitTestCase {
	/**
	 * Holds the service under test, i.e. LoggerContext.
	 *
	 * @var LoggerContext&MockObject
	 */
	private $sut;

	/**
	 * Restored after each test. A /wp-json/ REQUEST_URI left over from one test
	 * makes WC()->is_rest_api_request() return true for all later tests.
	 *
	 * @var array
	 */
	private $original_server;

	public function set_up() {
		parent::set_up();

		$this->sut             = new LoggerContext();
		$this->original_server = $_SERVER;
	}

	public function tear_down() {
		$_SERVER = $this->original_server;
		wp_set_current_user( 0 );

		parent::tear_down();
	}

	/**
	 * Test that the logger context is initialized correctly.
	 *
	 * @dataProvider provider_logger_context_applied
	 * @return void
	 */
	public function test_logger_context_applied( $setup_callback ) {
		$setup_callback( $this->sut );

		$entry_time = '2021-01-01 00:00:00 UTC';
		$timestamp  = strtotime( $entry_time );
		$message    = "Test log entry...\non two lines";
		$level      = WC_Log_Levels::INFO;

		$filtered_entry = apply_filters( // phpcs:ignore WooCommerce.Commenting.CommentHooks.MissingHookComment
			'woocommerce_format_log_entry',
			$message,
			[
				'timestamp' => $timestamp,
				'level'     => $level,
				'message'   => $message,
				'context'   => [ 'source' => Logger::LOG_FILENAME ],
			]
		);

		$this->assertTrue( strpos( $filtered_entry, '2021-01-01T00:00:00+00:00 INFO' ) === 0, 'Each line starts with the timestamp and log level' );
		$this->assertTrue( preg_match( '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}\+\d{2}:\d{2} [A-Z]+ [a-f0-9]+-(\d{4}) /', $filtered_entry, $matches ) === 1, 'Each line contains timestamp, log level, request identifier and entry number' );
		$entry_number = (int) ltrim( $matches[1], '0' );
		$this->assertTrue( $entry_number > 0, 'Entry number is a positive integer' );
	}

	/**
	 * Data provider for test_logger_context_applied.
	 *
	 * @return array
	 */
	public function provider_logger_context_applied() {
		return [
			[
				function ( $instance ) {
						$instance->init_hooks();
						$instance->set_value( 'foo', 'bar' );
				},
			],
			[
				function ( $_unused_instance ) {
					wcpay_get_container()->get( LoggerContext::class )->init_hooks();
					Logger_Context::set_value( 'foo', 'bar' );
				},
			],
			[
				function ( $instance ) {
						$instance->init_hooks();
						$instance->set_value( 'foo', 'bar' );
						// Set a value to be removed.
						$instance->set_value( 'baz', 'qux' );
						$instance->set_value( 'baz', null );
				},
			],
			[
				function ( $instance ) {
						$instance->init_hooks();
						$instance->init_hooks();
						$instance->set_value( 'foo', 'bar' );
				},
			],
		];
	}

	/**
	 * Test that logger filters are not active for other log sources.
	 *
	 * @return void
	 */
	public function test_logger_context_not_applied() {
		$this->sut->init_hooks();

		$entry_time = '2021-01-01 00:00:00 UTC';
		$timestamp  = strtotime( $entry_time );
		$message    = "Test log entry...\non two lines";
		$level      = WC_Log_Levels::INFO;

		$this->sut->set_value( 'foo', 'bar' );

		$filtered_entry = apply_filters( // phpcs:ignore WooCommerce.Commenting.CommentHooks.MissingHookComment
			'woocommerce_format_log_entry',
			$message,
			[
				'timestamp' => $timestamp,
				'level'     => $level,
				'message'   => $message,
				'context'   => [],
			]
		);

		$this->assertSame( $message, $filtered_entry, 'Filtered entry is the same as the original message' );

		$filtered_entry = apply_filters( // phpcs:ignore WooCommerce.Commenting.CommentHooks.MissingHookComment
			'woocommerce_format_log_entry',
			$message,
			[
				'timestamp' => $timestamp,
				'level'     => $level,
				'message'   => $message,
			]
		);

		$this->assertSame( $message, $filtered_entry, 'Filtered entry is the same as the original message' );
	}

	/**
	 * Test that the logger context is initialized correctly.
	 *
	 * @return void
	 */
	public function test_logger_context_init() {
		WC_Payments::mode()->test();

		$context = $this->sut->get_context();

		$this->assertSame( 'test', $context['WOOPAYMENTS_MODE'] );
		$this->assertTrue( isset( $context['WP_USER'] ) );
		$this->assertTrue( isset( $context['HTTP_REFERER'] ) );
		$this->assertTrue( isset( $context['HTTP_USER_AGENT'] ) );
		$this->assertTrue( isset( $context['REQUEST_URI'] ) );
		$this->assertTrue( isset( $context['DOING_AJAX'] ) );
		$this->assertTrue( isset( $context['DOING_CRON'] ) );
		$this->assertTrue( isset( $context['WP_CLI'] ) );
	}

	public function test_logger_context_logs_user_id_instead_of_login() {
		$user_id = self::factory()->user->create( [ 'user_login' => 'jane.doe' ] );
		wp_set_current_user( $user_id );

		$context = $this->sut->get_context();

		$this->assertSame( '#' . $user_id, $context['WP_USER'] );
	}

	/**
	 * @dataProvider provider_logger_context_redacts_url_query
	 */
	public function test_logger_context_redacts_url_query( string $url, string $expected ) {
		$_SERVER['REQUEST_URI']  = $url;
		$_SERVER['HTTP_REFERER'] = $url;

		$context = $this->sut->get_context();

		$this->assertSame( $expected, $context['REQUEST_URI'] );
		$this->assertSame( $expected, $context['HTTP_REFERER'] );
	}

	/**
	 * @dataProvider provider_logger_context_redacts_malformed_url_query
	 */
	public function test_logger_context_redacts_malformed_url_query( string $url, string $expected, string $secret ) {
		$_SERVER['REQUEST_URI']  = $url;
		$_SERVER['HTTP_REFERER'] = $url;

		$context = $this->sut->get_context();

		$this->assertSame( $expected, $context['REQUEST_URI'] );
		$this->assertSame( $expected, $context['HTTP_REFERER'] );
		$this->assertStringNotContainsString( $secret, $context['REQUEST_URI'] );
		$this->assertStringNotContainsString( $secret, $context['HTTP_REFERER'] );
	}

	public function provider_logger_context_redacts_malformed_url_query(): array {
		return [
			'raw less-than in secret'      => [
				'/wp-json/wc/v3/x?consumer_secret=cs_abc<def456',
				'/wp-json/wc/v3/x?consumer_secret=(redacted)',
				'def456',
			],
			'encoded equals in bare query' => [
				'/?consumer_secret%3Dcs_abc',
				'/?(redacted)',
				'cs_abc',
			],
			'double-encoded bare query'    => [
				'/?consumer_secret%253Dcs_abc',
				'/?(redacted)',
				'cs_abc',
			],
			'nested query in allowed path' => [
				'/?path=/payments?consumer_secret=cs_nested',
				'/?path=(redacted)',
				'cs_nested',
			],
			'encoded nested query'         => [
				'/?path=%2Fpayments%3Fconsumer_secret%3Dcs_nested',
				'/?path=(redacted)',
				'cs_nested',
			],
			'double-encoded nested query'  => [
				'/?path=%252Fpayments%253Fconsumer_secret%253Dcs_nested',
				'/?path=(redacted)',
				'cs_nested',
			],
			'encoded query separator'      => [
				'/?action=checkout%26consumer_secret%3Dcs_nested',
				'/?action=(redacted)',
				'cs_nested',
			],
			'fragment in allowed value'    => [
				'/?path=/payments#cs_fragment',
				'/?path=(redacted)',
				'cs_fragment',
			],
		];
	}

	public function test_logger_context_url_query_allowlist_can_be_extended() {
		$filter = function ( $keys_to_keep ) {
			$keys_to_keep[] = 'custom_route';
			return $keys_to_keep;
		};
		add_filter( 'wcpay_logger_context_url_query_keys_to_keep', $filter );

		try {
			$_SERVER['REQUEST_URI']  = '/?action=checkout&custom_route=payments&consumer_secret=cs_456';
			$_SERVER['HTTP_REFERER'] = $_SERVER['REQUEST_URI'];

			$context = $this->sut->get_context();

			$this->assertSame( '/?action=checkout&custom_route=payments&consumer_secret=(redacted)', $context['REQUEST_URI'] );
			$this->assertSame( '/?action=checkout&custom_route=payments&consumer_secret=(redacted)', $context['HTTP_REFERER'] );
		} finally {
			remove_filter( 'wcpay_logger_context_url_query_keys_to_keep', $filter );
		}
	}

	/**
	 * @dataProvider provider_logger_context_url_query_allowlist_filter
	 */
	public function test_logger_context_url_query_allowlist_filter( $keys_to_keep, string $expected ) {
		$filter = function () use ( $keys_to_keep ) {
			return $keys_to_keep;
		};
		add_filter( 'wcpay_logger_context_url_query_keys_to_keep', $filter );

		try {
			$_SERVER['REQUEST_URI']  = '/?action=checkout&custom_route=payments&consumer_secret=cs_456';
			$_SERVER['HTTP_REFERER'] = $_SERVER['REQUEST_URI'];

			$context = $this->sut->get_context();

			$this->assertSame( $expected, $context['REQUEST_URI'] );
			$this->assertSame( $expected, $context['HTTP_REFERER'] );
		} finally {
			remove_filter( 'wcpay_logger_context_url_query_keys_to_keep', $filter );
		}
	}

	public function provider_logger_context_url_query_allowlist_filter(): array {
		return [
			'replace default keys' => [
				[ 'custom_route' ],
				'/?action=(redacted)&custom_route=payments&consumer_secret=(redacted)',
			],
			'empty allowlist'      => [
				[],
				'/?action=(redacted)&custom_route=(redacted)&consumer_secret=(redacted)',
			],
			'invalid null'         => [
				null,
				'/?action=checkout&custom_route=(redacted)&consumer_secret=(redacted)',
			],
			'invalid boolean'      => [
				false,
				'/?action=checkout&custom_route=(redacted)&consumer_secret=(redacted)',
			],
			'invalid string'       => [
				'consumer_secret',
				'/?action=checkout&custom_route=(redacted)&consumer_secret=(redacted)',
			],
			'invalid integer'      => [
				1,
				'/?action=checkout&custom_route=(redacted)&consumer_secret=(redacted)',
			],
			'non-string entries'   => [
				[ true, 0, null, [ 'consumer_secret' ] ],
				'/?action=(redacted)&custom_route=(redacted)&consumer_secret=(redacted)',
			],
		];
	}

	public function provider_logger_context_redacts_url_query(): array {
		return [
			'REST API keys'                   => [
				'/wp-json/wc/v3/orders/12/refunds?consumer_key=ck_123&consumer_secret=cs_456',
				'/wp-json/wc/v3/orders/12/refunds?consumer_key=(redacted)&consumer_secret=(redacted)',
			],
			'REST API keys, plain permalinks' => [
				'/?rest_route=/wc/v3/payments/deposits&consumer_key=ck_123&consumer_secret=cs_456',
				'/?rest_route=/wc/v3/payments/deposits&consumer_key=(redacted)&consumer_secret=(redacted)',
			],
			'redirect payment return'         => [
				'/checkout/order-received/12/?key=wc_order_abc&order_id=12&wc_payment_method=woocommerce_payments_ideal&_wpnonce=abc123&payment_intent=pi_123&payment_intent_client_secret=pi_123_secret_456&redirect_status=succeeded',
				'/checkout/order-received/12/?key=(redacted)&order_id=(redacted)&wc_payment_method=woocommerce_payments_ideal&_wpnonce=(redacted)&payment_intent=(redacted)&payment_intent_client_secret=(redacted)&redirect_status=succeeded',
			],
			'AJAX action and nonce'           => [
				'/wp-admin/admin-ajax.php?action=create_setup_intent&_ajax_nonce=abc123',
				'/wp-admin/admin-ajax.php?action=create_setup_intent&_ajax_nonce=(redacted)',
			],
			'WC AJAX endpoint'                => [
				'/?wc-ajax=checkout',
				'/?wc-ajax=checkout',
			],
			'settings page'                   => [
				'/wp-admin/admin.php?page=wc-settings&tab=checkout&section=woocommerce_payments',
				'/wp-admin/admin.php?page=wc-settings&tab=checkout&section=woocommerce_payments',
			],
			'wc-admin page'                   => [
				'/wp-admin/admin.php?page=wc-admin&path=/payments/overview',
				'/wp-admin/admin.php?page=wc-admin&path=/payments/overview',
			],
			'encoded wc-admin path'           => [
				'/wp-admin/admin.php?page=wc-admin&path=%2Fpayments%2Foverview',
				'/wp-admin/admin.php?page=wc-admin&path=/payments/overview',
			],
			'encoded REST route'              => [
				'/?rest_route=%2Fwc%2Fv3%2Forders&consumer_secret=cs_456',
				'/?rest_route=/wc/v3/orders&consumer_secret=(redacted)',
			],
			'log viewer'                      => [
				'/wp-admin/admin.php?page=wc-status&tab=logs&view=single&file_id=woopayments-2026-11-04&_wpnonce=abc123',
				'/wp-admin/admin.php?page=wc-status&tab=logs&view=single&file_id=woopayments-2026-11-04&_wpnonce=(redacted)',
			],
			'cron request'                    => [
				'/wp-cron.php?doing_wp_cron=1793782800.123456789',
				'/wp-cron.php?doing_wp_cron=1793782800.123456789',
			],
			'REST locale'                     => [
				'/wp-json/wc/store/v1/checkout?_locale=user&key=wc_order_abc',
				'/wp-json/wc/store/v1/checkout?_locale=user&key=(redacted)',
			],
			'percent-encoded name'            => [
				'/?consumer%5Fsecret=cs_456',
				'/?consumersecret=(redacted)',
			],
			'array parameter'                 => [
				'/?foo[bar]=baz',
				'/?foo[bar]=(redacted)',
			],
			'parameter without a value'       => [
				'/?foo&consumer_secret=cs_456',
				'/?foo&consumer_secret=(redacted)',
			],
			'empty query'                     => [
				'/checkout/?',
				'/checkout/?',
			],
			'no query'                        => [
				'/checkout/',
				'/checkout/',
			],
		];
	}
}
