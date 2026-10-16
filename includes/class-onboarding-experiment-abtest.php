<?php
/**
 * Variant of Experimental_Abtest used by the accelerated-onboarding experiment.
 *
 * @package WooCommerce\Payments
 */

namespace WCPay;

defined( 'ABSPATH' ) || exit;

/**
 * Variant of Experimental_Abtest that returns null on failure so callers can
 * distinguish a real 'control' assignment from a transient fetch failure that
 * shouldn't be cached.
 *
 * The parent's get_variation() collapses no-consent and WP_Error fetch failures
 * into a real-looking 'control' string. That makes it impossible for the caller
 * to know whether to persist the assignment, which would otherwise lock a
 * merchant who first encounters the experiment during an ExPlat outage into
 * 'control' for the lifetime of the experiment.
 *
 * @internal Remove together with Onboarding_Experiment when the experiment ends.
 */
final class Onboarding_Experiment_Abtest extends Experimental_Abtest {
	/**
	 * Whether tracking consent was granted at construction time.
	 *
	 * Mirrors the parent's private $consent so this subclass can branch on it
	 * without modifying the parent class.
	 *
	 * @var bool
	 */
	private $is_consented;

	/**
	 * Constructor.
	 *
	 * @param string $anon_id  ExPlat anonymous ID.
	 * @param string $platform ExPlat platform name.
	 * @param bool   $consent  Whether tracking consent is given.
	 */
	public function __construct( string $anon_id, string $platform, bool $consent ) {
		parent::__construct( $anon_id, $platform, $consent );
		$this->is_consented = $consent;
	}

	/**
	 * Retrieve the test variation, or null on failure.
	 *
	 * @param string $test_name Name of the A/B test.
	 * @return string|null The variation string on success, or null when consent is missing
	 *                     or the upstream fetch errored.
	 */
	public function get_variation( $test_name ) {
		if ( ! $this->is_consented ) {
			return null;
		}

		$variation = $this->fetch_variation( $test_name );
		if ( is_wp_error( $variation ) ) {
			return null;
		}

		return $variation;
	}
}
