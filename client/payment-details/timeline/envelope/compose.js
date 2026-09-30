/** @format **/

/**
 * FEE_BREAKDOWN_FORK_CLONE: remove when envelope is the only path.
 *
 * Envelope-only timeline composers.
 *
 * Every helper here reads `event.fee_breakdown_v1` — the server-authoritative
 * envelope built by `WCPay\Utils\Fee_Breakdown_Builder`. None of it touches
 * `event.fee_rates` / `event.transaction_details.store_fee`; those are
 * legacy-allocator inputs owned by the sibling compose* functions in
 * `../map-events.js`.
 *
 * The split exists so future envelope tweaks can't regress legacy rendering,
 * and so the legacy code can be deleted in one pass once the server flag
 * (`_wcpay_feature_fee_breakdown_envelope_enabled`) is default-on.
 *
 * Shared primitives (`formatCurrency`, `hasSameSymbol`, `getLocalizedTaxDescription`,
 * the label map) are imported — duplicating those would cost more than it saves.
 * The FX line is sourced from the envelope's top-level `fx` block (emitted by
 * Fee_Breakdown_Builder::build_fx_block on cross-currency charges), so this
 * module no longer needs a local `composeFXString` copy or a read of
 * `event.transaction_details.customer_*`.
 */

/**
 * External dependencies
 */
import React from 'react';
import { __, sprintf } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
import {
	formatCurrency,
	formatExplicitCurrency,
	formatFX,
} from 'multi-currency/interface/functions';
import { hasSameSymbol } from 'multi-currency/utils/currency';
import { getLocalizedTaxDescription } from '../../utils/tax-descriptions';
import {
	resolveNoteText,
	resolveFeeRowLabel,
} from '../fee-breakdown-label-map';

/**
 * Render the FX line for a captured event from the envelope's `fx` block.
 *
 * The server's Fee_Breakdown_Builder emits `fx` with the full from/to
 * currency + amount pair (plus a pre-formatted `rate_display` string) so
 * the client no longer has to read `event.transaction_details.*` directly.
 * Returns `undefined` when the charge is same-currency (server omits `fx`).
 */
const composeEnvelopeFXLine = ( breakdown, storeCurrency ) => {
	const fx = breakdown.fx;
	if ( ! fx || ! fx.from_currency || ! fx.to_currency ) {
		return undefined;
	}

	return formatFX(
		{ currency: fx.from_currency, amount: fx.from_amount ?? 0 },
		{ currency: fx.to_currency, amount: fx.to_amount ?? 0 },
		undefined,
		storeCurrency
	);
};

/**
 * Format a fee rate (percentage + fixed) for display.
 *
 * Derived from: the percent+fixed formatting block inside `composeFeeString`
 * in `../map-events.js` (the `'%1$s (%2$f%% + %3$s%4$s)'` sprintf + the
 * `capped at` branch), extracted here so envelope rendering can be
 * exercised without any of the legacy `fee_rates`-driven branches.
 *
 * Prefers `rate.percentage_display` (server-owned canonical precision like
 * "2.9%" / "22.00%"); falls back to local toFixed(3) when older envelopes
 * omit it. Returns an empty string when the rate has no renderable parts.
 */
const formatRateText = ( rate, storeCurrency ) => {
	if ( ! rate ) {
		return '';
	}

	if ( rate.capped ) {
		const capAmount = rate.cap_amount ?? rate.fixed ?? 0;
		const capCurrency = rate.fixed_currency || storeCurrency;
		return sprintf(
			/* translators: %s is a monetary amount */
			__( 'capped at %s', 'woocommerce-payments' ),
			formatCurrency( capAmount, capCurrency, storeCurrency )
		);
	}

	const parts = [];
	const percentage = rate.percentage ?? 0;
	if ( rate.percentage_display ) {
		parts.push( rate.percentage_display );
	} else if ( percentage !== 0 ) {
		parts.push(
			`${ Number.parseFloat( ( percentage * 100 ).toFixed( 3 ) ) }%`
		);
	}

	const fixed = rate.fixed ?? 0;
	const fixedCurrency = rate.fixed_currency || storeCurrency;
	if ( fixed !== 0 ) {
		parts.push( formatCurrency( fixed, fixedCurrency, storeCurrency ) );
	}

	return parts.join( ' + ' );
};

/**
 * When a discount row carries both a variable (%) and fixed component, show
 * each piece as its own bullet. Fusing them into one string ("-2% + -$0.30")
 * reads as arithmetic rather than two independent discount rules, which is
 * what the merchant actually wants to see.
 *
 * Signs are preserved on both pieces so a discount renders as
 * "Variable fee: -0.15%" / "Fixed fee: -£0.20" — matches the legacy
 * `map-events.js` snapshots and the production order-note format. The
 * adjustment row's negative deltas come straight from the server's
 * `rows_from_history` (the database stores discounts as negative deltas
 * applied on top of the cumulative effective rate).
 *
 * Returns `null` when the row doesn't qualify (non-adjustment, no rate, or
 * only one of the two components is non-zero) so the caller can fall through
 * to the single-line render.
 */
