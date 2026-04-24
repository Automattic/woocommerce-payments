<?php
/**
 * Class WC_Payments_Fee_Breakdown_Presenter
 *
 * @package WooCommerce\Payments
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * FEE_BREAKDOWN_FORK_CLONE: remove when envelope is the only path.
 *
 * Writes `display_label` / `display_text` fields onto a fee_breakdown_v1
 * envelope as it arrives on the merchant's PHP layer — the order-note
 * composer and the React admin timeline then both render from those
 * pre-resolved strings instead of each maintaining a parallel dictionary
 * that silently drifted between PHP and JS.
 *
 * Idempotent and side-effect free: safe to call at every arrival point
 * (charge API deserialization, captured-event webhook payloads) because
 * re-enriching an already-enriched envelope rewrites the same display
 * fields in whatever locale is active for the current request.
 */
class WC_Payments_Fee_Breakdown_Presenter {

	/**
	 * Attach pre-resolved display strings to the envelope. Returns a new
	 * array; the input is not mutated.
	 *
	 * @param array $envelope A fee_breakdown_v1 envelope.
	 * @return array The envelope with display_* fields populated.
	 */
	public static function enrich( array $envelope ): array {
		$store_currency = isset( $envelope['totals']['fee']['currency'] )
			? (string) $envelope['totals']['fee']['currency']
			: '';

		if ( isset( $envelope['rows'] ) && is_array( $envelope['rows'] ) ) {
			foreach ( $envelope['rows'] as $idx => $row ) {
				if ( ! is_array( $row ) ) {
					continue;
				}
				$envelope['rows'][ $idx ]['display_label'] = self::resolve_row_label( $row );
				$row_currency                              = $row['rate']['fixed_currency']
					?? ( $row['currency'] ?? $store_currency );
				$envelope['rows'][ $idx ]['display_rate']  = self::resolve_display_rate(
					$row['rate'] ?? null,
					(string) $row_currency
				);
			}
		}

		if ( isset( $envelope['notes'] ) && is_array( $envelope['notes'] ) ) {
			foreach ( $envelope['notes'] as $idx => $note ) {
				if ( ! is_array( $note ) ) {
					continue;
				}
				$text = self::resolve_note_text( $note );
				// Store null explicitly so downstream consumers can distinguish
				// "resolved to no text, drop the note" from "wasn't enriched".
				$envelope['notes'][ $idx ]['display_text'] = $text;
			}
		}

		if ( isset( $envelope['totals']['fee'] ) && is_array( $envelope['totals']['fee'] ) ) {
			$key                                        = isset( $envelope['totals']['fee']['key'] )
				? (string) $envelope['totals']['fee']['key']
				: '';
			$envelope['totals']['fee']['display_label'] = self::resolve_totals_label( $key );
			$envelope['totals']['fee']['display_rate']  = self::resolve_display_rate(
				$envelope['totals']['fee']['rate'] ?? null,
				$store_currency
			);
		}

		if ( isset( $envelope['totals']['tax'] ) && is_array( $envelope['totals']['tax'] ) ) {
			$envelope['totals']['tax']['display_line'] = self::resolve_tax_line(
				$envelope['totals']['tax'],
				$envelope['rows'] ?? []
			);
		}

		if ( isset( $envelope['totals']['net'] ) && is_array( $envelope['totals']['net'] ) ) {
			$envelope['totals']['net']['display_line'] = self::resolve_net_line(
				$envelope['totals']['net'],
				$store_currency
			);
		}
		if ( isset( $envelope['totals']['capture_net'] ) && is_array( $envelope['totals']['capture_net'] ) ) {
			$envelope['totals']['capture_net']['display_line'] = self::resolve_net_line(
				$envelope['totals']['capture_net'],
				$store_currency
			);
		}

		return $envelope;
	}

	/**
	 * Server-provided `label` wins — it's the envelope's typed override for
	 * cases the client shouldn't second-guess (e.g. a promotion name the
	 * server composed). Dictionary match comes next for the canonical keys
	 * both layers agreed on. Discount rows fall under a prefix because the
	 * server emits per-promotion ids (`discount.promo_2024`) but the
	 * merchant-facing wording is always the same. `tax_on_fee` has a
	 * per-charge jurisdiction description in `meta` that supersedes the
	 * generic wording when present. Raw key is the last resort — dropping
	 * an unknown key entirely would leave a blank bullet in the UI.
	 *
	 * @param array $row Row entry from the envelope.
	 * @return string
	 */
	private static function resolve_row_label( array $row ): string {
		$label = $row['label'] ?? null;
		if ( is_string( $label ) && '' !== $label ) {
			return $label;
		}

		$key = (string) ( $row['key'] ?? '' );
		$map = self::row_label_dictionary();
		if ( isset( $map[ $key ] ) ) {
			return $map[ $key ];
		}

		if ( 0 === strpos( $key, 'discount.' ) ) {
			return __( 'Discount', 'woocommerce-payments' );
		}

		if ( 'tax_on_fee' === $key ) {
			$description = null;
			if ( is_array( $row['meta'] ?? null ) && isset( $row['meta']['description'] ) && is_string( $row['meta']['description'] ) ) {
				$description = $row['meta']['description'];
			}
			return ( null !== $description && '' !== $description )
				? $description
				: __( 'Tax on fee', 'woocommerce-payments' );
		}

		return $key;
	}

