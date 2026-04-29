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
 *   - The `wcpay-skip-accelerated-onboarding=1` param appended in
 *     client/onboarding/index.tsx handleExit() and in
 *     client/onboarding/steps/embedded-kyc.tsx (handleOnExit failure/catch
 *     branches and the loadError Cancel-button URL).
 *   - User meta cleanup (one-shot migration) for USER_META_VARIATION_KEY and
 *     USER_META_BYPASS_KEY. Do NOT delete USER_META_ANON_ID_KEY —
 *     'jetpack_tracks_anon_id' is shared with Jetpack and WooPay tracking.
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
	 * Whether the current user already has a variation cached in user meta.
	 *
	 * Lets callers distinguish first exposure (fire a Tracks event) from subsequent reads
	 * (stay quiet). Does not mutate state.
	 *
	 * @return bool
	 */
	public function has_assigned_variation(): bool {
		$user_id = get_current_user_id();
		if ( ! $user_id ) {
			return false;
		}

		$cached = get_user_meta( $user_id, self::USER_META_VARIATION_KEY, true );
		return is_string( $cached ) && '' !== $cached;
	}

	/**
	 * Resolve the variation for the current user, caching real ExPlat responses in user meta.
	 *
	 * The first successful call per user goes to ExPlat; subsequent calls read the cached
	 * variation so the merchant's arm stays stable. When ExPlat is unreachable we return
	 * 'control' transiently without caching, so a later call can still assign the user.
	 *
	 * @return string Either 'control' or 'treatment'. Transient 'control' on ExPlat failure.
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
			return self::VARIATION_CONTROL;
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