const composeAdjustmentSplitFeeRow = (
	row,
	idx,
	label,
	rowCurrency,
	storeCurrency
) => {
	if ( row.kind !== 'adjustment' || ! row.rate ) {
		return null;
	}

	const pct = row.rate.percentage ?? 0;
	const fixed = row.rate.fixed ?? 0;
	if ( pct === 0 || fixed === 0 ) {
		return null;
	}

	const variableText = `${ Number.parseFloat(
		( pct * 100 ).toFixed( 3 )
	) }%`;
	const fixedText = formatCurrency( fixed, rowCurrency, storeCurrency );
	return (
		<li key={ `${ row.key }-${ idx }` }>
			{ label }
			<ul className="discount-split-list">
				<li key="variable">
					{ sprintf(
						/* translators: %s is a percentage */
						__( 'Variable fee: %s', 'woocommerce-payments' ),
						variableText
					) }
				</li>
				<li key="fixed">
					{ sprintf(
						/* translators: %s is a monetary amount */
						__( 'Fixed fee: %s', 'woocommerce-payments' ),
						fixedText
					) }
				</li>
			</ul>
		</li>
	);
};

/**
 * Compose the tax line for the captured-event body.
 *
 * Returns `undefined` when the envelope reports zero tax so the caller can
 * omit the line entirely — mirrors `composeEnvelopeFXLine`'s contract and
 * matches the legacy `composeTaxString` behaviour of not emitting a "Tax: $0"
 * row.
 */
const composeTaxLineFromBreakdown = ( breakdown, storeCurrency ) => {
	if ( breakdown.totals.tax.amount === 0 ) {
		return undefined;
	}

	const taxRow = breakdown.rows.find( ( row ) => row.kind === 'tax' );
	const taxDescription =
		taxRow && taxRow.label
			? ` ${ getLocalizedTaxDescription( taxRow.label ) }`
			: '';
	const taxPercentageRate = taxRow?.rate?.percentage;
	const taxPercentageStr =
		taxRow?.rate?.percentage_display ??
		( taxPercentageRate
			? `${ ( taxPercentageRate * 100 ).toFixed( 2 ) }%`
			: '' );
	const taxPercentage = taxPercentageStr ? ` (${ taxPercentageStr })` : '';
	const taxDisplayAmount =
		breakdown.totals.tax.display_amount ??
		-Math.abs( breakdown.totals.tax.amount );
	const taxAmountText = formatCurrency(
		taxDisplayAmount,
		breakdown.totals.tax.currency,
		storeCurrency
	);

	return sprintf(
		/* translators: 1: tax description 2: tax percentage 3: tax amount */
		__( 'Tax%1$s%2$s: %3$s', 'woocommerce-payments' ),
		taxDescription,
		taxPercentage,
		taxAmountText
	);
};

/**
 * Build the captured-event note body from a server-driven fee_breakdown_v1 envelope.
 *
 * Derived from: the `case 'captured':` body in `../map-events.js` — the
 * chain of `composeFXString` + `composeFeeString` + `composeFeeBreakdown`
 * + `composeTaxString` + `composeNetString`. Same line order and layout,
 * but all arithmetic has been removed: values come straight from
 * `rows`, `totals`, and `notes`.
 */
