/** @format **/

/**
 * External dependencies
 */
import React from 'react';
import { __ } from '@wordpress/i18n';
import { Flex, FlexItem } from '@wordpress/components';

/** Internal dependencies */
import {
	TimelineFeeBreakdown,
	TimelineFeeBreakdownNote,
	TimelineFeeBreakdownRow,
} from 'wcpay/data/timeline/types';
import { formatCurrency } from 'multi-currency/interface/functions';
import {
	resolveNoteText,
	resolveFeeRowLabel,
} from 'wcpay/payment-details/timeline/fee-breakdown-label-map';

interface Props {
	data: TimelineFeeBreakdown;
}

/**
 * Derived from: `BreakdownFeeRate` in `./fee-breakdown-components.tsx`
 * (the `percentage * 100` + fixed-currency formatting), extended to
 * prefer the server's `percentage_display` when present.
 */
const buildRateText = (
	percentage: number | undefined,
	fixed: number | undefined,
	currency: string,
	storeCurrency: string,
	percentageDisplay?: string
): string => {
	const parts: string[] = [];
	// Prefer the server-owned percentage_display string ("2.9%", "22.00%")
	// for canonical precision. Kills the 2dp-vs-3dp-vs-0dp drift across
	// timeline, breakdown panel, and tax line.
	if ( percentageDisplay ) {
		parts.push( percentageDisplay );
	} else if ( percentage !== undefined && percentage !== 0 ) {
		parts.push(
			`${ Number.parseFloat( ( percentage * 100 ).toFixed( 2 ) ) }%`
		);
	}
	if ( fixed !== undefined && fixed !== 0 ) {
		parts.push(
			`${ formatCurrency(
				fixed,
				currency,
				storeCurrency
			) } ${ storeCurrency }`
		);
	}
	return parts.join( ' + ' );
};

/**
 * Derived from: `FeeRow` in `./fee-breakdown-components.tsx` — one row of
 * the fees-breakdown panel. Reads envelope row fields directly instead of
 * reconstructing label/rate from `type` + `additional_type`.
 */
const BreakdownRow: React.FC< {
	row: TimelineFeeBreakdownRow;
	storeCurrency: string;
} > = ( { row, storeCurrency } ) => {
	const label = resolveFeeRowLabel( row.key, row.label, { meta: row.meta } );
	const rateText = buildRateText(
		row.rate?.percentage,
		row.rate?.fixed,
		row.rate?.fixed_currency ?? row.currency,
		storeCurrency,
		row.rate?.percentage_display
	);
	// Use the server-signed display_amount when available — avoids
	// render-site sign coercion. When an older envelope omits
	// display_amount, fall back to a kind-aware signed magnitude so
	// fees/tax still render as deductions rather than as positive
	// amounts — matches the "display_amount earns its keep on fee and
	// tax" contract in the envelope design doc.
	const signedAmount =
		row.display_amount ??
		( row.kind === 'fee' || row.kind === 'tax'
			? -Math.abs( row.amount )
			: row.amount );
	const amountText = formatCurrency(
		signedAmount,
		row.currency,
		storeCurrency
	);
	const rowClass = `wcpay-transaction-breakdown__fee_info wcpay-transaction-breakdown__fee_info--${
		row.kind
	} wcpay-transaction-breakdown__${ row.key.replace( /\./g, '_' ) }_fee_info`;

	return (
		<Flex
			className={ rowClass }
			wrap={ true }
			justify="space-between"
			align="end"
		>
			<FlexItem className="wcpay-transaction-breakdown__fee_name">
				{ label }
			</FlexItem>
			<FlexItem className="wcpay-transaction-breakdown__fee_rate">
				{ rateText ? `${ rateText } (${ amountText })` : amountText }
			</FlexItem>
		</Flex>
	);
};

