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

import { __ } from '@wordpress/i18n';

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
		match: exact( 'refund_fee' ),
		resolver: () => __( 'Refund fee', 'woocommerce-payments' ),
	},
	{
		match: exact( 'financing_paydown' ),
		resolver: () => __( 'Loan paydown', 'woocommerce-payments' ),
	},
];

/**
 * Note codes the client is currently willing to render. The server may emit
 * additional codes (for internal telemetry) — unknown codes are silently
 * dropped rather than leaked as raw identifiers to merchants.
 */
const noteLabels: Record< string, LabelResolver > = {};

/**
 * Resolve a human-readable label for a breakdown row.
 *
 * Preference order: explicit server `label` → dictionary match → raw key.
 */
export function resolveRowLabel(
	key: string,
	label: string | null,
	context: FeeBreakdownLabelContext = {}
): string {
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