export const composeCapturedBodyFromBreakdown = ( event ) => {
	const breakdown = event.fee_breakdown_v1;
	if ( ! breakdown ) {
		return [];
	}

	const storeCurrency = breakdown.totals.fee.currency;
	const lines = [];

	const fxLine = composeEnvelopeFXLine( breakdown, storeCurrency );
	if ( fxLine ) {
		lines.push( fxLine );
	}

	// Append currency-code disambiguation (e.g. " USD", " CAD") when the
	// customer and store currencies share a symbol ($ vs $). Mirrors the
	// legacy `hasSameSymbol(customer, store)` check in composeFeeString.
	// Read the customer currency off the envelope's fx block; falls back
	// to transaction_details for envelopes missing fx (older server).
	const customerCurrency =
		breakdown.fx?.from_currency ??
		event.transaction_details?.customer_currency ??
		storeCurrency;
	const isSameSymbol = hasSameSymbol( customerCurrency, storeCurrency );
	const currencySuffix = isSameSymbol ? ` ${ storeCurrency }` : '';

	// Fee line: use server-provided display_amount (already signed for
	// display) so we don't second-guess with -Math.abs(). Drop the
	// currency-code suffix via formatCurrency (vs. formatExplicitCurrency)
	// to match the legacy "-$1.27" style.
	const feeDisplayAmount =
		breakdown.totals.fee.display_amount ??
		-Math.abs( breakdown.totals.fee.amount );
	const feeAmountText =
		formatCurrency(
			feeDisplayAmount,
			breakdown.totals.fee.currency,
			storeCurrency
		) + currencySuffix;
	const totalRateText = formatRateText(
		breakdown.totals.fee.rate,
		storeCurrency
	);
	const totalRateTextWithSuffix =
		totalRateText && breakdown.totals.fee.rate?.fixed && isSameSymbol
			? `${ totalRateText }${ currencySuffix }`
			: totalRateText;
	// Server may flag the totals row with a typed `key` (e.g. 'processing_fee'
	// for the Amazon Pay non-card case, where our application fee was
	// refunded and only Stripe's passthrough remains). Fall back to "Fee".
	const totalFeeLabel = resolveFeeRowLabel(
		breakdown.totals.fee.key ?? '',
		null
	);
	const defaultFeeLabel = __( 'Fee', 'woocommerce-payments' );
	const feeLineLabel =
		totalFeeLabel && totalFeeLabel !== '' ? totalFeeLabel : defaultFeeLabel;
	lines.push(
		totalRateText
			? sprintf(
					/* translators: 1: fee label (e.g. "Fee") 2: fee rate (e.g. 2.9% + $0.30) 3: monetary amount */
					__( '%1$s (%2$s): %3$s', 'woocommerce-payments' ),
					feeLineLabel,
					totalRateTextWithSuffix,
					feeAmountText
			  )
			: sprintf(
					/* translators: 1: fee label (e.g. "Fee" or "Processing fee") 2: monetary amount */
					__( '%1$s: %2$s', 'woocommerce-payments' ),
					feeLineLabel,
					feeAmountText
			  )
	);

	const feeRows = breakdown.rows.filter( ( row ) => row.kind !== 'tax' );
	if ( feeRows.length > 1 ) {
		lines.push(
			<ul key="fee-breakdown" className="fee-breakdown-list">
				{ feeRows.map( ( row, idx ) => {
					const label = resolveFeeRowLabel( row.key, row.label, {
						meta: row.meta,
					} );
					const rowCurrency =
						row.rate?.fixed_currency ||
						row.currency ||
						storeCurrency;
					const splitRow = composeAdjustmentSplitFeeRow(
						row,
						idx,
						label,
						rowCurrency,
						storeCurrency
					);
					if ( splitRow ) {
						return splitRow;
					}

					const rateText = formatRateText( row.rate, rowCurrency );
					return (
						<li key={ `${ row.key }-${ idx }` }>
							{ rateText ? `${ label }: ${ rateText }` : label }
						</li>
					);
				} ) }
			</ul>
		);
	}

	const taxLine = composeTaxLineFromBreakdown( breakdown, storeCurrency );
	if ( taxLine ) {
		lines.push( taxLine );
	}

	// Historical "Net payout" for the captured event — read capture-time
	// net, not current-state net, so a later refund doesn't rewrite the
	// deposit line of the capture event in the timeline.
	lines.push(
		sprintf(
			/* translators: %s is a monetary amount */
			__( 'Net payout: %s', 'woocommerce-payments' ),
			formatExplicitCurrency(
				breakdown.totals.capture_net.amount,
				breakdown.totals.capture_net.currency,
				false,
				storeCurrency
			)
		)
	);

	breakdown.notes.forEach( ( note ) => {
		const text = resolveNoteText( note.code, { meta: note.meta } );
		if ( text ) {
			lines.push( text );
		}
	} );

	return lines;
};

/**
 * Format the envelope's capture-time net for the deposit-line headline.
 *
 * Derived from: `formatNetString` in `../map-events.js` (the `gross - fee`
 * subtraction with FX branching) — replaced by a direct read of
 * `totals.capture_net.amount`.
 *
 * Reads `totals.capture_net` (not `totals.net`) because the timeline
 * captured event is a historical point-in-time record: a later refund
 * gets its own timeline entry and should not retroactively rewrite the
 * deposit headline for the capture event. `totals.net` is reserved for
 * consumers that want current-state (summary card, `_wcpay_net` meta).
 *
 * Caller must have already verified `event.fee_breakdown_v1` is present.
 */
export const formatEnvelopeNetString = ( event ) =>
	formatExplicitCurrency(
		event.fee_breakdown_v1.totals.capture_net.amount,
		event.fee_breakdown_v1.totals.capture_net.currency,
		false,
		event.fee_breakdown_v1.totals.capture_net.currency
	);

/**
 * Envelope-authoritative deposit impact used by dispute timeline items.
 *
 * Derived from: the inline `Math.abs( event.amount ) + Math.abs( event.fee )`
 * expressions at the `dispute_needs_response` and `dispute_won` call sites
 * in `../map-events.js` — replaced by a direct read of `totals.net.amount`.
 *
 * Returns `{ amount, currency }` (amount is the magnitude, not signed) or
 * `null` when the envelope is absent; callers fall back to the legacy
 * `|amount| + |fee|` math in that case.
 */
export const getEnvelopeDepositImpact = ( event ) => {
	const net = event.fee_breakdown_v1?.totals?.net;
	if ( ! net || net.amount === undefined ) {
		return null;
	}

	return {
		amount: Math.abs( net.amount ),
		currency: net.currency ?? event.currency,
	};
};
