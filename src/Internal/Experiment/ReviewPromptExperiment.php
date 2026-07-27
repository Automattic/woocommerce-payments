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
 * Assignment is per admin user, because the Tracks anon-ID belongs to a user. Variant
 * strings must match the ExPlat registration exactly.
 */
final class ReviewPromptExperiment extends Experiment {
	/**
	 * ExPlat experiment slug (platform: woocommerce).
	 *
	 * @var string
	 */
	public const EXPERIMENT_NAME = 'woopayments_review_prompt_design_v1';

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
	 * The merchant's Tracks anon-ID.
	 *
	 * ExPlat joins assignments to Tracks events on identity, so this uses the same
	 * get_identity() that stamps the events. It persists a new anon-ID to user meta.
	 *
	 * @return string Empty string when no anon-ID can be resolved.
	 */
	protected function assignment_key(): string {
		$user_id = $this->legacy_proxy->call_function( 'get_current_user_id' );

		if ( ! $user_id ) {
			return '';
		}

		if ( ! $this->legacy_proxy->call_function( 'class_exists', '\WC_Tracks_Client' ) ) {
			return '';
		}

		$identity = $this->legacy_proxy->call_static( '\WC_Tracks_Client', 'get_identity', $user_id );

		// ExPlat keys on the anon-ID, so a wpcom:user_id identity has nothing to join on.
		if ( ! is_array( $identity ) || 'anon' !== ( $identity['_ut'] ?? '' ) ) {
			return '';
		}

		$anon_id = $identity['_ui'] ?? '';

		return is_string( $anon_id ) ? $anon_id : '';
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
