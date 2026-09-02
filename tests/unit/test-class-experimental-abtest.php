<?php
/**
 * Class Experimental_Abtest_Test
 *
 * @package WooCommerce\Payments\Tests
 */

/**
 * Experimental ABTest Test class
 */
class Experimental_Abtest_Test extends WCPAY_UnitTestCase {
	public function test_get_variation_returns_control_if_consent_is_false() {
		$abtest = new \WCPay\Experimental_Abtest( '', 'woocommerce', false );

		$result = $abtest->get_variation( 'test_name' );

		$this->assertSame( $result, 'control' );
	}

	public function test_get_variation_returns_control_if_test_name_is_empty() {
		$abtest = new \WCPay\Experimental_Abtest( '', 'woocommerce', false );

		$result = $abtest->get_variation( '' );

		$this->assertSame( $result, 'control' );
	}

	public function test_get_variation_returns_control_if_test_name_is_invalid() {
		$abtest = new \WCPay\Experimental_Abtest( '', 'woocommerce', false );

		$result = $abtest->get_variation( 'invalid_test_name' );

		$this->assertSame( $result, 'control' );
	}

	public function test_cache_key_is_scoped_to_the_anon_id() {
		$first  = $this->get_cache_key( new \WCPay\Experimental_Abtest( 'woo:cJ8kL2mN', 'woocommerce', true ), 'some_test' );
		$second = $this->get_cache_key( new \WCPay\Experimental_Abtest( 'woo:pQ4rS6tU', 'woocommerce', true ), 'some_test' );

		$this->assertNotSame(
			$first,
			$second,
			'The transient is site-wide, so two admins on one store must not share a cached variation.'
		);
		$this->assertStringStartsWith( 'abtest_variation_some_test_', $first );
	}

	public function test_empty_variations_response_is_cached_so_it_is_not_re_requested() {
		$requests = $this->stub_explat_response( '{"variations":{},"assignments":{},"ttl":7200}' );
		$abtest   = new \WCPay\Experimental_Abtest( 'jetpack:anonA', 'woocommerce', true );

		$first  = $abtest->get_variation( 'some_test' );
		$second = ( new \WCPay\Experimental_Abtest( 'jetpack:anonA', 'woocommerce', true ) )->get_variation( 'some_test' );

		$this->assertSame( 'control', $first );
		$this->assertSame( 'control', $second, 'A cached no-assignment must still resolve to control.' );
		$this->assertSame( 1, $requests->count, 'ExPlat must be called once, not on every page load.' );
	}

	public function test_cached_no_assignment_is_not_mistaken_for_a_real_assignment() {
		$this->stub_explat_response( '{"variations":{},"assignments":{},"ttl":7200}' );

		// Onboarding_Experiment_Abtest returns null on WP_Error so a missing assignment
		// is never persisted. That must hold for the cached path too.
		$first  = new \WCPay\Onboarding_Experiment_Abtest( 'jetpack:anonB', 'woocommerce', true );
		$second = new \WCPay\Onboarding_Experiment_Abtest( 'jetpack:anonB', 'woocommerce', true );

		$this->assertNull( $first->get_variation( 'some_test' ) );
		$this->assertNull( $second->get_variation( 'some_test' ), 'A cached no-assignment must stay indistinguishable from a fresh one.' );
	}

	public function test_the_anon_id_reaches_explat_unmangled() {
		$captured = null;

		add_filter(
			'pre_http_request',
			function ( $pre, $args, $url ) use ( &$captured ) {
				if ( false === strpos( $url, 'experiments/0.1.0/assignments' ) ) {
					return $pre;
				}

				$captured = $url;

				return [ 'body' => '{"variations":{},"assignments":{},"ttl":7200}' ];
			},
			10,
			3
		);

		( new \WCPay\Experimental_Abtest( 'woo:aB+c/dEfGhIjKlMnOpQr', 'woocommerce', true ) )->get_variation( 'some_test' );

		$query = [];
		parse_str( (string) wp_parse_url( (string) $captured, PHP_URL_QUERY ), $query );

		$this->assertSame(
			'woo:aB+c/dEfGhIjKlMnOpQr',
			$query['anon_id'] ?? null,
			'A + must not arrive as a space, or the assignment is keyed on a different identity.'
		);
	}

	public function test_a_non_numeric_ttl_is_not_cached() {
		$requests = $this->stub_explat_response( '{"variations":{"some_test":"treatment"},"ttl":"soon"}' );

		( new \WCPay\Experimental_Abtest( 'jetpack:anonD', 'woocommerce', true ) )->get_variation( 'some_test' );
		( new \WCPay\Experimental_Abtest( 'jetpack:anonD', 'woocommerce', true ) )->get_variation( 'some_test' );

		$this->assertSame(
			2,
			$requests->count,
			'A non-numeric ttl casts to 0, which set_transient() reads as no expiry.'
		);
	}

	public function test_a_fractional_ttl_is_not_cached() {
		$requests = $this->stub_explat_response( '{"variations":{"some_test":"treatment"},"ttl":0.5}' );

		( new \WCPay\Experimental_Abtest( 'jetpack:anonE', 'woocommerce', true ) )->get_variation( 'some_test' );
		( new \WCPay\Experimental_Abtest( 'jetpack:anonE', 'woocommerce', true ) )->get_variation( 'some_test' );

		$this->assertSame(
			2,
			$requests->count,
			'A sub-second ttl casts to 0, which set_transient() reads as no expiry.'
		);
	}

