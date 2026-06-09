<?php
/**
 * Class Experiment
 *
 * @package WooCommerce\Payments
 */

namespace WCPay\Internal\Experiment;

use WCPay\Experimental_Abtest;
use WCPay\Internal\Proxy\LegacyProxy;

/**
 * Base class for ExPlat experiments.
 *
 * Owns the plumbing every experiment needs: tracking-consent gating,
 * the ExPlat call via Experimental_Abtest (which caches in a transient
 * and never throws), and collapsing unknown variations to control.
 * Subclasses declare only what is unique to their experiment.
 */
abstract class Experiment {
	/**
	 * The variation every failure path collapses to.
	 *
	 * @var string
	 */
	public const VARIATION_CONTROL = 'control';

	/**
	 * Legacy proxy for calling WP/legacy code.
	 *
	 * @var LegacyProxy
	 */
	protected $legacy_proxy;

	/**
	 * Constructor.
	 *
	 * @param LegacyProxy $legacy_proxy Legacy proxy.
	 */
	public function __construct( LegacyProxy $legacy_proxy ) {
		$this->legacy_proxy = $legacy_proxy;
	}

	/**
	 * The ExPlat experiment slug. Must match the ExPlat registration.
	 *
	 * @return string
	 */
	abstract public function name(): string;

	/**
	 * Identifies the experiment unit (passed to ExPlat as the anon_id).
	 * Return an empty string when no stable identity exists; the caller
	 * then receives control.
	 *
	 * @return string
	 */
	abstract protected function assignment_key(): string;

	/**
	 * Exhaustive list of valid variation strings, including control.
	 *
	 * @return string[]
	 */
	abstract protected function variations(): array;

	/**
	 * Resolve the variant for the current request. Never throws.
	 *
	 * @return string One of variations(); control on no consent, no
	 *                assignment key, ExPlat failure, or unknown variation.
	 */
	public function get_variant(): string {
		$assignment_key = $this->assignment_key();

		if ( '' === $assignment_key || ! $this->has_consent() ) {
			return self::VARIATION_CONTROL;
		}

		$variation = $this->create_abtest( $assignment_key )->get_variation( $this->name() );

		if ( ! in_array( $variation, $this->variations(), true ) ) {
			return self::VARIATION_CONTROL;
		}

		return $variation;
	}

	/**
	 * Whether the store has consented to tracking.
	 *
	 * @return bool
	 */
	protected function has_consent(): bool {
		return 'yes' === $this->legacy_proxy->call_function( 'get_option', 'woocommerce_allow_tracking' );
	}

	/**
	 * Build the ExPlat client. Consent is passed as true because
	 * has_consent() already gated above. Overridable in tests.
	 *
	 * @param string $anon_id The assignment key.
	 * @return Experimental_Abtest
	 */
	protected function create_abtest( string $anon_id ): Experimental_Abtest {
		return new Experimental_Abtest( $anon_id, 'woocommerce', true );
	}
}