	/**
	 * `totals.fee.key` carries a typed flag for cases where "Fee" is the
	 * wrong headline (currently `processing_fee` for the Amazon Pay non-card
	 * path, where our application fee was refunded and only Stripe's
	 * passthrough remains). Unknown keys fall back to "Fee" rather than the
	 * raw key because the totals headline must read as a human label, not a
	 * debug string.
	 *
	 * @param string $key Server-provided key, or '' when absent.
	 * @return string
	 */
	private static function resolve_totals_label( string $key ): string {
		$map = self::row_label_dictionary();
		if ( '' !== $key && isset( $map[ $key ] ) ) {
			return $map[ $key ];
		}
		return __( 'Fee', 'woocommerce-payments' );
	}

	/**
	 * Merchant-visible copy for the currently-known note codes. Returns
	 * null (not '') for unrecognised codes so the consumer can distinguish
	 * "this note is internal-only, suppress it" from "this note has a text
	 * the caller should render" — the server emits internal codes for
	 * support/telemetry that must never surface as raw identifiers.
	 *
	 * @param array $note Note entry from the envelope.
	 * @return string|null
	 */
	private static function resolve_note_text( array $note ): ?string {
		$code = (string) ( $note['code'] ?? '' );
		if ( 'application_fee_refunded' !== $code ) {
			return null;
		}

		$meta              = is_array( $note['meta'] ?? null ) ? $note['meta'] : [];
		$refunded_amount   = isset( $meta['refunded_amount'] ) ? (int) $meta['refunded_amount'] : 0;
		$refunded_currency = (string) ( $meta['refunded_currency'] ?? '' );
		if ( $refunded_amount <= 0 || '' === $refunded_currency ) {
			return __(
				'WooPayments refunded its application fee on this transaction.',
				'woocommerce-payments'
			);
		}

		$original_amount = isset( $meta['original_amount'] ) ? (int) $meta['original_amount'] : 0;
		$refunded_text   = WC_Payments_Utils::format_explicit_currency(
			WC_Payments_Utils::interpret_stripe_amount( $refunded_amount, $refunded_currency ),
			$refunded_currency,
			false
		);
		if ( $original_amount <= 0 ) {
			return sprintf(
				/* translators: %s is a monetary amount */
				__(
					'WooPayments refunded its %s application fee on this transaction.',
					'woocommerce-payments'
				),
				$refunded_text
			);
		}

		$original_text = WC_Payments_Utils::format_explicit_currency(
			WC_Payments_Utils::interpret_stripe_amount( $original_amount, $refunded_currency ),
			$refunded_currency,
			false
		);
		return sprintf(
			/* translators: %1$s is the refunded amount, %2$s is the pre-refund fee amount */
			__(
				'WooPayments refunded %1$s of its %2$s application fee on this transaction.',
				'woocommerce-payments'
			),
			$refunded_text,
			$original_text
		);
	}

	/**
	 * "2.9% + $0.30", "capped at $5", or "" when the rate has nothing
	 * renderable. `''` is a deliberate sentinel the consumers use to decide
	 * between the rate-qualified sprintf ("Fee (rate): amount") and the
	 * bare one ("Fee: amount") — don't collapse it to null.
	 *
	 * @param array|null $rate              Envelope rate structure.
	 * @param string     $fallback_currency Currency for the fixed part when
	 *                                      the rate omits `fixed_currency`.
	 * @return string
	 */
	private static function resolve_display_rate( ?array $rate, string $fallback_currency ): string {
		if ( null === $rate ) {
			return '';
		}
		if ( ! empty( $rate['capped'] ) ) {
			$cap_amount = isset( $rate['cap_amount'] ) ? (int) $rate['cap_amount'] : (int) ( $rate['fixed'] ?? 0 );
			$cap_curr   = $rate['fixed_currency'] ?? $fallback_currency;
			return sprintf(
				/* translators: %s is a monetary amount */
				__( 'capped at %s', 'woocommerce-payments' ),
				WC_Payments_Utils::format_currency(
					WC_Payments_Utils::interpret_stripe_amount( $cap_amount, $cap_curr ),
					$cap_curr
				)
			);
		}
		// The server may pre-format a canonical percentage string
		// (`percentage_display`) for cases where the raw float would round
		// inconsistently — prefer it so PHP and JS agree on the decimal
		// precision without each reimplementing the rounding rule.
		$parts = [];
		if ( isset( $rate['percentage_display'] ) && is_string( $rate['percentage_display'] ) && '' !== $rate['percentage_display'] ) {
			$parts[] = $rate['percentage_display'];
		} elseif ( isset( $rate['percentage'] ) && 0.0 !== (float) $rate['percentage'] ) {
			$parts[] = (string) round( (float) $rate['percentage'] * 100, 3 ) . '%';
		}
		$fixed_minor = isset( $rate['fixed'] ) ? (int) $rate['fixed'] : 0;
		$fixed_curr  = $rate['fixed_currency'] ?? $fallback_currency;
		if ( 0 !== $fixed_minor ) {
			$parts[] = WC_Payments_Utils::format_currency(
				WC_Payments_Utils::interpret_stripe_amount( $fixed_minor, $fixed_curr ),
				$fixed_curr
			);
		}
		return implode( ' + ', $parts );
	}