	public function test_a_transport_failure_is_not_re_requested_within_the_backoff_window() {
		$requests = $this->stub_explat_response( new WP_Error( 'http_request_failed', 'Timed out.' ) );

		$first  = ( new \WCPay\Experimental_Abtest( 'jetpack:anonC', 'woocommerce', true ) )->get_variation( 'some_test' );
		$second = ( new \WCPay\Experimental_Abtest( 'jetpack:anonC', 'woocommerce', true ) )->get_variation( 'some_test' );

		$this->assertSame( 'control', $first );
		$this->assertSame( 'control', $second );
		$this->assertSame( 1, $requests->count, 'An outage must not add a blocking request to every page load.' );
	}

	public function test_a_transport_failure_backs_off_briefly_not_for_the_assignment_ttl() {
		$this->stub_explat_response( new WP_Error( 'http_request_failed', 'Timed out.' ) );
		$abtest = new \WCPay\Experimental_Abtest( 'jetpack:anonF', 'woocommerce', true );

		$abtest->get_variation( 'some_test' );

		$timeout = (int) get_option( '_transient_timeout_' . $this->get_cache_key( $abtest, 'some_test' ) );
		$this->assertGreaterThan( time(), $timeout );
		$this->assertLessThanOrEqual(
			time() + 60,
			$timeout,
			'A failure must expire quickly so recovery is picked up, not pin the merchant for hours.'
		);
	}

	public function test_a_cached_transport_failure_is_not_mistaken_for_an_answer() {
		$this->stub_explat_response( new WP_Error( 'http_request_failed', 'Timed out.' ) );

		// Onboarding_Experiment_Abtest persists assignments, so a cached failure
		// must stay an error for it, exactly like a fresh one.
		$first  = new \WCPay\Onboarding_Experiment_Abtest( 'jetpack:anonG', 'woocommerce', true );
		$second = new \WCPay\Onboarding_Experiment_Abtest( 'jetpack:anonG', 'woocommerce', true );

		$this->assertNull( $first->get_variation( 'some_test' ) );
		$this->assertNull( $second->get_variation( 'some_test' ), 'A cached failure must stay indistinguishable from a fresh one.' );
	}

	public function test_an_undecodable_body_is_treated_as_a_failed_request() {
		$requests = $this->stub_explat_response( '<html>Too many requests</html>' );

		( new \WCPay\Experimental_Abtest( 'jetpack:anonH', 'woocommerce', true ) )->get_variation( 'some_test' );
		( new \WCPay\Experimental_Abtest( 'jetpack:anonH', 'woocommerce', true ) )->get_variation( 'some_test' );

		$this->assertSame( 1, $requests->count, 'A rate-limiter page is an outage, not an answer, and must back off the same way.' );
	}

	public function test_the_explat_request_shortens_the_default_timeout() {
		$captured = null;

		add_filter(
			'pre_http_request',
			function ( $pre, $args, $url ) use ( &$captured ) {
				if ( false === strpos( $url, 'experiments/0.1.0/assignments' ) ) {
					return $pre;
				}

				$captured = $args;

				return [ 'body' => '{"variations":{},"assignments":{},"ttl":7200}' ];
			},
			10,
			3
		);

		( new \WCPay\Experimental_Abtest( 'jetpack:anonI', 'woocommerce', true ) )->get_variation( 'some_test' );

		$this->assertSame(
			3,
			$captured['timeout'] ?? null,
			'The request blocks admin page render, so it must not wait the default 5 seconds.'
		);
	}

	public function test_the_explat_request_does_not_use_the_default_wordpress_user_agent() {
		$captured = null;

		add_filter(
			'pre_http_request',
			function ( $pre, $args, $url ) use ( &$captured ) {
				if ( false === strpos( $url, 'experiments/0.1.0/assignments' ) ) {
					return $pre;
				}

				$captured = $args;

				return [ 'body' => '{"variations":{},"assignments":{},"ttl":7200}' ];
			},
			10,
			3
		);

		( new \WCPay\Experimental_Abtest( 'jetpack:anonJ', 'woocommerce', true ) )->get_variation( 'some_test' );

		$this->assertSame(
			'WooPayments/' . WCPAY_VERSION_NUMBER,
			$captured['user-agent'] ?? null,
			'The default WordPress user agent is bot-filtered by the ExPlat assigner, so no assignment would ever be created.'
		);
	}

	/**
	 * Short-circuit the ExPlat request with a canned response and count the calls.
	 *
	 * @param string|WP_Error $body Response body, or a WP_Error to simulate a transport failure.
	 * @return object Counter exposing a `count` property.
	 */
	private function stub_explat_response( $body ) {
		$counter = new stdClass();

		$counter->count = 0;

		add_filter(
			'pre_http_request',
			function ( $pre, $args, $url ) use ( $body, $counter ) {
				if ( false === strpos( $url, 'experiments/0.1.0/assignments' ) ) {
					return $pre;
				}

				++$counter->count;

				return is_wp_error( $body ) ? $body : [ 'body' => $body ];
			},
			10,
			3
		);

		return $counter;
	}

	/**
	 * Invoke the protected get_cache_key() on an abtest instance.
	 *
	 * @param \WCPay\Experimental_Abtest $abtest    The abtest instance.
	 * @param string                     $test_name Name of the A/B test.
	 * @return string
	 */
	private function get_cache_key( \WCPay\Experimental_Abtest $abtest, string $test_name ): string {
		$method = new ReflectionMethod( \WCPay\Experimental_Abtest::class, 'get_cache_key' );
		$method->setAccessible( true );

		return $method->invoke( $abtest, $test_name );
	}
}
