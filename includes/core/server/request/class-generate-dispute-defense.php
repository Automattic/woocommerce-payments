<?php
/**
 * Class file for WCPay\Core\Server\Request\Generate_Dispute_Defense.
 *
 * @package WooCommerce Payments
 */

namespace WCPay\Core\Server\Request;

use WCPay\Core\Exceptions\Server\Request\Invalid_Request_Parameter_Exception;
use WCPay\Core\Server\Request;
use WC_Payments_API_Client;

/**
 * Request class for generating AI-assisted dispute defense drafts.
 */
class Generate_Dispute_Defense extends Request {

	const REQUIRED_PARAMS = [ 'dispute_id', 'reason', 'evidence_context' ];

	const SUPPORTED_REASON = 'fraudulent';

	/**
	 * Specifies the WordPress hook name that will be triggered upon calling the send() method.
	 *
	 * @var string
	 */
	protected $hook = 'wcpay_generate_dispute_defense_request';

	/**
	 * Returns the request's API.
	 *
	 * @return string
	 */
	public function get_api(): string {
		return WC_Payments_API_Client::DISPUTE_DEFENSE_API;
	}

	/**
	 * Returns the request's HTTP method.
	 *
	 * @return string
	 */
	public function get_method(): string {
		return 'POST';
	}

	/**
	 * Dispute ID setter.
	 *
	 * @param string $dispute_id Stripe dispute ID (must start with `dp_`).
	 *
	 * @return void
	 * @throws Invalid_Request_Parameter_Exception If the ID does not match the Stripe dispute format.
	 */
	public function set_dispute_id( string $dispute_id ) {
		$this->validate_stripe_id( $dispute_id, 'dp' );
		$this->set_param( 'dispute_id', $dispute_id );
	}

	/**
	 * Dispute reason setter.
	 *
	 * Only the `fraudulent` reason is supported by the AI draft flow.
	 *
	 * @param string $reason Stripe dispute reason.
	 *
	 * @return void
	 * @throws Invalid_Request_Parameter_Exception If the reason is not supported.
	 */
	public function set_reason( string $reason ) {
		if ( self::SUPPORTED_REASON !== $reason ) {
			throw new Invalid_Request_Parameter_Exception(
				esc_html__( 'Only fraudulent disputes are supported by the AI dispute defender.', 'woocommerce-payments' ),
				'wcpay_dispute_defender_unsupported_reason'
			);
		}
		$this->set_param( 'reason', $reason );
	}

	/**
	 * Evidence context setter.
	 *
	 * @param array $context Structured evidence context collected by Dispute_Evidence_Collector.
	 *
	 * @return void
	 */
	public function set_evidence_context( array $context ) {
		$this->set_param( 'evidence_context', $context );
	}
}
