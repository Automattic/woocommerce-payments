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
 * Handles consent gating, ExPlat lookup, fallback-to-control behavior, and
 * per-request memoization. Subclasses define only experiment-specific details.
 */
abstract class Experiment {
	/**
	 * The variant every failure path collapses to.
	 *
	 * @var string
	 */
	public const VARIANT_CONTROL = 'control';

	/**
	 * Legacy proxy for calling WP/legacy code.
	 *
	 * @var LegacyProxy
	 */
	protected $legacy_proxy;

	/**
	 * Memoized variant for the current request.
	 *
	 * @var string|null
	 */
	private $memoized_variant;

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
	 * Exhaustive list of valid variant strings, including control.
	 *
	 * @return string[]
	 */
	abstract protected function variants(): array;

	/**
	 * Resolve the variant for the current request.
	 *
	 * @return string One of variants(); control when assignment is unavailable or invalid.
	 */
	public function get_variant(): string {
		if ( null !== $this->memoized_variant ) {
			return $this->memoized_variant;
		}

		$this->memoized_variant = $this->resolve_variant();

		return $this->memoized_variant;
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
	 * Build the ExPlat client. Overridable in tests.
	 *
	 * @param string $anon_id The assignment key.
	 * @return Experimental_Abtest
	 */
	protected function create_abtest( string $anon_id ): Experimental_Abtest {
		return new Experimental_Abtest( $anon_id, 'woocommerce', true );
	}

	/**
	 * Uncached variant resolution.
	 *
	 * @return string
	 */
	private function resolve_variant(): string {
		$assignment_key = $this->assignment_key();

		if ( '' === $assignment_key || ! $this->has_consent() ) {
			return self::VARIANT_CONTROL;
		}

		$variant = $this->create_abtest( $assignment_key )->get_variation( $this->name() );

		if ( ! in_array( $variant, $this->variants(), true ) ) {
			return self::VARIANT_CONTROL;
		}

		return $variant;
	}
}
