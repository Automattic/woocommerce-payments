<?php
/**
 * Class WC_Payments_Refunded_Event_Note
 *
 * @package WooCommerce\Payments
 */

defined( 'ABSPATH' ) || exit;

/**
 * Utility class generating detailed order note for refunded payments.
 */
class WC_Payments_Refunded_Event_Note {

	/**
	 * Captured event data.
	 *
	 * @var array
	 */
	private $refunded_event;

	/**
	 * Refund object.
	 *
	 * @var array
	 */
	private $refund;

	/**
	 * Order object.
	 *
	 * @var WC_Order
	 */
	private $order;

	/**
	 * Amount refunded.
	 *
	 * @var int
	 */
	private $amount_refunded;

	/**
	 * WC_Payments_Refunded_Event_Note constructor.
	 *
	 * @param array    $refunded_event Refunded event data.
	 * @param WC_Order $order Order object.
	 *
	 * @throws InvalidArgumentException If the event is not a refunded event.
	 */
	public function __construct( array $refunded_event, WC_Order $order ) {
		$is_refunded_event = isset( $refunded_event['type'] ) && 'charge.refunded' === $refunded_event['type'];
		if ( ! $is_refunded_event ) {
			throw new InvalidArgumentException( 'Not a refunded event type.' );
		}
		$this->refunded_event  = $refunded_event;
		$this->refund          = array_pop( $refunded_event['data']['object']['refunds']['data'] );
		$this->order           = $order;
		$this->amount_refunded = WC_Payments_Utils::interpret_stripe_amount( $this->refund['amount'], $this->refund['currency'] );
	}

	/**
	 * Generate the HTML note for the refunded event.
	 *
	 * @return string
	 */
	public function generate_html_note(): string {
		$formatted_price = WC_Payments_Explicit_Price_Formatter::get_explicit_price(
			wc_price( $this->amount_refunded, [ 'currency' => strtoupper( $this->refunded_event['data']['object']['currency'] ) ] ),
			$this->order,
		);
		$refund_id       = $this->refund['id'];
		$refund_reason   = $this->refund['reason'] ?? null;

		if ( empty( $refund_reason ) ) {
			$note = sprintf(
				WC_Payments_Utils::esc_interpolated_html(
				/* translators: %1: the refund amount, %2: WooPayments, %3: ID of the refund */
					__( 'A refund of %1$s was successfully processed using %2$s (<code>%3$s</code>).', 'woocommerce-payments' ),
					[
						'code' => '<code>',
					]
				),
				$formatted_price,
				'WooPayments',
				$refund_id
			);
		} else {
			$note = sprintf(
				WC_Payments_Utils::esc_interpolated_html(
					/* translators: %1: the successfully charged amount, %2: WooPayments, %3: reason, %4: refund id */
					__( 'A refund of %1$s was successfully processed using %2$s. Reason: %3$s. (<code>%4$s</code>)', 'woocommerce-payments' ),
					[
						'code' => '<code>',
					]
				),
				$formatted_price,
				'WooPayments',
				$refund_reason,
				$refund_id
			);
		}

		return $note;
	}
}
