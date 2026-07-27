<?php
/**
 * A class that interacts with Explat A/B tests.
 *
 * This class is experimental. It is a fork of the jetpack-abtest package and
 * updated for use with ExPlat. These changes are planned to be contributed
 * back to the upstream Jetpack package. If accepted, this class should then
 * be superseded by the Jetpack class using Composer.
 *
 * This class should not be used externally.
 *
 * @package WooCommerce\Payments
 * @link https://packagist.org/packages/automattic/jetpack-abtest
 */

namespace WCPay;

/**
 * This class provides an interface to the Explat A/B tests.
 *
 * @internal This class is experimental and should not be used externally due to planned breaking changes.
 */
class Experimental_Abtest {
	/**
	 * Marks a cached no-assignment. Truthy so the `! empty()` cache read hits.
	 *
	 * @var string
	 */
	private const NO_ASSIGNMENT = '__wcpay_no_assignment__';

	/**
	 * A variable to hold the tests we fetched, and their variations for the current user.
	 *
	 * @var array
	 */
	private $tests = [];

	/**
	 * ExPlat Anonymous ID.
	 *
	 * @var string
	 */
	private $anon_id = null;

	/**
	 * ExPlat Platform name.
	 *
	 * @var string
	 */
	private $platform = 'woocommerce';

	/**
	 * Whether trcking consent is given.
	 *
	 * @var bool
	 */
	private $consent = false;

	/**
	 * Constructor.
	 *
	 * @param string $anon_id  ExPlat anonymous ID.
	 * @param string $platform ExPlat platform name.
	 * @param bool   $consent  Whether tracking consent is given.
	 */
	public function __construct( string $anon_id, string $platform, bool $consent ) {
		$this->anon_id  = $anon_id;
		$this->platform = $platform;
		$this->consent  = $consent;
	}

	/**
	 * Retrieve the test variation for a provided A/B test.
	 *
	 * @param string $test_name Name of the A/B test.
	 * @return mixed A/B test variation, or null on failure.
	 */
	public function get_variation( $test_name ) {
		// Default to the control variation when users haven't consented to tracking.
		if ( ! $this->consent ) {
			return 'control';
		}

		$variation = $this->fetch_variation( $test_name );

		// If there was an error retrieving a variation, conceal the error for the consumer.
		if ( is_wp_error( $variation ) ) {
			return 'control';
		}

		return $variation;
	}

	/**
	 * Fetch and cache the test variation for a provided A/B test from WP.com.
	 *
	 * ExPlat returns a null value when the assigned variation is control or
	 * an assignment has not been set. In these instances, this method returns
	 * a value of "control".
	 *
	 * @param string $test_name Name of the A/B test.
	 * @return string|array|\WP_Error A/B test variation, or error on failure.
	 */
	protected function fetch_variation( $test_name ) {
		// Make sure test name exists.
		if ( ! $test_name ) {
			return new \WP_Error( 'test_name_not_provided', 'A/B test name has not been provided.' );
		}

		// Make sure test name is a valid one.
		if ( ! preg_match( '/^[[:alnum:]_]+$/', $test_name ) ) {
			return new \WP_Error( 'invalid_test_name', 'Invalid A/B test name.' );
		}

		// Return internal-cached test variations.
		if ( isset( $this->tests[ $test_name ] ) ) {
			return $this->tests[ $test_name ];
		}

		// Return external-cached test variations.
		$cache_key = $this->get_cache_key( $test_name );
		$cached    = get_transient( $cache_key );

		if ( self::NO_ASSIGNMENT === $cached ) {
			return $this->no_assignment_error();
		}

		if ( ! empty( $cached ) ) {
			return $cached;
		}

		// Make the request to the WP.com API.
		$response = $this->request_variation( $test_name );

		// Bail if there was an error or malformed response.
		if ( is_wp_error( $response ) || ! is_array( $response ) || ! isset( $response['body'] ) ) {
			return new \WP_Error( 'failed_to_fetch_data', 'Unable to fetch the requested data.' );
		}

		// Decode the results.
		$results = json_decode( $response['body'], true );

		// Bail if there were no results or there is no test variation returned.
		if ( ! is_array( $results ) || empty( $results['variations'] ) ) {
			// Cache it: an empty variations list is an answer, not a failure, and carries a TTL.
			if ( is_array( $results ) && $this->has_usable_ttl( $results ) ) {
				set_transient( $cache_key, self::NO_ASSIGNMENT, (int) $results['ttl'] );
			}

			return $this->no_assignment_error();
		}

		// Store the variation in our internal cache.
		$this->tests[ $test_name ] = $results['variations'][ $test_name ];

		$variation = $results['variations'][ $test_name ] ?? 'control';

		// Store the variation in our external cache.
		if ( $this->has_usable_ttl( $results ) ) {
			set_transient( $cache_key, $variation, (int) $results['ttl'] );
		}

		return $variation;
	}

	/**
	 * Build the transient key for a cached variation.
	 *
	 * Scoped to the anon-ID because the transient is a site-wide option.
	 *
	 * @param string $test_name Name of the A/B test.
	 * @return string
	 */
	protected function get_cache_key( $test_name ) {
		return 'abtest_variation_' . $test_name . '_' . md5( $this->anon_id );
	}

	/**
	 * Perform the request for a variation of a provided A/B test from WP.com.
	 *
	 * @param string $test_name Name of the A/B test.
	 * @return array|\WP_Error A/B test variation error on failure.
	 */
	protected function request_variation( $test_name ) {
		// Values are encoded here because add_query_arg() appends them as-is, and a '+' in
		// the base64 anon-ID would arrive as a space.
		$args = [
			'experiment_name'  => $test_name,
			'anon_id'          => rawurlencode( $this->anon_id ),
			'woo_country_code' => rawurlencode( (string) get_option( 'woocommerce_default_country' ) ),
		];

		$url = add_query_arg(
			$args,
			sprintf( // nosemgrep: audit.php.wp.security.xss.query-arg -- constant value is passed in to add_query_arg.
				'https://public-api.wordpress.com/wpcom/v2/experiments/0.1.0/assignments/%s',
				$this->platform
			)
		);

		$get = wp_remote_get( $url );

		return $get;
	}

	/**
	 * Whether the response TTL can be cached against.
	 *
	 * A non-numeric or sub-second TTL casts to 0, which set_transient() reads as no expiry.
	 *
	 * @param array $results Decoded ExPlat response.
	 * @return bool
	 */
	private function has_usable_ttl( array $results ): bool {
		return is_numeric( $results['ttl'] ?? null ) && (int) $results['ttl'] > 0;
	}

	/**
	 * The error returned when ExPlat has no assignment for this participant.
	 *
	 * Onboarding_Experiment_Abtest turns this into null, so cached and fresh
	 * no-assignments must stay indistinguishable.
	 *
	 * @return \WP_Error
	 */
	private function no_assignment_error() {
		return new \WP_Error( 'unexpected_data_format', 'Data was not returned in the expected format.' );
	}
}
