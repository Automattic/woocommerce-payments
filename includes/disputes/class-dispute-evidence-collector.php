<?php
/**
 * Class WCPay\Disputes\Dispute_Evidence_Collector.
 *
 * @package WooCommerce Payments
 */

namespace WCPay\Disputes;

use WC_Order;
use WC_Payments_API_Client;
use WC_Payments_Order_Service;

defined( 'ABSPATH' ) || exit;

/**
 * Collects a normalized evidence-context DTO for a dispute, intended as the
 * payload sent to Transact-Platform in the `evidence_context` parameter when
 * drafting an AI-generated dispute defense.
 *
 * PII minimization is load-bearing: the DTO never includes card PAN, CVC,
 * expiry, fingerprint, or any other card-auth data. Only the fields explicitly
 * shaped by the private `shape_*` helpers are returned — the raw dispute blob
 * from the API client is never passed through.
 */
class Dispute_Evidence_Collector {

	/**
	 * Max prior orders returned in customer_history, to keep the prompt bounded.
	 */
	private const PRIOR_ORDERS_LIMIT = 10;

	/**
	 * API client used to fetch the enriched dispute payload.
	 *
	 * @var WC_Payments_API_Client
	 */
	private $api_client;

	/**
	 * Order service, held for future order-meta reads.
	 *
	 * @var WC_Payments_Order_Service
	 */
	private $order_service;

	/**
	 * Constructor.
	 *
	 * @param WC_Payments_API_Client    $api_client    API client used to fetch the enriched dispute.
	 * @param WC_Payments_Order_Service $order_service Order service (kept for future order-meta reads).
	 */
	public function __construct( WC_Payments_API_Client $api_client, WC_Payments_Order_Service $order_service ) {
		$this->api_client    = $api_client;
		$this->order_service = $order_service;
	}

	/**
	 * Builds the evidence-context DTO for a dispute.
	 *
	 * @param string $dispute_id Stripe dispute ID.
	 * @return array{
	 *     dispute: array<string, mixed>,
	 *     charge: array<string, mixed>,
	 *     order: array<string, mixed>|null,
	 *     radar: array<string, mixed>,
	 *     customer_history: array<int, array<string, mixed>>,
	 * }
	 */
	public function collect( string $dispute_id ): array {
		$dispute = $this->api_client->get_dispute( $dispute_id );
		if ( ! is_array( $dispute ) ) {
			$dispute = [];
		}

		$charge = is_array( $dispute['charge'] ?? null ) ? $dispute['charge'] : [];
		$order  = $this->load_order_from_dispute( $dispute );

		return [
			'dispute'          => $this->shape_dispute( $dispute ),
			'charge'           => $this->shape_charge( $charge ),
			'order'            => $order ? $this->shape_order( $order ) : null,
			'radar'            => $this->shape_radar( $charge ),
			'customer_history' => $order ? $this->prior_orders_for( $order ) : [],
		];
	}

	/**
	 * Shapes the dispute-level fields.
	 *
	 * @param array $dispute Raw dispute payload.
	 * @return array<string, mixed>
	 */
	private function shape_dispute( array $dispute ): array {
		return [
			'id'       => $dispute['id'] ?? '',
			'reason'   => $dispute['reason'] ?? '',
			'amount'   => $dispute['amount'] ?? 0,
			'currency' => $dispute['currency'] ?? '',
			'due_by'   => $dispute['evidence_details']['due_by'] ?? null,
			'status'   => $dispute['status'] ?? '',
		];
	}

	/**
	 * Shapes the charge-level fields.
	 *
	 * Card fields are intentionally limited to `brand`, `last4`, and `funding`;
	 * PAN, CVC, expiry, fingerprint, and any other card-auth data are never
	 * extracted from the raw payload.
	 *
	 * @param array $charge Raw charge payload.
	 * @return array<string, mixed>
	 */
	private function shape_charge( array $charge ): array {
		$card = $charge['payment_method_details']['card'] ?? [];

		return [
			'id'              => $charge['id'] ?? '',
			'amount'          => $charge['amount'] ?? 0,
			'currency'        => $charge['currency'] ?? '',
			'created'         => $charge['created'] ?? null,
			'billing_details' => $this->shape_address( is_array( $charge['billing_details'] ?? null ) ? $charge['billing_details'] : [] ),
			'shipping'        => $this->shape_address( is_array( $charge['shipping'] ?? null ) ? $charge['shipping'] : [] ),
			'card'            => [
				'brand'   => $card['brand'] ?? '',
				'last4'   => $card['last4'] ?? '',
				'funding' => $card['funding'] ?? '',
			],
		];
	}

