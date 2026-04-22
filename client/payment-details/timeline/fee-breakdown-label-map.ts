/**
 * FEE_BREAKDOWN_FORK_CLONE: remove when envelope is the only path.
 *
 * Label dictionary for server-driven fee_breakdown_v1 rows and notes.
 *
 * The server sends typed keys (e.g. `base`, `amazon_pay.stripe_processing_fee`,
 * `discount.promo_2024`) alongside a per-row `label` override. Clients prefer
 * the server's `label`, then look the key up here, then fall back to the raw
 * key. Unknown keys therefore degrade gracefully instead of crashing the UI.
 */

import { __, sprintf } from '@wordpress/i18n';
import { formatExplicitCurrency } from 'multi-currency/interface/functions';

export interface FeeBreakdownLabelContext {
	/** Row metadata passed through by the server (e.g. fee_id, discounted). */
	meta?: Record< string, unknown > | null;
}

type LabelResolver = (
	context: FeeBreakdownLabelContext
) => string | undefined;

/**
 * Ordered entries: the first resolver whose matcher returns true wins.
 * Exact keys are also accepted as a shorthand (the matcher does an ===).
 */
interface LabelEntry {
	match: string | ( ( key: string ) => boolean );
	resolver: LabelResolver;
}

const exact = ( key: string ) => ( k: string ) => k === key;
const prefix = ( start: string ) => ( k: string ) => k.startsWith( start );

const rowLabels: LabelEntry[] = [
	{
		match: exact( 'base' ),
		resolver: () => __( 'Base fee', 'woocommerce-payments' ),
	},
	{
		match: exact( 'additional.international' ),
		resolver: () => __( 'International card fee', 'woocommerce-payments' ),
	},
	{
		match: exact( 'additional.fx' ),
		resolver: () => __( 'Currency conversion fee', 'woocommerce-payments' ),
	},
	{
		match: exact( 'additional.wcpay-subscription' ),
		resolver: () =>
			__( 'Subscription transaction fee', 'woocommerce-payments' ),
	},
	{
		match: exact( 'additional.device' ),
		resolver: () => __( 'Device fee', 'woocommerce-payments' ),
	},
	{
		match: prefix( 'discount.' ),
		resolver: () => __( 'Discount', 'woocommerce-payments' ),
	},
	{
		match: exact( 'tax_on_fee' ),
		resolver: ( { meta } ) => {
			const description =
				typeof meta?.description === 'string'
					? meta.description
					: undefined;
			return description ?? __( 'Tax on fee', 'woocommerce-payments' );
		},
	},
	{
		match: exact( 'dispute_fee' ),
		resolver: () => __( 'Dispute fee', 'woocommerce-payments' ),
	},
	{
		match: exact( 'dispute_fee_refund' ),
		resolver: () => __( 'Dispute fee refund', 'woocommerce-payments' ),
	},
	{
		// Emitted on totals.fee.key when our application fee was refunded —
		// merchant's effective fee is only Stripe's passthrough. Used by the
		// timeline captured body and the fees-breakdown total row.
		match: exact( 'processing_fee' ),
		resolver: () => __( 'Processing fee', 'woocommerce-payments' ),
	},
];

/**
 * Note codes the client is currently willing to render. The server may emit
 * additional codes (for internal telemetry) — unknown codes are silently
 * dropped rather than leaked as raw identifiers to merchants.
 */
const noteLabels: Record< string, LabelResolver > = {
	// eslint-disable-next-line @typescript-eslint/naming-convention
	application_fee_refunded: ( { meta } ) => {
		const refundedAmount =
			typeof meta?.refunded_amount === 'number'
				? meta.refunded_amount
				: undefined;
		// `original_amount` was added alongside the partial-refund work —
		// older envelopes only carry `refunded_amount`. Handle both shapes:
		// when `original_amount` is present, render "refunded $X of its $Y";
		// when it's absent (old envelope), fall back to the single-amount
		// "refunded its $X" wording so the merchant still sees the refund
		// amount. Neither present: generic copy.
		const originalAmount =
			typeof meta?.original_amount === 'number'
				? meta.original_amount
				: undefined;
		const refundedCurrency =
			typeof meta?.refunded_currency === 'string'
				? meta.refunded_currency
				: undefined;
		if ( refundedAmount === undefined || ! refundedCurrency ) {
			return __(
				'WooPayments refunded its application fee on this transaction.',
				'woocommerce-payments'
			);
		}
		const refundedFormatted = formatExplicitCurrency(
			refundedAmount,
			refundedCurrency,
			false,
			refundedCurrency
		);
		if ( originalAmount === undefined ) {
			return sprintf(
				/* translators: %s is a monetary amount */
				__(
					'WooPayments refunded its %s application fee on this transaction.',
					'woocommerce-payments'
				),
				refundedFormatted
			);
		}
		const originalFormatted = formatExplicitCurrency(
			originalAmount,
			refundedCurrency,
			false,
			refundedCurrency
		);
		return sprintf(
			/* translators: %1$s is the refunded amount, %2$s is the pre-refund fee amount */
			__(
				'WooPayments refunded %1$s of its %2$s application fee on this transaction.',
				'woocommerce-payments'
			),
			refundedFormatted,
			originalFormatted
		);
	},
};

/**
 * Resolve a human-readable label for a breakdown row.
 *
 * Derived from: `formatFeeType` in `../transaction-breakdown/utils.ts`
 * (string table keyed by `type + additional_type`) and the inline
 * label strings inside `composeFeeBreakdown` in
 * `./map-events.js`. Consolidated here so server-typed keys map to
 * a single translation source.
 *
 * Preference order: explicit server `label` → dictionary match → raw key.
 */
export function resolveRowLabel(
	key: string,
	label: string | null,
	context: FeeBreakdownLabelContext = {}
): string {
	// Return value is rendered as a React text child — React auto-escapes it,
	// so a hostile `label` from the envelope cannot inject markup. DO NOT
	// feed this return into `dangerouslySetInnerHTML`; doing so would remove
	// the escape and turn a server-side envelope field into an XSS surface.
	// (The PHP counterpart, `WC_Payments_Captured_Event_Note`, runs its
	// label through `esc_html` because it concatenates into a persisted
	// `<p>`-wrapped order note.)
	if ( label !== null && label !== '' ) {
		return label;
	}
	for ( const entry of rowLabels ) {
		const matches =
			typeof entry.match === 'string'
				? entry.match === key
				: entry.match( key );
		if ( matches ) {
			const resolved = entry.resolver( context );
			if ( resolved ) {
				return resolved;
			}
		}
	}
	return key;
}

/**
 * Resolve a human-readable description for a breakdown note.
 *
 * Returns `null` when the code has no merchant-facing text — the caller
 * should then suppress the note entirely. This keeps internal-only codes
 * (recorded in `sources` for support) from ever reaching the UI.
 */
export function resolveNoteText(
	code: string,
	context: FeeBreakdownLabelContext = {}
): string | null {
	const resolver = noteLabels[ code ];
	if ( resolver ) {
		const resolved = resolver( context );
		if ( resolved ) {
			return resolved;
		}
	}
	return null;
}
