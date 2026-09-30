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
 * @internal When the experiment ends, remove together with:
 *   - includes/class-onboarding-experiment-abtest.php (the Onboarding_Experiment_Abtest subclass)
 *     and its include_once line in class-wc-payments.php.
 *   - WC_Payments_Account::maybe_accelerate_onboarding() and the branch added to
 *     maybe_redirect_from_connect_page().
 *   - WC_Payments_Account::maybe_redirect_from_payments_settings_to_onboarding().
 *   - User meta cleanup (one-shot migration) for USER_META_VARIATION_KEY. Do NOT
 *     delete USER_META_ANON_ID_KEY — 'jetpack_tracks_anon_id' is shared with
 *     Jetpack and WooPay tracking.
 */
class Onboarding_Experiment {
	const EXPERIMENT_NAME         = 'woopayments_accelerated_onboarding_202604';
	const USER_META_VARIATION_KEY = '_wcpay_onboarding_experiment_variation';
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
	 * Resolve the variation for the current user, caching real ExPlat responses in user meta.
	 *
	 * The first successful call per user goes to ExPlat; subsequent calls read the cached
	 * variation so the merchant's arm stays stable. Three paths return 'control':
	 *  - ExPlat assigns 'control' (cached).
	 *  - User is outside the experiment audience — ExPlat returns null, the parent
	 *    Experimental_Abtest collapses that to 'control' (cached, so we don't re-hit ExPlat).
	 *  - Transient failure: no consent or WP_Error from the subclass (returned but NOT cached,
	 *    so a later call can still assign the user).
	 *
	 * Only known arm strings are cached; an unrecognized value from ExPlat (misconfigured
	 * experiment, renamed arm) falls through as transient control to avoid locking a merchant
	 * into a typo for the lifetime of the experiment.
	 *
	 * @return string Either 'control' or 'treatment'.
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
		if ( ! in_array( $variation, [ self::VARIATION_CONTROL, self::VARIATION_TREATMENT ], true ) ) {
			return self::VARIATION_CONTROL;
		}

		if ( $user_id ) {
			update_user_meta( $user_id, self::USER_META_VARIATION_KEY, $variation );
		}

		return $variation;
	}

	/**
	 * Lazy-build the abtest client using the merchant's anon-ID and tracking consent.
	 *
	 * ExPlat keys assignments by anon-ID, but our wrapper caches the resolved variation by
	 * user-ID (see get_variation()). The asymmetry is only on paper: get_anon_id() persists
	 * the anon-ID under user_meta on first read, so a given WP user has a stable anon-ID
	 * across browsers and sessions — both keys end up identifying the same merchant.
	 *
	 * @return Experimental_Abtest
	 */
	private function get_abtest(): Experimental_Abtest {
		if ( null === $this->abtest ) {
			$this->abtest = new Onboarding_Experiment_Abtest(
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

		update_user_meta( $user_id, self::USER_META_ANON_ID_KEY, $anon_id );
		return $anon_id;
	}
}