	/**
	 * Shapes the Radar/risk-outcome fields.
	 *
	 * @param array $charge Raw charge payload.
	 * @return array<string, mixed>
	 */
	private function shape_radar( array $charge ): array {
		$outcome = is_array( $charge['outcome'] ?? null ) ? $charge['outcome'] : [];

		return [
			'risk_level'     => $outcome['risk_level'] ?? '',
			'risk_score'     => $outcome['risk_score'] ?? null,
			'rule_triggered' => $outcome['rule']['id'] ?? null,
			'network_status' => $outcome['network_status'] ?? '',
			'seller_message' => $outcome['seller_message'] ?? '',
		];
	}

	/**
	 * Shapes an address-like block (billing_details or shipping).
	 *
	 * @param array $details Raw address block from Stripe.
	 * @return array<string, mixed>
	 */
	private function shape_address( array $details ): array {
		$address = is_array( $details['address'] ?? null ) ? $details['address'] : [];

		return [
			'name'        => $details['name'] ?? '',
			'email'       => $details['email'] ?? '',
			'line1'       => $address['line1'] ?? '',
			'line2'       => $address['line2'] ?? '',
			'city'        => $address['city'] ?? '',
			'state'       => $address['state'] ?? '',
			'postal_code' => $address['postal_code'] ?? '',
			'country'     => $address['country'] ?? '',
		];
	}

	/**
	 * Shapes a WooCommerce order into a DTO fragment.
	 *
	 * @param WC_Order $order The order.
	 * @return array<string, mixed>
	 */
	private function shape_order( WC_Order $order ): array {
		$items = [];
		foreach ( $order->get_items() as $item ) {
			$items[] = [
				'name'     => $item->get_name(),
				'quantity' => $item->get_quantity(),
				'total'    => (float) $item->get_total(),
			];
		}

		$created = $order->get_date_created();

		return [
			'id'                  => $order->get_id(),
			'order_number'        => $order->get_order_number(),
			'status'              => $order->get_status(),
			'total'               => (float) $order->get_total(),
			'currency'            => $order->get_currency(),
			'created'             => $created ? $created->format( 'c' ) : null,
			'customer_email'      => $order->get_billing_email(),
			'customer_ip'         => $order->get_customer_ip_address(),
			'customer_user_agent' => $order->get_customer_user_agent(),
			'items'               => $items,
		];
	}

	/**
	 * Finds the WooCommerce order linked to the dispute, if any.
	 *
	 * WC_Payments_API_Client::get_dispute() enriches the dispute with a top-level
	 * `order` key via add_order_info_to_charge_object() / build_order_info(), so
	 * the WC order ID lives at $dispute['order']['id'] (not inside the charge).
	 *
	 * @param array $dispute Enriched dispute payload.
	 * @return WC_Order|null
	 */
	private function load_order_from_dispute( array $dispute ): ?WC_Order {
		$order_info = is_array( $dispute['order'] ?? null ) ? $dispute['order'] : [];

		$order_id = $order_info['id'] ?? null;
		if ( empty( $order_id ) ) {
			return null;
		}

		$order = wc_get_order( $order_id );

		return $order instanceof WC_Order ? $order : null;
	}

	/**
	 * Returns a lightweight summary of prior orders for the same customer,
	 * excluding the current one.
	 *
	 * @param WC_Order $order The order linked to the dispute.
	 * @return array<int, array<string, mixed>>
	 */
	private function prior_orders_for( WC_Order $order ): array {
		$email = $order->get_billing_email();
		if ( empty( $email ) ) {
			return [];
		}

		$orders = wc_get_orders(
			[
				'billing_email' => $email,
				'limit'         => self::PRIOR_ORDERS_LIMIT,
				'exclude'       => [ $order->get_id() ],
				'status'        => [ 'wc-completed', 'wc-processing', 'wc-refunded' ],
				'return'        => 'objects',
			]
		);

		$summary = [];
		foreach ( $orders as $prior ) {
			if ( ! $prior instanceof WC_Order ) {
				continue;
			}

			$created = $prior->get_date_created();

			$summary[] = [
				'id'       => $prior->get_id(),
				'status'   => $prior->get_status(),
				'total'    => (float) $prior->get_total(),
				'currency' => $prior->get_currency(),
				'created'  => $created ? $created->format( 'c' ) : null,
			];
		}

		return $summary;
	}
}
