<?php
/**
 * Class WC_Payments_Captured_Event_Note
 *
 * @package WooCommerce\Payments
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Utility class generating detailed captured note for successful payments.
 */
class WC_Payments_Captured_Event_Note {
	const HTML_BLACK_BULLET = '<span style="font-size: 7px;vertical-align: middle;">&#9679;</span>';
	const HTML_WHITE_BULLET = '<span style="font-size: 7px;vertical-align: middle;">&#9675;</span>';
	const HTML_SPACE        = '&nbsp;';
	const HTML_BR           = '<br>';

	/**
	 * Captured event data.
	 *
	 * @var array
	 */
	private $captured_event;

	/**
	 * Constructor.
	 *
	 * @param  array $captured_event Captured event data.
	 *
	 * @throws Exception
	 */
	public function __construct( array $captured_event ) {
		$is_captured_event = isset( $captured_event['type'] ) && 'captured' === $captured_event['type'];
		if ( ! $is_captured_event ) {
			throw new Exception( 'Not a captured event' );
		}

		$this->captured_event = $captured_event;
	}

	/**
	 * Generate the HTML note.
	 *
	 * @return string
	 */
	public function generate_html_note(): string {

		$lines = [];

		$fx_string = $this->compose_fx_string();
		if ( null !== $fx_string ) {
			$lines[] = $fx_string;
		}

		$lines[] = $this->compose_fee_string();

		$fee_breakdown_lines = $this->compose_fee_break_down();
		if ( null !== $fee_breakdown_lines ) {
			$lines = array_merge( $lines, $fee_breakdown_lines );
		}

		$lines[] = $this->compose_net_string();

		$html = '';
		foreach ( $lines as $line ) {
			$html .= '<p>' . $line . '</p>' . PHP_EOL;
		}

		return '<div class="captured-event-details">' . PHP_EOL
				. $html
				. '</div>';
	}

	/**
	 * Generate FX string.
	 *
	 * @return string|null
	 */
	public function compose_fx_string() {
		if ( ! $this->is_fx_event() ) {
			return null;
		}

		$customer_currency        = $this->captured_event['transaction_details']['customer_currency'];
		$customer_amount_captured = $this->captured_event['transaction_details']['customer_amount_captured'];
		$store_currency           = $this->captured_event['transaction_details']['store_currency'];
		$store_amount_captured    = $this->captured_event['transaction_details']['store_amount_captured'];

		return $this->format_fx( $customer_currency, $customer_amount_captured, $store_currency, $store_amount_captured );
	}

	/**
	 * Generate fee string.
	 *
	 * @return string
	 */
	public function compose_fee_string(): string {
		$data = $this->captured_event;

		$fee_rates      = $data['fee_rates'];
		$percentage     = $fee_rates['percentage'];
		$fixed_currency = $fee_rates['fixed_currency'];
		$fixed          = WC_Payments_Utils::interpret_stripe_amount( (int) $fee_rates['fixed'], $fixed_currency );
		$history        = $fee_rates['history'];

		$fee_currency = $data['transaction_details']['store_currency'];
		$fee_amount   = WC_Payments_Utils::interpret_stripe_amount( (int) $data['transaction_details']['store_fee'], $fee_currency );

		$base_fee_label = $this->is_base_fee_only()
			? __( 'Base fee', 'woocommerce-payments' )
			: __( 'Fee', 'woocommerce-payments' );

		$is_capped = isset( $history[0]['capped'] ) && true === $history[0]['capped'];

		if ( $this->is_base_fee_only() && $is_capped ) {
			return sprintf(
				'%1$s (capped at %2$s): %3$s',
				$base_fee_label,
				WC_Payments_Utils::format_currency( $fixed, $fixed_currency ),
				WC_Payments_Utils::format_currency( - $fee_amount, $fee_currency )
			);
		}
		$is_same_symbol = $this->has_same_currency_symbol( $data['transaction_details']['store_currency'], $data['transaction_details']['customer_currency'] );

		return sprintf(
			'%1$s (%2$s%% + %3$s%4$s): %5$s%6$s',
			$base_fee_label,
			self::format_fee( $percentage ),
			WC_Payments_Utils::format_currency( $fixed, $fixed_currency ),
			$is_same_symbol ? ' ' . $data['transaction_details']['customer_currency'] : '',
			WC_Payments_Utils::format_currency( -$fee_amount, $fee_currency ),
			$is_same_symbol ? " $fee_currency" : ''
		);
	}

