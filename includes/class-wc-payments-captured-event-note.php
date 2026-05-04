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

		// When the server attached a fee_breakdown_v1 envelope, render from it
		// verbatim. This covers Amazon Pay non-card, dispute fees, partial
		// refunds, and future fee quirks uniformly — no per-case branches.
		// The server gates the envelope behind its own feature option; its
		// absence is the signal to run the legacy composer below.
		//
		// Defense-in-depth: skip the envelope path when its shape is
		// incomplete (malformed / partial payload) so we fall back to the
		// legacy composer instead of emitting PHP notices mid-render.
		if ( ! empty( $this->captured_event['fee_breakdown_v1'] )
			&& self::is_renderable_breakdown( $this->captured_event['fee_breakdown_v1'] ) ) {
			return $this->generate_html_note_from_breakdown( $this->captured_event['fee_breakdown_v1'] );
		}

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
	 * FEE_BREAKDOWN_FORK_CLONE: remove when envelope is the only path.
	 *
	 * Render the HTML note from a server-driven fee_breakdown_v1 envelope.
	 *
	 * Derived from: generate_html_note() in the same class — the legacy
	 * composer that builds the order note from fee_rates/transaction_details
	 * via compose_fx_string / compose_fee_string / compose_fee_break_down /
	 * compose_tax_string / compose_net_string. Same line order and layout,
	 * but all arithmetic is removed: values come straight from the envelope's
	 * totals, rows, and notes.
	 *
	 * Takes server-authoritative rows / totals / notes and renders one HTML
	 * paragraph per line — mirroring the legacy layout without any of the
	 * per-event-type branching or client-side arithmetic.
	 *
	 * @param array $breakdown The fee_breakdown_v1 envelope.
	 * @return string
	 */
	private function generate_html_note_from_breakdown( array $breakdown ): string {
		// This HTML is persisted as an order note, so every server-provided
		// string reaching the `<p>` output runs through `esc_html` below —
		// cheap defense-in-depth against a future attacker-controlled label,
		// currency code, or note reaching WooCommerce verbatim, even though
		// the envelope itself is built by our own Fee_Breakdown_Builder and
		// shipped via signed transport. Currency formatters strip tags
		// internally, so the wrapping `esc_html` is idempotent for normal
		// amounts and only matters if a hostile currency code escapes their
		// final `$amount . ' ' . $code` concatenation.
		$store_currency   = $breakdown['totals']['fee']['currency'];
		$total_fee_amount = (int) $breakdown['totals']['fee']['amount'];
		$total_tax_amount = (int) $breakdown['totals']['tax']['amount'];
		// Read capture-time net so the order note (a historical record of
		// the capture) doesn't drift if regenerated after a later refund
		// or dispute. Matches the timeline captured event's "Net payout"
		// line on the JS side (compose.js reads totals.capture_net too).
		// Falls back to totals.net for older envelopes that pre-date the
		// capture_net split.
		$total_net_amount = isset( $breakdown['totals']['capture_net']['amount'] )
			? (int) $breakdown['totals']['capture_net']['amount']
			: (int) $breakdown['totals']['net']['amount'];
		$net_currency     = $breakdown['totals']['capture_net']['currency']
			?? ( $breakdown['totals']['net']['currency'] ?? $store_currency );

		$lines = [];

		$fx_string = $this->compose_fx_string();
		if ( null !== $fx_string ) {
			$lines[] = $fx_string;
		}

		$total_rate_text = self::format_rate_text( $breakdown['totals']['fee']['rate'] ?? null, $store_currency );
		$fee_amount_text = WC_Payments_Utils::format_explicit_currency(
			WC_Payments_Utils::interpret_stripe_amount( $total_fee_amount, $store_currency ),
			$store_currency,
			false
		);
		// Server may flag the totals row with a typed `key` (e.g.
		// 'processing_fee' for the Amazon Pay non-card case, where our
		// application fee was refunded). Fall back to "Fee" otherwise.
		$totals_key     = isset( $breakdown['totals']['fee']['key'] ) ? (string) $breakdown['totals']['fee']['key'] : '';
		$fee_line_label = self::fee_label_from_key( $totals_key );
		$lines[]        = '' !== $total_rate_text
			? sprintf(
				/* translators: 1: fee label (e.g. "Fee") 2: fee rate (e.g. 2.9% + $0.30) 3: monetary amount */
				__( '%1$s (%2$s): %3$s', 'woocommerce-payments' ),
				esc_html( $fee_line_label ),
				esc_html( $total_rate_text ),
				esc_html( $fee_amount_text )
			)
			: sprintf(
				/* translators: 1: fee label (e.g. "Fee" or "Processing fee") 2: monetary amount */
				__( '%1$s: %2$s', 'woocommerce-payments' ),
				esc_html( $fee_line_label ),
				esc_html( $fee_amount_text )
			);

		// Show the per-row breakdown when it adds information: skip it
		// when there's a single fee row (the "Fee (rate): amount" line
		// above already says everything). Sub-rows display rate only,
		// matching the legacy "Base fee: 2.9% + $0.30" format.
		$fee_rows = array_values(
			array_filter(
				$breakdown['rows'],
				static function ( array $row ) {
					return 'tax' !== ( $row['kind'] ?? '' );
				}
			)
		);

		if ( count( $fee_rows ) > 1 ) {
			$indent     = str_repeat( self::HTML_SPACE, 4 );
			$sub_indent = str_repeat( self::HTML_SPACE, 8 );
			foreach ( $fee_rows as $row ) {
				$label    = self::label_from_row( $row );
				$row_curr = $row['rate']['fixed_currency'] ?? ( $row['currency'] ?? $store_currency );

				// Adjustment row with both percentage and fixed components
				// (e.g., "-0.15% + -£0.20" promo discount): render parent
				// label and split the components into sub-bullets, mirroring
				// the legacy `compose_fee_break_down` HTML_WHITE_BULLET
				// layout and the JS `composeAdjustmentSplitFeeRow` output.
				// Fusing them into one rate string reads as arithmetic
				// rather than two independent discount rules.
				$adjustment_split = self::adjustment_split_lines( $row, $row_curr, $indent, $sub_indent );
				if ( null !== $adjustment_split ) {
					$lines = array_merge( $lines, $adjustment_split );
					continue;
				}

				$rate_text = self::format_rate_text( $row['rate'] ?? null, $row_curr );
				$lines[]   = $indent . ( '' !== $rate_text ? sprintf( '%1$s: %2$s', esc_html( $label ), esc_html( $rate_text ) ) : esc_html( $label ) );
			}
		}

		if ( 0 !== $total_tax_amount ) {
			// Pull description + percentage off the tax row (populated by
			// the server builder from Transaction_Fee_Detail tax record)
			// to match the legacy "Tax IT VAT (22.00%): -$X.XX" format.
			$tax_row = null;
			foreach ( $breakdown['rows'] as $candidate ) {
				if ( 'tax' === ( $candidate['kind'] ?? '' ) ) {
					$tax_row = $candidate;
					break;
				}
			}
			$tax_description = '';
			if ( null !== $tax_row && ! empty( $tax_row['label'] ) ) {
				// `localize_tax_description_code` maps to a dictionary of
				// `__()` translations or returns the "Tax" fallback — it
				// never reflects the raw label back. `esc_html` is still
				// applied in case a translator ever adds markup.
				$tax_description = ' ' . self::localize_tax_description_code( (string) $tax_row['label'] );
			}
			$tax_percentage = '';
			if ( null !== $tax_row && isset( $tax_row['rate']['percentage'] ) && 0.0 !== (float) $tax_row['rate']['percentage'] ) {
				$tax_percentage = ' (' . number_format( (float) $tax_row['rate']['percentage'] * 100, 2 ) . '%)';
			}
			$tax_amount_text = WC_Payments_Utils::format_currency(
				-abs( WC_Payments_Utils::interpret_stripe_amount( $total_tax_amount, $breakdown['totals']['tax']['currency'] ) ),
				$breakdown['totals']['tax']['currency']
			);
			$lines[]         = sprintf(
				/* translators: 1: tax description 2: tax percentage 3: tax amount */
				__( 'Tax%1$s%2$s: %3$s', 'woocommerce-payments' ),
				esc_html( $tax_description ),
				esc_html( $tax_percentage ),
				esc_html( $tax_amount_text )
			);
		}

		$lines[] = sprintf(
			/* translators: %s is a monetary amount */
			__( 'Net payout: %s', 'woocommerce-payments' ),
			esc_html(
				WC_Payments_Utils::format_explicit_currency(
					WC_Payments_Utils::interpret_stripe_amount( $total_net_amount, $net_currency ),
					$net_currency,
					false
				)
			)
		);

		if ( ! empty( $breakdown['notes'] ) ) {
			foreach ( $breakdown['notes'] as $note ) {
				$note_text = self::text_from_note( $note );
				if ( null !== $note_text && '' !== $note_text ) {
					// `text_from_note` already escapes its return — we
					// don't re-escape here so translators can't accidentally
					// double-encode entities in the copy.
					$lines[] = $note_text;
				}
			}
		}

		$html = '';
		foreach ( $lines as $line ) {
			$html .= '<p>' . $line . '</p>' . PHP_EOL;
		}

		return '<div class="captured-event-details">' . PHP_EOL
				. $html
				. '</div>';
	}

	/**
	 * Check whether an envelope has the minimum shape required by the
	 * renderer. Returns false for missing/malformed payloads so
	 * generate_html_note() can fall back to the legacy composer instead
	 * of emitting PHP notices mid-render.
	 *
	 * We control both sides of the wire, so a malformed envelope here
	 * represents either a rollout glitch or a manual fixture — in either
	 * case, reverting to the legacy composer is the safer outcome than
	 * a partially-rendered note.
	 *
	 * @param array $breakdown The fee_breakdown_v1 envelope candidate.
	 * @return bool
	 */
	private static function is_renderable_breakdown( array $breakdown ): bool {
		return isset(
			$breakdown['totals']['fee']['amount'],
			$breakdown['totals']['fee']['currency'],
			$breakdown['totals']['tax']['amount'],
			$breakdown['rows']
		) && is_array( $breakdown['rows'] )
			&& (
				isset( $breakdown['totals']['capture_net']['amount'] )
				|| isset( $breakdown['totals']['net']['amount'] )
			);
	}

	/**
	 * Server emits a typed `key` on `totals.fee` for cases where the
	 * default "Fee" wording is misleading — currently `processing_fee`
	 * for the Amazon Pay non-card path where our application fee was
	 * refunded and only Stripe's passthrough remains. Unknown or empty
	 * keys fall back to "Fee".
	 *
	 * @param string $key Server-provided key, or '' when absent.
	 * @return string
	 */
	private static function fee_label_from_key( string $key ): string {
		switch ( $key ) {
			case 'processing_fee':
				return __( 'Processing fee', 'woocommerce-payments' );
			default:
				return __( 'Fee', 'woocommerce-payments' );
		}
	}

	/**
	 * Derived from the inline label-mapping inside compose_fee_break_down()
	 * in the same class (the branches that turn `type` + `additional_type`
	 * into "Base fee" / "International card fee" / "Currency conversion fee"
	 * / "Discount"). Now keyed by the server's typed row key so the envelope
	 * can teach clients new labels without a PHP release.
	 *
	 * @param array $row Row entry from the envelope.
	 * @return string
	 */
	private static function label_from_row( array $row ): string {
		if ( ! empty( $row['label'] ) ) {
			// Server-provided label — escape on return so any downstream
			// concat into the HTML order note can't leak attacker-
			// controlled markup if the envelope is ever compromised
			// upstream. The dictionary branch below returns `__()` strings
			// that are plain text, so `esc_html` there is idempotent.
			return esc_html( (string) $row['label'] );
		}
		$key = (string) ( $row['key'] ?? '' );

		$map = [
			'base'                          => __( 'Base fee', 'woocommerce-payments' ),
			'additional.international'      => __( 'International card fee', 'woocommerce-payments' ),
			'additional.fx'                 => __( 'Currency conversion fee', 'woocommerce-payments' ),
			'additional.wcpay-subscription' => __( 'Subscription transaction fee', 'woocommerce-payments' ),
			'additional.device'             => __( 'Device fee', 'woocommerce-payments' ),
			'tax_on_fee'                    => __( 'Tax on fee', 'woocommerce-payments' ),
			'dispute_fee'                   => __( 'Dispute fee', 'woocommerce-payments' ),
			'dispute_fee_refund'            => __( 'Dispute fee refund', 'woocommerce-payments' ),
			'refund_fee'                    => __( 'Refund fee', 'woocommerce-payments' ),
			'financing_paydown'             => __( 'Loan paydown', 'woocommerce-payments' ),
		];
		if ( isset( $map[ $key ] ) ) {
			return $map[ $key ];
		}
		if ( 0 === strpos( $key, 'discount.' ) ) {
			return __( 'Discount', 'woocommerce-payments' );
		}
		return $key;
	}

	/**
	 * For an adjustment row (e.g. discount) carrying both a percentage and a
	 * fixed component, return the parent label line plus two sub-bullet
	 * lines — mirrors the legacy `compose_fee_break_down` HTML_WHITE_BULLET
	 * layout and the JS counterpart in `compose.js::composeAdjustmentSplitFeeRow`.
	 *
	 * Signs are preserved (e.g. "Variable fee: -0.15%", "Fixed fee: -£0.20")
	 * so the rendering matches the legacy snapshots; the database stores
	 * discounts as negative deltas applied to the cumulative effective rate.
	 *
	 * Returns null when the row doesn't qualify (non-adjustment, no rate, or
	 * either component is zero) so the caller can fall through to the
	 * single-line render.
	 *
	 * @param array  $row        Row entry from the envelope.
	 * @param string $row_curr   Currency to format the fixed amount in.
	 * @param string $indent     Indent string for the parent label line.
	 * @param string $sub_indent Indent string for the sub-bullet lines.
	 * @return array<string>|null
	 */
	private static function adjustment_split_lines( array $row, string $row_curr, string $indent, string $sub_indent ): ?array {
		if ( 'adjustment' !== ( $row['kind'] ?? '' ) || empty( $row['rate'] ) ) {
			return null;
		}
		$rate        = $row['rate'];
		$percentage  = isset( $rate['percentage'] ) ? (float) $rate['percentage'] : 0.0;
		$fixed_minor = isset( $rate['fixed'] ) ? (int) $rate['fixed'] : 0;
		if ( 0.0 === $percentage || 0 === $fixed_minor ) {
			return null;
		}

		$label         = self::label_from_row( $row );
		$variable_text = self::format_fee( $percentage ) . '%';
		$fixed_text    = WC_Payments_Utils::format_currency(
			WC_Payments_Utils::interpret_stripe_amount( $fixed_minor, $row_curr ),
			$row_curr
		);

		return [
			$indent . esc_html( $label ),
			$sub_indent . self::HTML_WHITE_BULLET . ' ' . esc_html(
				sprintf(
					/* translators: %s is a percentage number */
					__( 'Variable fee: %s', 'woocommerce-payments' ),
					$variable_text
				)
			),
			$sub_indent . self::HTML_WHITE_BULLET . ' ' . esc_html(
				sprintf(
					/* translators: %s is a monetary amount */
					__( 'Fixed fee: %s', 'woocommerce-payments' ),
					$fixed_text
				)
			),
		];
	}

	/**
	 * Format a fee rate (percentage + fixed) for display, matching the
	 * legacy "2.9% + $0.30" style. Returns an empty string when the rate
	 * has no percentage and no fixed part.
	 *
	 * Derived from: the sprintf('%1$s (%2$f%% + %3$s ...)') block inside
	 * compose_fee_string() and the "capped at" branch in the same class —
	 * extracted so the envelope path can render rates without any of the
	 * legacy fee_rates/history plumbing.
	 *
	 * @param array|null $rate           Rate array with percentage/fixed/fixed_currency keys.
	 * @param string     $store_currency Fallback currency for the fixed part.
	 * @return string
	 */
	private static function format_rate_text( ?array $rate, string $store_currency ): string {
		if ( null === $rate ) {
			return '';
		}
		// Capped fee: render "capped at $X" instead of the percent+fixed
		// combo, matching the legacy "Base fee: capped at $5" treatment.
		if ( ! empty( $rate['capped'] ) ) {
			$cap_amount = isset( $rate['cap_amount'] ) ? (int) $rate['cap_amount'] : (int) ( $rate['fixed'] ?? 0 );
			$cap_curr   = $rate['fixed_currency'] ?? $store_currency;
			return sprintf(
				/* translators: %s is a monetary amount */
				__( 'capped at %s', 'woocommerce-payments' ),
				WC_Payments_Utils::format_currency(
					WC_Payments_Utils::interpret_stripe_amount( $cap_amount, $cap_curr ),
					$cap_curr
				)
			);
		}
		$parts       = [];
		$percentage  = isset( $rate['percentage'] ) ? (float) $rate['percentage'] : 0.0;
		$fixed_minor = isset( $rate['fixed'] ) ? (int) $rate['fixed'] : 0;
		$fixed_curr  = $rate['fixed_currency'] ?? $store_currency;

		if ( 0.0 !== $percentage ) {
			$parts[] = self::format_fee( $percentage ) . '%';
		}
		if ( 0 !== $fixed_minor ) {
			$parts[] = WC_Payments_Utils::format_currency(
				WC_Payments_Utils::interpret_stripe_amount( $fixed_minor, $fixed_curr ),
				$fixed_curr
			);
		}
		return implode( ' + ', $parts );
	}

	/**
	 * Returns null when the note has no merchant-facing text so the caller
	 * can suppress it — the server emits internal-only codes (e.g. refund
	 * provenance) for telemetry and support that must never surface in the
	 * order note as raw strings.
	 *
	 * @param array $note Note entry from the envelope.
	 * @return string|null
	 */
	private static function text_from_note( array $note ): ?string {
		$code = (string) ( $note['code'] ?? '' );
		$meta = is_array( $note['meta'] ?? null ) ? $note['meta'] : [];

		switch ( $code ) {
			case 'application_fee_refunded':
				$refunded_amount   = isset( $meta['refunded_amount'] ) ? (int) $meta['refunded_amount'] : 0;
				$refunded_currency = (string) ( $meta['refunded_currency'] ?? '' );
				if ( $refunded_amount <= 0 || '' === $refunded_currency ) {
					return __(
						'WooPayments refunded its application fee on this transaction.',
						'woocommerce-payments'
					);
				}
				// `format_explicit_currency` strips HTML internally but
				// falls back to `$amount . ' ' . $currency` when the
				// formatted output doesn't contain the currency code —
				// meaning a hostile `refunded_currency` would concatenate
				// raw. Escape the final composed string so this can't reach
				// the `<p>`-wrapped order note verbatim.
				$formatted = WC_Payments_Utils::format_explicit_currency(
					WC_Payments_Utils::interpret_stripe_amount( $refunded_amount, $refunded_currency ),
					$refunded_currency,
					false
				);
				return esc_html(
					sprintf(
						/* translators: %s is a monetary amount */
						__(
							'WooPayments refunded its %s application fee on this transaction.',
							'woocommerce-payments'
						),
						$formatted
					)
				);
		}

		// Unknown codes are internal-only — drop them silently so server-side
		// telemetry additions never leak raw identifiers to merchants.
		return null;
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
			$fee_currency = $data['transaction_details']['customer_currency'];
			$fee_amount   = (int) $data['transaction_details']['customer_fee'];
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
			$is_same_symbol ? " {$data['transaction_details']['store_currency']}" : ''
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
	private static function format_fee( float $percentage ): string {
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
		return self::localize_tax_description_code(
			$this->captured_event['fee_rates']['tax']['description']
		);
	}

	/**
	 * Localize a raw tax description code (e.g. "IT VAT" → "IT VAT" in
	 * the active locale, or "Tax" if the code is unknown).
	 *
	 * @param string $tax_description_id Raw code like "IT VAT" or "JP JCT".
	 * @return string
	 */
	private static function localize_tax_description_code( string $tax_description_id ): string {
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
		$store_currency    = $this->captured_event['transaction_details']['store_currency'] ?? null;
		if ( ( strtoupper( $fee_currency ) === strtoupper( $store_currency ) ) || ! $this->is_fx_event() || ! $fee_exchange_rate ) {
			return WC_Payments_Utils::format_currency(
				-abs( WC_Payments_Utils::interpret_stripe_amount( $fee_amount, $fee_currency ) ),
				$fee_currency
			);
		}

		$rate          = $fee_exchange_rate['rate'];
		$from_currency = $fee_exchange_rate['from_currency'] ?? null;

		// Convert based on the direction of the exchange rate.
		$converted_amount =
			strtoupper( $fee_currency ) === strtoupper( $from_currency )
			? $fee_amount / $rate // Converting from store currency to customer currency.
			: $fee_amount * $rate; // Converting from customer currency to store currency.

		return WC_Payments_Utils::format_currency(
			-abs( WC_Payments_Utils::interpret_stripe_amount( $converted_amount, $store_currency ) ),
			$store_currency
		);
	}
}
