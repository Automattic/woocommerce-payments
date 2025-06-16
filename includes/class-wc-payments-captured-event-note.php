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

		if ( $this->has_tax() ) {
			$lines[] = $this->compose_tax_string();
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

		if ( $this->has_tax() ) {
			$before_tax   = $data['fee_rates']['before_tax'];
			$fee_amount   = $before_tax['amount'];
			$fee_currency = $before_tax['currency'];
		} else {
			$fee_currency = $data['transaction_details']['store_currency'];
			$fee_amount   = (int) $data['transaction_details']['store_fee'];
		}

		$formatted_fee_amount = $this->convert_and_format_fee_amount( $fee_amount, $fee_currency );

		$base_fee_label = $this->is_base_fee_only()
			? __( 'Base fee', 'woocommerce-payments' )
			: __( 'Fee', 'woocommerce-payments' );

		$is_capped = isset( $history[0]['capped'] ) && true === $history[0]['capped'];

		if ( $this->is_base_fee_only() && $is_capped ) {
			return sprintf(
				'%1$s (capped at %2$s): %3$s',
				$base_fee_label,
				WC_Payments_Utils::format_currency( $fixed, $fixed_currency ),
				$formatted_fee_amount
			);
		}
		$is_same_symbol = $this->has_same_currency_symbol( $data['transaction_details']['store_currency'], $data['transaction_details']['customer_currency'] );

		return sprintf(
			'%1$s (%2$s%% + %3$s%4$s): %5$s%6$s',
			$base_fee_label,
			self::format_fee( $percentage ),
			WC_Payments_Utils::format_currency( $fixed, $fixed_currency ),
			$is_same_symbol ? ' ' . $data['transaction_details']['customer_currency'] : '',
			$formatted_fee_amount,
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
	 * Compose tax string.
	 *
	 * @return string|null
	 */
	public function compose_tax_string(): ?string {
		if ( ! $this->has_tax() ) {
			return null;
		}

		$tax        = $this->captured_event['fee_rates']['tax'];
		$tax_amount = $tax['amount'];
		if ( 0 === $tax_amount ) {
			return null;
		}

		$tax_currency     = $tax['currency'];
		$formatted_amount = $this->convert_and_format_fee_amount( $tax_amount, $tax_currency );

		$tax_description      = ' ' . $this->get_localized_tax_description();
		$percentage_rate      = $tax['percentage_rate'];
		$formatted_percentage = ' (' . self::format_fee( $percentage_rate ) . '%)';

		return sprintf(
			/* translators: 1: tax description 2: tax percentage 3: tax amount */
			__( 'Tax%1$s%2$s: %3$s', 'woocommerce-payments' ),
			$tax_description,
			$formatted_percentage,
			$formatted_amount
		);
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

	/**
	 * Check if the event has tax information.
	 *
	 * @return bool
	 */
	private function has_tax(): bool {
		return isset( $this->captured_event['fee_rates']['tax'] );
	}

	/**
	 * Get localized tax description based on the tax description ID contained in the captured event.
	 *
	 * @return string|null
	 */
	private function get_localized_tax_description(): ?string {
		if ( ! isset( $this->captured_event['fee_rates']['tax']['description'] ) ) {
			return null;
		}

		$tax_description_id = $this->captured_event['fee_rates']['tax']['description'];

		$tax_descriptions = [
			// European Union VAT.
			'AT VAT' => __( 'AT VAT', 'woocommerce-payments' ), // Austria.
			'BE VAT' => __( 'BE VAT', 'woocommerce-payments' ), // Belgium.
			'BG VAT' => __( 'BG VAT', 'woocommerce-payments' ), // Bulgaria.
			'CY VAT' => __( 'CY VAT', 'woocommerce-payments' ), // Cyprus.
			'CZ VAT' => __( 'CZ VAT', 'woocommerce-payments' ), // Czech Republic.
			'DE VAT' => __( 'DE VAT', 'woocommerce-payments' ), // Germany.
			'DK VAT' => __( 'DK VAT', 'woocommerce-payments' ), // Denmark.
			'EE VAT' => __( 'EE VAT', 'woocommerce-payments' ), // Estonia.
			'ES VAT' => __( 'ES VAT', 'woocommerce-payments' ), // Spain.
			'FI VAT' => __( 'FI VAT', 'woocommerce-payments' ), // Finland.
			'FR VAT' => __( 'FR VAT', 'woocommerce-payments' ), // France.
			'GB VAT' => __( 'UK VAT', 'woocommerce-payments' ), // United Kingdom.
			'GR VAT' => __( 'GR VAT', 'woocommerce-payments' ), // Greece.
			'HR VAT' => __( 'HR VAT', 'woocommerce-payments' ), // Croatia.
			'HU VAT' => __( 'HU VAT', 'woocommerce-payments' ), // Hungary.
			'IE VAT' => __( 'IE VAT', 'woocommerce-payments' ), // Ireland.
			'IT VAT' => __( 'IT VAT', 'woocommerce-payments' ), // Italy.
			'LT VAT' => __( 'LT VAT', 'woocommerce-payments' ), // Lithuania.
			'LU VAT' => __( 'LU VAT', 'woocommerce-payments' ), // Luxembourg.
			'LV VAT' => __( 'LV VAT', 'woocommerce-payments' ), // Latvia.
			'MT VAT' => __( 'MT VAT', 'woocommerce-payments' ), // Malta.
			'NO VAT' => __( 'NO VAT', 'woocommerce-payments' ), // Norway.
			'NL VAT' => __( 'NL VAT', 'woocommerce-payments' ), // Netherlands.
			'PL VAT' => __( 'PL VAT', 'woocommerce-payments' ), // Poland.
			'PT VAT' => __( 'PT VAT', 'woocommerce-payments' ), // Portugal.
			'RO VAT' => __( 'RO VAT', 'woocommerce-payments' ), // Romania.
			'SE VAT' => __( 'SE VAT', 'woocommerce-payments' ), // Sweden.
			'SI VAT' => __( 'SI VAT', 'woocommerce-payments' ), // Slovenia.
			'SK VAT' => __( 'SK VAT', 'woocommerce-payments' ), // Slovakia.

			// GST Countries.
			'AU GST' => __( 'AU GST', 'woocommerce-payments' ), // Australia.
			'NZ GST' => __( 'NZ GST', 'woocommerce-payments' ), // New Zealand.
			'SG GST' => __( 'SG GST', 'woocommerce-payments' ), // Singapore.

			// Other Tax Systems.
			'CH VAT' => __( 'CH VAT', 'woocommerce-payments' ), // Switzerland.
			'JP JCT' => __( 'JP JCT', 'woocommerce-payments' ), // Japan Consumption Tax.
		];

		return $tax_descriptions[ $tax_description_id ] ?? __( 'Tax', 'woocommerce-payments' );
	}

	/**
	 * Given the fee amount and currency, converts it to the store currency if necessary and formats using formatCurrency.
	 *
	 * @param float  $fee_amount Fee amount to convert and format.
	 * @param string $fee_currency Fee currency to convert from.
	 *
	 * @return string Formatted fee amount in the store currency.
	 */
	private function convert_and_format_fee_amount( float $fee_amount, string $fee_currency ) {
		$fee_exchange_rate = $this->captured_event['fee_rates']['fee_exchange_rate'] ?? null;
		if ( ! $this->is_fx_event() || ! $fee_exchange_rate ) {
			return WC_Payments_Utils::format_currency(
				-abs( WC_Payments_Utils::interpret_stripe_amount( $fee_amount, $fee_currency ) ),
				$fee_currency
			);
		}

		$rate           = $fee_exchange_rate['rate'];
		$from_currency  = $fee_exchange_rate['from_currency'] ?? null;
		$store_currency = $this->captured_event['transaction_details']['store_currency'] ?? null;

		// Convert based on the direction of the exchange rate.
		$converted_amount =
			$fee_currency === $from_currency
			? $fee_amount * $rate // Converting from store currency to customer currency.
			: $fee_amount / $rate; // Converting from customer currency to store currency.

		return WC_Payments_Utils::format_currency(
			-abs( WC_Payments_Utils::interpret_stripe_amount( $converted_amount, $store_currency ) ),
			$store_currency
		);
	}
}