	/**
	 * Generate an array including HTML formatted breakdown lines.
	 *
	 * @return array<string>|null
	 */
	public function compose_fee_break_down() {
		$fee_history_strings = $this->get_fee_breakdown();

		if ( null === $fee_history_strings ) {
			return null;
		}

		if ( 0 === count( $fee_history_strings ) ) {
			return null;
		}

		$res = [];
		foreach ( $fee_history_strings as $type => $fee ) {
			$res[] = self::HTML_BLACK_BULLET . ' ' . ( 'discount' === $type
					? $fee['label']
					: $fee
				);

			if ( 'discount' === $type ) {
				$res[] = str_repeat( self::HTML_SPACE . ' ', 2 ) . self::HTML_WHITE_BULLET . ' ' . $fee['variable'];
				$res[] = str_repeat( self::HTML_SPACE . ' ', 2 ) . self::HTML_WHITE_BULLET . ' ' . $fee['fixed'];
			}
		}

		return $res;
	}

	/**
	 * Generate net string.
	 *
	 * @return string
	 */
	public function compose_net_string(): string {
		$data = $this->captured_event['transaction_details'];

		// Determine the type of payment and select the appropriate amounts and currencies.
		if ( $this->is_fx_event() ) {
			// For fx events, we need the store amount and currency to display the net amount
			// in the store currency.
			$amount          = $data['store_amount'];
			$captured_amount = $data['store_amount_captured'];
			$fee             = $data['store_fee'];
			$currency        = $data['store_currency'];
		} else {
			$amount          = $data['customer_amount'];
			$captured_amount = $data['customer_amount_captured'];
			$fee             = $data['customer_fee'];
			$currency        = $data['customer_currency'];
		}

		$gross_amount = $captured_amount ?? $amount;
		$net          = WC_Payments_Utils::interpret_stripe_amount( (int) ( $gross_amount - $fee ), $currency );

		// Format and return the net string.
		return sprintf(
			/* translators: %s is a monetary amount */
			__( 'Net payout: %s', 'woocommerce-payments' ),
			WC_Payments_Utils::format_explicit_currency( $net, $currency )
		);
	}

	/**
	 * Returns an associative array containing fee breakdown.
	 * Keys are fee types such as base, additional-fx, etc, except for "discount" that is an associative array including more discount details.
	 *
	 * @return array|null
	 */
	public function get_fee_breakdown() {
		$data = $this->captured_event;

		if ( ! isset( $data['fee_rates']['history'] ) ) {
			return null;
		}

		$history = $data['fee_rates']['history'];

		// Hide breakdown when there's only a base fee.
		if ( $this->is_base_fee_only() ) {
			return null;
		}

		$fee_history_strings = [];

		foreach ( $history as $fee ) {
			$label_type = $fee['type'];
			if ( $fee['additional_type'] ?? '' ) {
				$label_type .= '-' . $fee['additional_type'];
			}

			$percentage_rate = (float) $fee['percentage_rate'];
			$fixed_rate      = (int) $fee['fixed_rate'];
			$currency        = strtoupper( $fee['currency'] );
			$is_capped       = isset( $fee['capped'] ) && true === $fee['capped'];

			$percentage_rate_formatted = self::format_fee( $percentage_rate );
			$fix_rate_formatted        = WC_Payments_Utils::format_currency(
				WC_Payments_Utils::interpret_stripe_amount( $fixed_rate ),
				$currency
			);

			if ( $this->has_same_currency_symbol( $data['transaction_details']['customer_currency'], $data['transaction_details']['store_currency'] ) ) {
				$fix_rate_formatted = $fix_rate_formatted . ' ' . $data['transaction_details']['store_currency'];
			}

			$label = sprintf(
				$this->fee_label_mapping( $fixed_rate, $is_capped )[ $label_type ],
				$percentage_rate_formatted,
				$fix_rate_formatted
			);

			if ( 'discount' === $label_type ) {
				$fee_history_strings[ $label_type ] = [
					'label'    => $label,
					'variable' => sprintf(
						/* translators: %s is a percentage number */
						__( 'Variable fee: %s', 'woocommerce-payments' ),
						$percentage_rate_formatted
					) . '%',
					'fixed'    => sprintf(
						/* translators: %s is a monetary amount */
						__( 'Fixed fee: %s', 'woocommerce-payments' ),
						$fix_rate_formatted
					),
				];
			} else {
				$fee_history_strings[ $label_type ] = $label;
			}
		}

		return $fee_history_strings;
	}

