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
	const HTML_BLACK_BULLET = '&#9679;';
	const HTML_WHITE_BULLET = '&#9675;';
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
			$html .= self::HTML_BR . $line . PHP_EOL;
		}

		return '<div class="captured-event-details" style="line-height: 0.8;padding-top: 15px;">' . PHP_EOL
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

		$customer_currency = $this->captured_event['transaction_details']['customer_currency'];
		$customer_amount   = $this->captured_event['transaction_details']['customer_amount'];
		$store_currency    = $this->captured_event['transaction_details']['store_currency'];
		$store_amount      = $this->captured_event['transaction_details']['store_amount'];

		return $this->format_fx( $customer_currency, $customer_amount, $store_currency, $store_amount );
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
		$fixed          = WC_Payments_Utils::interpret_stripe_amount( (int) $fee_rates['fixed'] );
		$fixed_currency = $fee_rates['fixed_currency'];
		$history        = $fee_rates['history'];

		$fee_amount   = WC_Payments_Utils::interpret_stripe_amount( (int) $data['transaction_details']['store_fee'] );
		$fee_currency = $data['transaction_details']['store_currency'];

		$base_fee_label = $this->is_base_fee_only()
			? __( 'Base fee', 'woocommerce-payments' )
			: __( 'Fee', 'woocommerce-payments' );

		$is_capped = isset( $history[0]['capped'] ) && true === $history[0]['capped'];

		if ( $this->is_base_fee_only() && $is_capped ) {
			return sprintf(
				'%1$s (capped at %2$s): %3$s',
				$base_fee_label,
				self::format_currency( $fixed, $fixed_currency ),
				self::format_currency( -$fee_amount, $fee_currency )
			);
		}

		return sprintf(
			'%1$s (%2$s%% + %3$s): %4$s',
			$base_fee_label,
			self::format_fee( $percentage ),
			self::format_currency( $fixed, $fixed_currency ),
			self::format_currency( -$fee_amount, $fee_currency )
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

		$net = WC_Payments_Utils::interpret_stripe_amount( (int) $data['store_amount'] - $data['store_fee'] );

		return sprintf(
			/* translators: %s is a monetary amount */
			__( 'Net deposit: %s', 'woocommerce-payments' ),
			self::format_explicit_currency( $net, $data['store_currency'] )
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
			if ( isset( $fee['additional_type'] ) ) {
				$label_type .= '-' . $fee['additional_type'];
			}

			$percentage_rate = (float) $fee['percentage_rate'];
			$fixed_rate      = (int) $fee['fixed_rate'];
			$currency        = strtoupper( $fee['currency'] );
			$is_capped       = isset( $fee['capped'] ) && true === $fee['capped'];

			$percentage_rate_formatted = self::format_fee( $percentage_rate );
			$fix_rate_formatted        = self::format_currency(
				WC_Payments_Utils::interpret_stripe_amount( $fixed_rate ),
				$currency
			);

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

		return 1 === count( $history ) && 'base' === $history[0]['type'];
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
			? __( 'Foreign exchange fee: %1$s%% + %2$s', 'woocommerce-payments' )
			/* translators: %1$s% is the fee percentage */
			: __( 'Foreign exchange fee: %1$s%%', 'woocommerce-payments' );

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
			self::format_explicit_currency( $to_display_amount, $to_currency, false )
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
		$formatted    = self::format_explicit_currency( $rate, $currency, true, [ 'decimals' => $num_decimals ] );

		$func_remove_ending_zeros = function( $str ) {
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
		$custom_format = self::get_currency_format_for_wc_price( $base_currency );
		unset( $custom_format['currency'] );

		if ( 0 === self::get_currency_format_for_wc_price( $currency )['decimals'] ) {
			unset( $custom_format['decimals'] );
		}

		return self::format_explicit_currency( $amount, $currency, $skip_symbol, $custom_format );
	}

	/**
	 * TODO: may move to Utils?
	 *
	 * Format amount according to the given currency with the currency code in the right.
	 *
	 * @param  float  $amount          Amount.
	 * @param  string $currency       3-letter currency code.
	 * @param  bool   $skip_symbol      Optional. If true, trims off the short currency symbol. Default false.
	 * @param  array  $currency_format Optional. Additional currency format for wc_price.
	 *
	 * @return string Formatted currency representation
	 */
	public static function format_explicit_currency( float $amount, string $currency, bool $skip_symbol = false, array $currency_format = [] ): string {
		$currency = strtoupper( $currency );

		$formatted_amount = wc_price(
			$amount,
			wp_parse_args( $currency_format, self::get_currency_format_for_wc_price( $currency ) )
		);

		$formatted_amount = html_entity_decode( wp_strip_all_tags( $formatted_amount ) );

		if ( $skip_symbol ) {
			$formatted_amount = preg_replace( '/[^0-9,\.]+/', '', $formatted_amount );
		}

		if ( false === strpos( $formatted_amount, $currency ) ) {
			return $formatted_amount . ' ' . $currency;
		}

		return $formatted_amount;
	}

	/**
	 * TODO: may move to Utils?
	 * Format an amount according to the given currency format.
	 *
	 * @param  float  $amount   Amount to format.
	 * @param  string $currency 3-letter currency code.
	 *
	 * @return string
	 */
	public static function format_currency( float $amount, string $currency ): string {
		$currency = strtoupper( $currency );

		$formatted = html_entity_decode(
			wp_strip_all_tags(
				wc_price(
					$amount,
					self::get_currency_format_for_wc_price( $currency )
				)
			)
		);

		if ( $amount >= 0 ) {
			return $formatted;
		}

		// Handle the subtle display difference for the negative amount between PHP wc_price `-$0.74` vs JavaScript formatCurrency `$-0.74` for the same input.
		// Remove the minus sign, and then move it right before the number.
		$formatted = str_replace( '-', '', $formatted );
		return preg_replace( '/([0-9,\.]+)/', '-$1', $formatted );
	}

	/**
	 * TODO: may move to Utils?
	 * Transform the currency format returned from localization service into
	 * the format that can be used by wc_price
	 *
	 * @param string $currency the currency code.
	 * @return array The currency format.
	 */
	public static function get_currency_format_for_wc_price( string $currency ) : array {
		$currency = strtoupper( $currency );

		$currency_data                = WC_Payments::get_localization_service()->get_currency_format( $currency );
		$currency_format_for_wc_price = [];
		foreach ( $currency_data as $key => $format ) {
			switch ( $key ) {
				case 'thousand_sep':
					$currency_format_for_wc_price['thousand_separator'] = $format;
					break;
				case 'decimal_sep':
					$currency_format_for_wc_price['decimal_separator'] = $format;
					break;
				case 'num_decimals':
					$currency_format_for_wc_price['decimals'] = $format;
					break;
				case 'currency_pos':
					$currency_format_for_wc_price['price_format'] = self::get_woocommerce_price_format( $format );
					break;
			}
		}
		$currency_format_for_wc_price['currency'] = $currency;
		return $currency_format_for_wc_price;
	}

	/**
	 * TODO: may move to Utils?
	 * Return the currency format based on the symbol position.
	 * Similar to get_woocommerce_price_format but with an input.
	 *
	 * @param string $currency_pos currency symbol position.
	 * @return string The currency format.
	 */
	public static function get_woocommerce_price_format( string $currency_pos ): string {
		switch ( $currency_pos ) {
			case 'left':
				return '%1$s%2$s';
			case 'right':
				return '%2$s%1$s';
			case 'left_space':
				return '%1$s&nbsp;%2$s';
			case 'right_space':
				return '%2$s&nbsp;%1$s';
			default:
				return '%1$s%2$s';
		}
	}
}