/**
 * Derived from: `FeeRow` in `./fee-breakdown-components.tsx` (the
 * `type="total"` branch — percentage + fixed rendered in the total row).
 * Reduced to a pre-computed `amount` read because the envelope already
 * sums totals server-side.
 */
const TotalRow: React.FC< {
	label?: string;
	amount: number;
	currency: string;
	storeCurrency: string;
} > = ( { label, amount, currency, storeCurrency } ) => (
	<Flex
		className="wcpay-transaction-breakdown__fee_info wcpay-transaction-breakdown__fee_info--total"
		wrap={ true }
		justify="space-between"
		align="end"
	>
		<FlexItem className="wcpay-transaction-breakdown__fee_name">
			{ label ?? __( 'Total', 'woocommerce-payments' ) }
		</FlexItem>
		<FlexItem className="wcpay-transaction-breakdown__fee_rate">
			{ formatCurrency( amount, currency, storeCurrency ) }
		</FlexItem>
	</Flex>
);

const NotesList: React.FC< {
	notes: TimelineFeeBreakdownNote[];
} > = ( { notes } ) => {
	// Resolve text up-front and drop any notes without merchant-facing copy.
	// Server may emit internal-only codes (logged to `sources`); those must
	// not leak to the UI.
	const renderable = notes
		.map( ( note ) => ( {
			...note,
			text: resolveNoteText( note.code, { meta: note.meta } ),
		} ) )
		.filter(
			( note ): note is TimelineFeeBreakdownNote & { text: string } =>
				typeof note.text === 'string' && note.text !== ''
		);

	if ( renderable.length === 0 ) {
		return null;
	}

	return (
		<ul className="wcpay-transaction-breakdown__fee_notes">
			{ renderable.map( ( note, idx ) => (
				<li
					key={ `${ note.code }-${ idx }` }
					className={ `wcpay-transaction-breakdown__fee_note wcpay-transaction-breakdown__fee_note--${
						note.severity ?? 'info'
					}` }
				>
					{ note.text }
				</li>
			) ) }
		</ul>
	);
};

/**
 * FEE_BREAKDOWN_FORK_CLONE: remove when envelope is the only path.
 *
 * Server-driven fees breakdown renderer.
 *
 * Derived from: `FeesBreakdown` in `./index.tsx` — the legacy allocator
 * that walks `fee_rates.history`, applies percentage/fixed discounts row
 * by row, and re-sums the total. Rewritten here to render envelope rows
 * verbatim (server has already applied discounts and summed totals).
 *
 * Takes the `fee_breakdown_v1` envelope built by the server's
 * Fee_Breakdown_Builder and renders it verbatim: one row per
 * `rows[]` entry (plus a total row), plus any notes. Does no
 * arithmetic of its own — discounts are already applied to
 * downstream rates on the server. See the design doc for the
 * envelope contract.
 */
const FeesBreakdownV1: React.FC< Props > = ( { data } ) => {
	const storeCurrency = data.totals.fee.currency;

	return (
		<div
			className="wcpay-transaction-breakdown__fees-container wcpay-transaction-breakdown__fees-container--v1"
			role="table"
			aria-label={ __(
				'Transaction fees breakdown',
				'woocommerce-payments'
			) }
		>
			{ data.rows.map( ( row, idx ) => (
				<BreakdownRow
					key={ `${ row.key }-${ idx }` }
					row={ row }
					storeCurrency={ storeCurrency }
				/>
			) ) }
			<TotalRow
				amount={ data.totals.fee.amount }
				currency={ data.totals.fee.currency }
				storeCurrency={ storeCurrency }
			/>
			{ data.totals.tax.amount !== 0 && (
				<TotalRow
					label={ __( 'Tax on fee', 'woocommerce-payments' ) }
					amount={ data.totals.tax.amount }
					currency={ data.totals.tax.currency }
					storeCurrency={ storeCurrency }
				/>
			) }
			<NotesList notes={ data.notes } />
		</div>
	);
};

export default FeesBreakdownV1;