	/**
	 * Pre-composes the full tax line ("Tax IT VAT (22.00%): -$0.22") so
	 * both renderers don't reimplement the `Tax%s%s: %s` sprintf with the
	 * jurisdiction dictionary, percentage formatting, and sign coercion.
	 * Returns null when the envelope reports zero tax — consumers treat
	 * null as "don't render a tax line at all", same contract the legacy
	 * `compose_tax_string` used.
	 *
	 * @param array $tax_totals `totals.tax` block.
	 * @param array $rows       Envelope rows (used to find the tax row's label + percentage).
	 * @return string|null
	 */
	private static function resolve_tax_line( array $tax_totals, array $rows ): ?string {
		$amount = isset( $tax_totals['amount'] ) ? (int) $tax_totals['amount'] : 0;
		if ( 0 === $amount ) {
			return null;
		}

		$tax_row = null;
		foreach ( $rows as $row ) {
			if ( is_array( $row ) && 'tax' === ( $row['kind'] ?? '' ) ) {
				$tax_row = $row;
				break;
			}
		}

		$description = '';
		if ( null !== $tax_row && ! empty( $tax_row['label'] ) ) {
			$description = ' ' . WC_Payments_Captured_Event_Note::localize_tax_description_code( (string) $tax_row['label'] );
		}

		$percentage = '';
		if ( null !== $tax_row && isset( $tax_row['rate']['percentage'] ) && 0.0 !== (float) $tax_row['rate']['percentage'] ) {
			$percentage = ' (' . number_format( (float) $tax_row['rate']['percentage'] * 100, 2 ) . '%)';
		}

		$currency    = isset( $tax_totals['currency'] ) ? (string) $tax_totals['currency'] : '';
		$amount_text = WC_Payments_Utils::format_currency(
			-abs( WC_Payments_Utils::interpret_stripe_amount( $amount, $currency ) ),
			$currency
		);

		return sprintf(
			/* translators: 1: tax description 2: tax percentage 3: tax amount */
			__( 'Tax%1$s%2$s: %3$s', 'woocommerce-payments' ),
			$description,
			$percentage,
			$amount_text
		);
	}

	/**
	 * Pre-composes the "Net payout: $X.XX" line. Applied to both `net` and
	 * `capture_net` by `enrich()` — the caller picks which one to display
	 * (capture_net for the timeline's historical captured event, net for
	 * the summary card's current-state view).
	 *
	 * @param array  $net_totals        `totals.net` or `totals.capture_net` block.
	 * @param string $fallback_currency Used when the block omits `currency`.
	 * @return string|null
	 */
	private static function resolve_net_line( array $net_totals, string $fallback_currency ): ?string {
		if ( ! isset( $net_totals['amount'] ) ) {
			return null;
		}
		$currency = isset( $net_totals['currency'] ) && '' !== $net_totals['currency']
			? (string) $net_totals['currency']
			: $fallback_currency;
		if ( '' === $currency ) {
			return null;
		}
		return sprintf(
			/* translators: %s is a monetary amount */
			__( 'Net payout: %s', 'woocommerce-payments' ),
			WC_Payments_Utils::format_explicit_currency(
				WC_Payments_Utils::interpret_stripe_amount( (int) $net_totals['amount'], $currency ),
				$currency,
				false
			)
		);
	}

	/**
	 * Canonical key → label dictionary. Lives here (not in the consumers)
	 * so PHP and JS render from the same source of truth — prior drift
	 * between the two produced cases where a key was labelled on one side
	 * and surfaced raw on the other.
	 *
	 * @return array<string, string>
	 */
	private static function row_label_dictionary(): array {
		return [
			'base'                          => __( 'Base fee', 'woocommerce-payments' ),
			'additional.international'      => __( 'International card fee', 'woocommerce-payments' ),
			'additional.fx'                 => __( 'Currency conversion fee', 'woocommerce-payments' ),
			'additional.wcpay-subscription' => __( 'Subscription transaction fee', 'woocommerce-payments' ),
			'additional.device'             => __( 'Device fee', 'woocommerce-payments' ),
			'dispute_fee'                   => __( 'Dispute fee', 'woocommerce-payments' ),
			'dispute_fee_refund'            => __( 'Dispute fee refund', 'woocommerce-payments' ),
			'refund_fee'                    => __( 'Refund fee', 'woocommerce-payments' ),
			'financing_paydown'             => __( 'Loan paydown', 'woocommerce-payments' ),
			'processing_fee'                => __( 'Processing fee', 'woocommerce-payments' ),
		];
	}
}
