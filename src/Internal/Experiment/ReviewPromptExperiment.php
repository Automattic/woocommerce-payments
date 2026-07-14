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
 * Assignment is per-store (Jetpack blog ID), and variant strings must match
 * the ExPlat registration exactly.
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
	 * Store-derived assignment key so assignment is per-store, not per-admin.
	 *
	 * @return string Empty string when no Jetpack connection exists.
	 */
	protected function assignment_key(): string {
		if ( ! $this->legacy_proxy->call_function( 'class_exists', '\Jetpack_Options' ) ) {
			return '';
		}

		$blog_id = $this->legacy_proxy->call_static( '\Jetpack_Options', 'get_option', 'id' );

		if ( empty( $blog_id ) || ! is_numeric( $blog_id ) ) {
			return '';
		}

		return 'woopayments_store_' . $blog_id;
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
