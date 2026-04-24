/**
 * FEE_BREAKDOWN_FORK_CLONE: remove when envelope is the only path.
 *
 * Clients prefer the server's per-row `label`, then look the key up here,
 * then fall back to the raw key — so a new typed key the server starts
 * emitting before a client release degrades gracefully instead of crashing
 * the UI.
 */

import { __, sprintf } from '@wordpress/i18n';
import { formatExplicitCurrency } from 'multi-currency/interface/functions';

export interface FeeBreakdownLabelContext {
	meta?: Record< string, unknown > | null;
}

const rowLabels: Record< string, string > = {
	base: __( 'Base fee', 'woocommerce-payments' ),
	'additional.international': __(
		'International card fee',
		'woocommerce-payments'
	),
	'additional.fx': __( 'Currency conversion fee', 'woocommerce-payments' ),
	'additional.wcpay-subscription': __(
		'Subscription transaction fee',
		'woocommerce-payments'
	),
	'additional.device': __( 'Device fee', 'woocommerce-payments' ),
	dispute_fee: __( 'Dispute fee', 'woocommerce-payments' ),
	dispute_fee_refund: __( 'Dispute fee refund', 'woocommerce-payments' ),
	// Server emits this on totals.fee.key when our application fee was
	// refunded — the merchant's effective fee is only Stripe's passthrough,
	// so the headline must not still say "WooPayments fee".
	processing_fee: __( 'Processing fee', 'woocommerce-payments' ),
};

/**
 * Preference order: explicit server `label` → dictionary match → raw key.
 */
export function resolveFeeRowLabel(
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
	if ( key in rowLabels ) {
		return rowLabels[ key ];
	}
	if ( key.startsWith( 'discount.' ) ) {
		return __( 'Discount', 'woocommerce-payments' );
	}
	if ( key === 'tax_on_fee' ) {
		const description =
			typeof context.meta?.description === 'string'
				? context.meta.description
				: undefined;
		return description ?? __( 'Tax on fee', 'woocommerce-payments' );
	}
	return key;
}

/**
 * Returns `null` for codes with no merchant-facing text so the caller can
 * suppress the note — the server emits internal-only codes (recorded in
 * `sources` for support) that must never surface in the UI as raw strings.
 */
export function resolveNoteText(
	code: string,
	context: FeeBreakdownLabelContext = {}
): string | null {
	if ( code !== 'application_fee_refunded' ) {
		return null;
	}

	const { meta } = context;
	const refundedAmount =
		typeof meta?.refunded_amount === 'number'
			? meta.refunded_amount
			: undefined;

	// `original_amount` landed with partial-refund support; older envelopes
	// only carry `refunded_amount`. The three-branch cascade below keeps
	// older events readable on current clients instead of showing generic
	// copy when the merchant-visible amount is still available.
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
}
