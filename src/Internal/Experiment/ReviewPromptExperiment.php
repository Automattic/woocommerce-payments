<?php
/**
 * Class ReviewPromptExperiment
 *
 * @package WooCommerce\Payments
 */

namespace WCPay\Internal\Experiment;

/**
 * A/B/C experiment for the in-app review prompt designs (WOOPMNT-6080).
 *
 * Assignment is per-merchant (Jetpack Tracks anon-ID), and variant strings must
 * match the ExPlat registration exactly.
 */
final class ReviewPromptExperiment extends Experiment {
	/**
	 * ExPlat experiment slug (platform: woocommerce).
	 *
	 * @var string
	 */
	public const EXPERIMENT_NAME = 'woopayments_review_prompt_design_v1';

	/**
	 * User meta key holding the Jetpack Tracks anon-ID. Shared with Jetpack, never delete it.
	 *
	 * @var string
	 */
	public const USER_META_ANON_ID_KEY = 'jetpack_tracks_anon_id';

	/**
	 * Variant B.
	 *
	 * @var string
	 */
	public const VARIANT_TREATMENT_ILLUSTRATION = 'treatment_illustration';

	/**
	 * Variant C.
	 *
	 * @var string
	 */
	public const VARIANT_TREATMENT_REVISED = 'treatment_revised';

	/**
	 * The ExPlat experiment slug.
	 *
	 * @return string
	 */
	public function name(): string {
		return self::EXPERIMENT_NAME;
	}

	/**
	 * The merchant's Jetpack Tracks anon-ID.
	 *
	 * ExPlat joins assignments to Tracks events on identity, so this has to match what
	 * Tracks stamps on the events. Mirrors Onboarding_Experiment::get_anon_id().
	 *
	 * @return string Empty string when no anon-ID can be resolved.
	 */
	protected function assignment_key(): string {
		$user_id = $this->legacy_proxy->call_function( 'get_current_user_id' );

		if ( ! $user_id ) {
			return '';
		}

		$anon_id = $this->legacy_proxy->call_function( 'get_user_meta', $user_id, self::USER_META_ANON_ID_KEY, true );

		if ( is_string( $anon_id ) && '' !== $anon_id ) {
			return $anon_id;
		}

		if ( ! $this->legacy_proxy->call_function( 'class_exists', '\Jetpack_Tracks_Client' ) ) {
			return '';
		}

		$anon_id = $this->legacy_proxy->call_static( '\Jetpack_Tracks_Client', 'get_anon_id' );

		if ( ! is_string( $anon_id ) || '' === $anon_id ) {
			return '';
		}

		$this->legacy_proxy->call_function( 'update_user_meta', $user_id, self::USER_META_ANON_ID_KEY, $anon_id );

		return $anon_id;
	}

	/**
	 * Valid variants, matching the ExPlat registration.
	 *
	 * @return string[]
	 */
	protected function variants(): array {
		return [
			self::VARIANT_CONTROL,
			self::VARIANT_TREATMENT_ILLUSTRATION,
			self::VARIANT_TREATMENT_REVISED,
		];
	}
}
