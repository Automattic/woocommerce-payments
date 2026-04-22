/** @format **/

/**
 * FEE_BREAKDOWN_FORK_CLONE: remove when envelope is the only path.
 *
 * Envelope-only timeline composers.
 *
 * Every helper here reads `event.fee_breakdown` — the server-authoritative
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
 * `composeEnvelopeFXString` is a local copy of the legacy `composeFXString` to
 * avoid a circular import; it reads the same `transaction_details` keys because
 * the timeline envelope (unlike `/charges/{id}`) does not yet carry an `fx` block.
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
import { resolveNoteText, resolveRowLabel } from '../fee-breakdown-label-map';

const isFXEvent = ( event = {} ) => {
	const { transaction_details: transactionDetails } = event;
	if ( ! transactionDetails ) {
		return false;
	}
	const {
		customer_currency: customerCurrency,
		store_currency: storeCurrency,
	} = transactionDetails;
	return (
		customerCurrency !== undefined &&
		storeCurrency !== undefined &&
		customerCurrency !== storeCurrency
	);
};

/**
 * Local copy of the legacy composeFXString — kept here to avoid a circular
 * import between map-events.js and this module. Pure formatting; reads the
 * same transaction_details fields because the timeline envelope does not
 * yet carry an `fx` block.
 */
const composeEnvelopeFXString = ( event ) => {
	if ( ! isFXEvent( event ) ) {
		return;
	}
	const {
		transaction_details: {
			customer_currency: customerCurrency,
			customer_amount: customerAmount,
			customer_amount_captured: customerAmountCaptured,
			store_currency: storeCurrency,
			store_amount: storeAmount,
			store_amount_captured: storeAmountCaptured,
		},
	} = event;
	return formatFX(
		{
			currency: customerCurrency,
			amount: customerAmountCaptured ?? customerAmount,
		},
		{
			currency: storeCurrency,
			amount: storeAmountCaptured ?? storeAmount,
		},
		undefined,
		storeCurrency
	);
};

/**
 * Format a fee rate (percentage + fixed) for display.
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
 * Build the captured-event note body from a server-driven fee_breakdown envelope.
 *
 * Mirrors the legacy compose* chain (fee line, breakdown, tax line, net line)
 * but without any client-side arithmetic — values come straight from
 * `rows`, `totals`, and `notes`.
 */
export const composeCapturedBodyFromBreakdown = ( event ) => {
	const breakdown = event.fee_breakdown;
	if ( ! breakdown ) {
		return [];
	}

	const storeCurrency = breakdown.totals.fee.currency;
	const lines = [];

	const fxLine = composeEnvelopeFXString( event );
	if ( fxLine ) {
		lines.push( fxLine );
	}

	// Append currency-code disambiguation (e.g. " USD", " CAD") when the
	// customer and store currencies share a symbol ($ vs $). Mirrors the
	// legacy `hasSameSymbol(customer, store)` check in composeFeeString.
	const customerCurrency =
		event.transaction_details?.customer_currency ?? storeCurrency;
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
	lines.push(
		totalRateText
			? sprintf(
					/* translators: 1: fee rate (e.g. 2.9% + $0.30) 2: monetary amount */
					__( 'Fee (%1$s): %2$s', 'woocommerce-payments' ),
					totalRateTextWithSuffix,
					feeAmountText
			  )
			: sprintf(
					/* translators: %s is a monetary amount */
					__( 'Fee: %s', 'woocommerce-payments' ),
					feeAmountText
			  )
	);

	// Per-component breakdown: show rate only (e.g. "Base fee: 2.9% + $0.30")
	// as a bulleted <ul>, matching the legacy composeFeeBreakdown output.
	// Adjustment rows (discounts) render a nested list with the variable
	// and fixed components separated.
	const feeRows = breakdown.rows.filter( ( row ) => row.kind !== 'tax' );
	if ( feeRows.length > 1 ) {
		lines.push(
			<ul key="fee-breakdown" className="fee-breakdown-list">
				{ feeRows.map( ( row, idx ) => {
					const label = resolveRowLabel( row.key, row.label, {
						meta: row.meta,
					} );
					const rowCurrency =
						row.rate?.fixed_currency ||
						row.currency ||
						storeCurrency;
					const rateText = formatRateText( row.rate, rowCurrency );

					if ( row.kind === 'adjustment' && row.rate ) {
						const pct = row.rate.percentage ?? 0;
						const fixed = row.rate.fixed ?? 0;
						if ( pct !== 0 && fixed !== 0 ) {
							const variableText = `${ Number.parseFloat(
								( Math.abs( pct ) * 100 ).toFixed( 3 )
							) }%`;
							const fixedText = formatCurrency(
								Math.abs( fixed ),
								rowCurrency,
								storeCurrency
							);
							return (
								<li key={ `${ row.key }-${ idx }` }>
									{ label }
									<ul className="discount-split-list">
										<li key="variable">
											{ sprintf(
												/* translators: %s is a percentage */
												__(
													'Variable fee: %s',
													'woocommerce-payments'
												),
												variableText
											) }
										</li>
										<li key="fixed">
											{ sprintf(
												/* translators: %s is a monetary amount */
												__(
													'Fixed fee: %s',
													'woocommerce-payments'
												),
												fixedText
											) }
										</li>
									</ul>
								</li>
							);
						}
					}
					return (
						<li key={ `${ row.key }-${ idx }` }>
							{ rateText ? `${ label }: ${ rateText }` : label }
						</li>
					);
				} ) }
			</ul>
		);
	}

	if ( breakdown.totals.tax.amount !== 0 ) {
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
		const taxPercentage = taxPercentageStr
			? ` (${ taxPercentageStr })`
			: '';
		const taxDisplayAmount =
			breakdown.totals.tax.display_amount ??
			-Math.abs( breakdown.totals.tax.amount );
		const taxAmountText = formatCurrency(
			taxDisplayAmount,
			breakdown.totals.tax.currency,
			storeCurrency
		);
		lines.push(
			sprintf(
				/* translators: 1: tax description 2: tax percentage 3: tax amount */
				__( 'Tax%1$s%2$s: %3$s', 'woocommerce-payments' ),
				taxDescription,
				taxPercentage,
				taxAmountText
			)
		);
	}

	lines.push(
		sprintf(
			/* translators: %s is a monetary amount */
			__( 'Net payout: %s', 'woocommerce-payments' ),
			formatExplicitCurrency(
				breakdown.totals.net.amount,
				breakdown.totals.net.currency,
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
 * Format the envelope's net amount for the deposit-line headline.
 *
 * Caller must have already verified `event.fee_breakdown` is present.
 * This is the single number the order-page "Transaction Fee" row and the
 * `_wcpay_net` meta also read from — keeping the deposit line consistent
 * with every other surface.
 */
export const formatEnvelopeNetString = ( event ) => {
	return formatExplicitCurrency(
		event.fee_breakdown.totals.net.amount,
		event.fee_breakdown.totals.net.currency,
		false,
		event.fee_breakdown.totals.net.currency
	);
};

/**
 * Envelope-authoritative deposit impact used by dispute timeline items.
 *
 * Returns `{ amount, currency }` (amount is the magnitude, not signed) or
 * `null` when the envelope is absent; callers fall back to the legacy
 * `|amount| + |fee|` math in that case.
 */
export const getEnvelopeDepositImpact = ( event ) => {
	const net = event.fee_breakdown?.totals?.net;
	if ( ! net || net.amount === undefined ) {
		return null;
	}
	return {
		amount: Math.abs( net.amount ),
		currency: net.currency ?? event.currency,
	};
};
