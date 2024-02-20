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
	 * Refund currency.
	 *
	 * @var string
	 */
	private $refund_currency;

	/**
	 * Refund id.
	 *
	 * @var string
	 */
	private $refund_id;

	/**
	 * Refund reason.
	 *
	 * @var string
	 */
	private $refund_reason;

	/**
	 * Order object.
	 *
	 * @var WC_Order
	 */
	private $order;

	/**
	 * Amount refunded.
	 *
	 * @var float
	 */
	private $refunded_amount;

	/**
	 * WC_Payments_Refunded_Event_Note constructor.
	 *
	 * @param float    $refunded_amount Refund amount.
	 * @param string   $refunded_currency Refund currency.
	 * @param string   $refund_id Refund id.
	 * @param string   $refund_reason Refund reason.
	 * @param WC_Order $order Order object.
	 */
	public function __construct( float $refunded_amount, string $refunded_currency, string $refund_id, string $refund_reason, WC_Order $order ) {
		$this->refunded_amount    = $refunded_amount;
		$this->$refunded_currency = $refunded_currency;
		$this->refund_id          = $refund_id;
		$this->refund_reason      = $refund_reason;
		$this->order              = $order;
	}

	/**
	 * Generate the HTML note for the refunded event.
	 *
	 * @return string
	 */
	public function generate_html_note(): string {
		$formatted_price = WC_Payments_Explicit_Price_Formatter::get_explicit_price(
			wc_price( $this->refunded_amount, [ 'currency' => strtoupper( $this->refund_currency ) ] ),
			$this->order,
		);

		if ( empty( $this->refund_reason ) ) {
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
				$this->refund_id
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
				$this->refund_reason,
				$this->refund_id
			);
		}

		return $note;
	}
}
