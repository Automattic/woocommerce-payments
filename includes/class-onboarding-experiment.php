<?php
/**
 * Wrapper around Experimental_Abtest for the accelerated-onboarding experiment.
 *
 * Encapsulates variation fetching, per-user caching, and anon-ID resolution so
 * experiment logic lives in one place and can be removed cleanly when the test
 * concludes.
 *
 * @package WooCommerce\Payments
 */

namespace WCPay;

defined( 'ABSPATH' ) || exit;

/**
 * Accelerated-onboarding experiment wrapper.
 *
 * @internal Remove together with the branch in WC_Payments_Account::maybe_redirect_from_connect_page() when the experiment ends.
 */
class Onboarding_Experiment {
	const EXPERIMENT_NAME         = 'woopayments_accelerated_onboarding_202604';
	const USER_META_VARIATION_KEY = '_wcpay_onboarding_experiment_variation';
	const USER_META_BYPASS_KEY    = '_wcpay_onboarding_experiment_bypass';
	const USER_META_ANON_ID_KEY   = 'jetpack_tracks_anon_id';
	const VARIATION_CONTROL       = 'control';
	const VARIATION_TREATMENT     = 'treatment';

	/**
	 * Injected abtest client. When null, constructed lazily from the current request's identity.
	 *
	 * @var Experimental_Abtest|null
	 */
	private $abtest;

	/**
	 * Constructor.
	 *
	 * @param Experimental_Abtest|null $abtest Optional pre-built abtest client, useful for tests.
	 */
	public function __construct( ?Experimental_Abtest $abtest = null ) {
		$this->abtest = $abtest;
	}

	/**
	 * Resolve the variation for the current user, caching the result in user meta.
	 *
	 * The first call per user goes to ExPlat; subsequent calls read the cached variation
	 * so the merchant's arm stays stable even if ExPlat is temporarily unreachable.
	 *
	 * @return string Either 'control' or 'treatment'. Defaults to 'control' on any failure.
	 */
	public function get_variation(): string {
		$user_id = get_current_user_id();

		if ( $user_id ) {
			$cached = get_user_meta( $user_id, self::USER_META_VARIATION_KEY, true );
			if ( is_string( $cached ) && '' !== $cached ) {
				return $cached;
			}
		}

		$variation = $this->get_abtest()->get_variation( self::EXPERIMENT_NAME );
		if ( ! is_string( $variation ) || '' === $variation ) {
			$variation = self::VARIATION_CONTROL;
		}

		if ( $user_id ) {
			update_user_meta( $user_id, self::USER_META_VARIATION_KEY, $variation );
		}

		return $variation;
	}

	/**
	 * Whether the current user has opted out of the experiment via the bypass flag.
	 *
	 * @return bool
	 */
	public function has_bypass(): bool {
		$user_id = get_current_user_id();
		if ( ! $user_id ) {
			return false;
		}

		return (bool) get_user_meta( $user_id, self::USER_META_BYPASS_KEY, true );
	}

	/**
	 * Persist the bypass flag for the current user so subsequent qualifying visits
	 * don't re-route into the treatment arm.
	 */
	public function set_bypass(): void {
		$user_id = get_current_user_id();
		if ( ! $user_id ) {
			return;
		}

		update_user_meta( $user_id, self::USER_META_BYPASS_KEY, 1 );
	}

	/**
	 * Lazy-build the abtest client using the merchant's anon-ID and tracking consent.
	 *
	 * @return Experimental_Abtest
	 */
	private function get_abtest(): Experimental_Abtest {
		if ( null === $this->abtest ) {
			$this->abtest = new Experimental_Abtest(
				$this->get_anon_id(),
				'woocommerce',
				'yes' === get_option( 'woocommerce_allow_tracking' )
			);
		}

		return $this->abtest;
	}

	/**
	 * Resolve the Jetpack Tracks anon-ID for the current user, persisting a newly-generated
	 * one to user meta so exposure keys match other Woo track events.
	 *
	 * Mirrors the pattern in WCPay\WooPay_Tracker::tracks_get_identity().
	 *
	 * @return string Anon-ID, or empty string if one cannot be resolved.
	 */
	private function get_anon_id(): string {
		$user_id = get_current_user_id();
		if ( ! $user_id ) {
			return '';
		}

		$anon_id = get_user_meta( $user_id, self::USER_META_ANON_ID_KEY, true );
		if ( is_string( $anon_id ) && '' !== $anon_id ) {
			return $anon_id;
		}

		if ( ! class_exists( '\Jetpack_Tracks_Client' ) ) {
			return '';
		}

		$anon_id = \Jetpack_Tracks_Client::get_anon_id();
		if ( ! is_string( $anon_id ) || '' === $anon_id ) {
			return '';
		}

		add_user_meta( $user_id, self::USER_META_ANON_ID_KEY, $anon_id, false );
		return $anon_id;
	}
}