	/**
	 * Check if this is a FX event.
	 *
	 * @return bool
	 */
	private function is_fx_event(): bool {
		$customer_currency = $this->captured_event['transaction_details']['customer_currency'] ?? null;
		$store_currency    = $this->captured_event['transaction_details']['store_currency'] ?? null;

		return ! (
			is_null( $customer_currency )
			|| is_null( $store_currency )
			|| $customer_currency === $store_currency
		);
	}

	/**
	 * Return a boolean indicating whether only fee applied is the base fee.
	 *
	 * @return bool True if the only applied fee is the base fee
	 */
	private function is_base_fee_only(): bool {
		if ( ! isset( $this->captured_event['fee_rates']['history'] ) ) {
			return false;
		}

		$history = $this->captured_event['fee_rates']['history'];

		return 1 === ( is_countable( $history ) ? count( $history ) : 0 ) && 'base' === $history[0]['type'];
	}

	/**
	 * Get the mapping format for all types of fees.
	 *
	 * @param  int  $fixed_rate Fixed rate amount in Stripe format.
	 * @param  bool $is_capped True if the fee is capped.
	 *
	 * @return array An associative array with keys are fee types, values are string formats.
	 */
	private function fee_label_mapping( int $fixed_rate, bool $is_capped ) {
		$res = [];

		$res['base'] = $is_capped
			/* translators: %2$s is the capped fee */
			? __( 'Base fee: capped at %2$s', 'woocommerce-payments' )
			:
			( 0 !== $fixed_rate
				/* translators: %1$s% is the fee percentage and %2$s is the fixed rate */
				? __( 'Base fee: %1$s%% + %2$s', 'woocommerce-payments' )
				/* translators: %1$s% is the fee percentage */
				: __( 'Base fee: %1$s%%', 'woocommerce-payments' )
			);

		$res['additional-international'] = 0 !== $fixed_rate
			/* translators: %1$s% is the fee percentage and %2$s is the fixed rate */
			? __( 'International card fee: %1$s%% + %2$s', 'woocommerce-payments' )
			/* translators: %1$s% is the fee percentage */
			: __( 'International card fee: %1$s%%', 'woocommerce-payments' );

		$res['additional-fx'] = 0 !== $fixed_rate
			/* translators: %1$s% is the fee percentage and %2$s is the fixed rate */
			? __( 'Currency conversion fee: %1$s%% + %2$s', 'woocommerce-payments' )
			/* translators: %1$s% is the fee percentage */
			: __( 'Currency conversion fee: %1$s%%', 'woocommerce-payments' );

		$res['additional-wcpay-subscription'] = 0 !== $fixed_rate
			/* translators: %1$s% is the fee percentage and %2$s is the fixed rate */
			? __( 'Subscription transaction fee: %1$s%% + %2$s', 'woocommerce-payments' )
			/* translators: %1$s% is the fee percentage */
			: __( 'Subscription transaction fee: %1$s%%', 'woocommerce-payments' );

		$res['discount'] = __( 'Discount', 'woocommerce-payments' );

		return $res;
	}

	/**
	 * Return a given decimal fee as a percentage with a maximum of 3 decimal places.
	 *
	 * @param  float $percentage Percentage as float.
	 *
	 * @return string
	 */
	private function format_fee( float $percentage ): string {
		return (string) round( $percentage * 100, 3 );
	}

	/**
	 * Format FX string based on the two provided currencies.
	 *
	 * @param  string $from_currency 3-letter code for original currency.
	 * @param  int    $from_amount Amount (Stripe-type) for original currency.
	 * @param  string $to_currency 3-letter code for converted currency.
	 * @param  int    $to_amount Amount (Stripe-type) for converted currency.
	 *
	 * @return string Formatted FX string.
	 */
	private function format_fx(
		string $from_currency,
		int $from_amount,
		string $to_currency,
		int $to_amount
	): string {

		$exchange_rate = (float) ( 0 !== $from_amount
			? $to_amount / $from_amount
			: 0 );

		if ( WC_Payments_Utils::is_zero_decimal_currency( strtolower( $to_currency ) ) ) {
			$exchange_rate *= 100;
		}

		if ( WC_Payments_Utils::is_zero_decimal_currency( strtolower( $from_currency ) ) ) {
			$exchange_rate /= 100;
		}

		$to_display_amount = WC_Payments_Utils::interpret_stripe_amount( $to_amount, $to_currency );

		return sprintf(
			'%1$s → %2$s: %3$s',
			self::format_explicit_currency_with_base( 1, $from_currency, $to_currency, true ),
			self::format_exchange_rate( $exchange_rate, $to_currency ),
			WC_Payments_Utils::format_explicit_currency( $to_display_amount, $to_currency, false )
		);
	}

	/**
	 * Format exchange rate.
	 *
	 * @param  float  $rate Exchange rate.
	 * @param  string $currency 3-letter currency code.
	 *
	 * @return string
	 */
	private function format_exchange_rate( float $rate, string $currency ): string {
		$num_decimals = $rate > 1 ? 5 : 6;
		$formatted    = WC_Payments_Utils::format_explicit_currency(
			$rate,
			$currency,
			true,
			[ 'decimals' => $num_decimals ]
		);

		$func_remove_ending_zeros = function ( $str ) {
			return rtrim( $str, '0' );
		};

		// Remove ending zeroes after the decimal separator if they exist.
		return implode(
			' ',
			array_map(
				$func_remove_ending_zeros,
				explode( ' ', $formatted )
			)
		);
	}

	/**
	 * Format amount for a given currency but according to the base currency's format.
	 *
	 * @param  float  $amount Amount.
	 * @param  string $currency 3-letter currency code.
	 * @param  string $base_currency 3-letter base currency code.
	 * @param  bool   $skip_symbol Optional. If true, trims off the short currency symbol. Default false.
	 *
	 * @return string
	 */
	private function format_explicit_currency_with_base( float $amount, string $currency, string $base_currency, bool $skip_symbol = false ) {
		$custom_format = WC_Payments_Utils::get_currency_format_for_wc_price( $base_currency );
		unset( $custom_format['currency'] );

		// Given this is used to display the $amount, the decimals for $base_currency shouldn't interfere with decimals for $currency.
		$custom_format['decimals'] = WC_Payments_Utils::get_currency_format_for_wc_price( $currency )['decimals'];

		return WC_Payments_Utils::format_explicit_currency( $amount, $currency, $skip_symbol, $custom_format );
	}

	/**
	 * Compare does two currencies have the same symbol.
	 *
	 * @param string $base_currency Base currency.
	 * @param string $currency Currency to compare.
	 *
	 * @return bool
	 */
	private function has_same_currency_symbol( string $base_currency, string $currency ): bool {
		return strcasecmp( $base_currency, $currency ) !== 0 && get_woocommerce_currency_symbol( $base_currency ) === get_woocommerce_currency_symbol( $currency );
	}
}
