/** @format **/

/**
 * External dependencies
 */
import { flatMap } from 'lodash';
import { __, sprintf } from '@wordpress/i18n';
import { addQueryArgs } from '@wordpress/url';
import { createInterpolateElement } from '@wordpress/element';
import { Link } from '@woocommerce/components';
import SyncIcon from 'gridicons/dist/sync';
import PlusIcon from 'gridicons/dist/plus';
import MinusIcon from 'gridicons/dist/minus';
import InfoOutlineIcon from 'gridicons/dist/info-outline';
import CheckmarkIcon from 'gridicons/dist/checkmark';
import CrossIcon from 'gridicons/dist/cross';
import NoticeOutlineIcon from 'gridicons/dist/notice-outline';

/**
 * Internal dependencies
 */
import { reasons as disputeReasons } from 'disputes/strings';
import {
	formatCurrency,
	formatFX,
	formatExplicitCurrency,
} from 'multi-currency/interface/functions';
import { formatFee } from 'utils/fees';
import { getAdminUrl } from 'wcpay/utils';
import { ShieldIcon } from 'wcpay/icons';
import { fraudOutcomeRulesetMapping, paymentFailureMapping } from './mappings';
import { formatDateTimeFromTimestamp } from 'wcpay/utils/date-time';
import { hasSameSymbol } from 'multi-currency/utils/currency';
import { getLocalizedTaxDescription } from '../utils/tax-descriptions';
import {
	resolveNoteText,
	resolveRowLabel,
} from './fee-breakdown-label-map';

/**
 * Format a rate object (percentage + fixed) as "2.9% + $0.30" for display.
 * When the rate is capped, returns "capped at $X" instead — matching the
 * legacy "Base fee: capped at $5" treatment. Returns an empty string when
 * the rate is null or has no non-zero parts.
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
	const fixed = rate.fixed ?? 0;
	const fixedCurrency = rate.fixed_currency || storeCurrency;
	if ( percentage !== 0 ) {
		parts.push(
			`${ Number.parseFloat( ( percentage * 100 ).toFixed( 3 ) ) }%`
		);
	}
	if ( fixed !== 0 ) {
		parts.push(
			formatCurrency( fixed, fixedCurrency, storeCurrency )
		);
	}
	return parts.join( ' + ' );
};

/**
 * Creates a timeline item about a payment status change
 *
 * @param {Object} event  An event triggering the status change
 * @param {string} status Localized status description
 *
 * @return {Object} Formatted status change timeline item
 */
const getStatusChangeTimelineItem = ( event, status ) => {
	return {
		date: new Date( event.datetime * 1000 ),
		icon: <SyncIcon />,
		headline: createInterpolateElement(
			sprintf(
				// translators: %s new status, for example Authorized, Refunded, etc
				__(
					'Payment status changed to <strong>%s</strong>.',
					'woocommerce-payments'
				),
				status
			),
			{
				strong: <strong />,
			}
		),
		body: [],
	};
};

/**
 * Creates a timeline item about a payout
 *
 * @param {Object}  event           An event affecting the payout
 * @param {string}  formattedAmount Formatted amount string
 * @param {boolean} isPositive      Whether the amount will be added or deducted
 * @param {Array}   body            Any extra subitems that should be included as item body
 *
 * @return {Object} Payout timeline item
 */
const getDepositTimelineItem = (
	event,
	formattedAmount,
	isPositive,
	body = []
) => {
	let headline = '';
	if ( event.deposit ) {
		headline = sprintf(
			isPositive
				? // translators: %1$s - formatted amount, %2$s - payout arrival date, <a> - link to the payout
				  __(
						'%1$s was added to your <a>%2$s payout</a>.',
						'woocommerce-payments'
				  )
				: // translators: %1$s - formatted amount, %2$s - payout arrival date, <a> - link to the payout
				  __(
						'%1$s was deducted from your <a>%2$s payout</a>.',
						'woocommerce-payments'
				  ),
			formattedAmount,
			formatDateTimeFromTimestamp( event.deposit.arrival_date )
		);
		const depositUrl = getAdminUrl( {
			page: 'wc-admin',
			path: '/payments/payouts/details',
			id: event.deposit.id,
		} );

		headline = createInterpolateElement( headline, {
			// eslint-disable-next-line jsx-a11y/anchor-has-content
			a: <Link href={ depositUrl } />,
		} );
	} else {
		headline = sprintf(
			isPositive
				? // translators: %s - formatted amount
				  __(
						'%s will be added to a future payout.',
						'woocommerce-payments'
				  )
				: // translators: %s - formatted amount
				  __(
						'%s will be deducted from a future payout.',
						'woocommerce-payments'
				  ),
			formattedAmount
		);
	}

	return {
		date: new Date( event.datetime * 1000 ),
		icon: isPositive ? <PlusIcon /> : <MinusIcon />,
		headline,
		body,
	};
};

/**
 * Creates a timeline item about a financing paydown
 *
 * @param {Object} event           An event affecting the payout
 * @param {string} formattedAmount Formatted amount string
 * @param {Array}  body            Any extra subitems that should be included as item body
 *
 * @return {Object} Payout timeline item
 */
const getFinancingPaydownTimelineItem = ( event, formattedAmount, body ) => {
	let headline = '';
	if ( event.deposit ) {
		headline = sprintf(
			// translators: %1$s - formatted amount, %2$s - payout arrival date, <a> - link to the payout
			__(
				'%1$s was subtracted from your <a>%2$s payout</a>.',
				'woocommerce-payments'
			),
			formattedAmount,
			formatDateTimeFromTimestamp( event.deposit.arrival_date )
		);

		const depositUrl = getAdminUrl( {
			page: 'wc-admin',
			path: '/payments/payouts/details',
			id: event.deposit.id,
		} );

		headline = createInterpolateElement( headline, {
			// eslint-disable-next-line jsx-a11y/anchor-has-content
			a: <Link href={ depositUrl } />,
		} );
	} else {
		headline = sprintf(
			__(
				'%s will be subtracted from a future payout.',
				'woocommerce-payments'
			),
			formattedAmount
		);
	}

	return {
		date: new Date( event.datetime * 1000 ),
		icon: <MinusIcon />,
		headline,
		body,
	};
};

/**
 * Formats the main item for the event
 *
 * @param {Object}          event    Event object
 * @param {string | Object} headline Headline describing the event
 * @param {JSX.Element}     icon     Icon component to render for this event
 * @param {Array}           body     Body to include in this item, defaults to empty
 *
 * @return {Object} Formatted main item
 */
const getMainTimelineItem = ( event, headline, icon, body = [] ) => ( {
	date: new Date( event.datetime * 1000 ),
	headline,
	icon,
	body,
} );

const isFXEvent = ( event = {} ) => {
	const { transaction_details: transactionDetails = {} } = event;
	const {
		customer_currency: customerCurrency,
		store_currency: storeCurrency,
	} = transactionDetails;
	return (
		customerCurrency && storeCurrency && customerCurrency !== storeCurrency
	);
};

/**
 * Given the fee amount and currency, converts it to the store currency if necessary and formats using formatCurrency.
 *
 * @param {number} feeAmount   Fee amount to convert and format.
 * @param {string} feeCurrency Fee currency to convert from.
 * @param {Object} event       Event object containing fee rates and transaction details.
 *
 * @return {string} Formatted fee amount in the store currency.
 */
const convertAndFormatFeeAmount = ( feeAmount, feeCurrency, event ) => {
	const storeCurrency =
		event.transaction_details?.store_currency?.toUpperCase();
	if (
		( storeCurrency && storeCurrency === feeCurrency.toUpperCase() ) ||
		! isFXEvent( event ) ||
		! event.fee_rates?.fee_exchange_rate
	) {
		return formatCurrency(
			-Math.abs( feeAmount ),
			feeCurrency,
			storeCurrency
		);
	}

	const {
		from_amount: fromAmount,
		to_amount: toAmount,
		from_currency: fromCurrency,
	} = event.fee_rates.fee_exchange_rate;

	// Handle zero amounts
	if ( feeAmount === 0 || fromAmount === 0 || toAmount === 0 ) {
		return formatCurrency( 0, storeCurrency, storeCurrency );
	}

	// Use the ratio from the fee_exchange_rate to convert the fee amount
	const convertedAmount =
		feeCurrency.toUpperCase() === fromCurrency.toUpperCase()
			? Math.floor( ( feeAmount * toAmount ) / fromAmount )
			: Math.floor( ( feeAmount * fromAmount ) / toAmount );

	return formatCurrency(
		-Math.abs( convertedAmount ),
		storeCurrency,
		storeCurrency
	);
};

/**
 * Returns a boolean indicating whether only fee applied is the base fee
 *
 * @param {Object} event Event object
 *
 * @return {boolean} true if the only applied fee is the base fee
 */
const isBaseFeeOnly = ( event ) => {
	if ( ! event.fee_rates ) return false;

	const history = event.fee_rates.history;
	return history?.length === 1 && history[ 0 ].type === 'base';
};

const formatNetString = ( event ) => {
	// Prefer the server-authoritative totals when the envelope is present.
	// This is the single number the order page "Transaction Fee" row and
	// the _wcpay_net order meta also read from, so the deposit line stays
	// consistent with every other surface.
	if ( event.fee_breakdown ) {
		return formatExplicitCurrency(
			event.fee_breakdown.totals.net.amount,
			event.fee_breakdown.totals.net.currency,
			false,
			event.fee_breakdown.totals.net.currency
		);
	}

	const {
		amount_captured: amountCaptured,
		fee,
		currency,
		transaction_details: {
			store_amount_captured: storeAmountCaptured,
			store_fee: storeFee,
			store_currency: storeCurrency,
		},
	} = event;

	if ( ! isFXEvent( event ) ) {
		return formatExplicitCurrency(
			amountCaptured - fee,
			currency,
			false,
			storeCurrency
		);
	}

	// We need to use the store amount and currency for the net amount calculation in the case of a FX event.
	return formatExplicitCurrency(
		storeAmountCaptured - storeFee,
		storeCurrency,
		false,
		storeCurrency
	);
};

export const composeNetString = ( event ) => {
	return sprintf(
		/* translators: %s is a monetary amount */
		__( 'Net payout: %s', 'woocommerce-payments' ),
		formatNetString( event )
	);
};

export const composeTaxString = ( event ) => {
	const tax = event.fee_rates?.tax;
	if ( ! tax || tax.amount === 0 ) {
		return '';
	}

	const taxDescription = tax.description
		? ` ${ getLocalizedTaxDescription( tax.description ) }`
		: '';

	const taxPercentage = tax.percentage_rate
		? ` (${ ( tax.percentage_rate * 100 ).toFixed( 2 ) }%)`
		: '';

	const formattedTaxAmount = convertAndFormatFeeAmount(
		tax.amount,
		tax.currency,
		event
	);

	return sprintf(
		/* translators: 1: tax description 2: tax percentage 3: tax amount */
		__( 'Tax%1$s%2$s: %3$s', 'woocommerce-payments' ),
		taxDescription,
		taxPercentage,
		formattedTaxAmount
	);
};

/**
 * Build the captured-event note body from a server-driven fee_breakdown envelope.
 *
 * Mirrors the legacy compose* chain (fee line, breakdown, tax line, net line)
 * but without any client-side arithmetic — values come straight from
 * `rows`, `totals`, and `notes`. Returns an array of strings suitable for
 * passing to getMainTimelineItem.
 */
export const composeCapturedBodyFromBreakdown = ( event ) => {
	const breakdown = event.fee_breakdown;
	if ( ! breakdown ) {
		return [];
	}

	const storeCurrency = breakdown.totals.fee.currency;
	const lines = [];

	// Cross-border / multi-currency "1 USD → 0.92 EUR: 0.50 EUR" header.
	// Driven by the event's transaction_details (customer vs. store
	// currency), not by the envelope — the envelope already expresses
	// amounts in store currency, but merchants want to see the FX
	// conversion context above the fees.
	const fxLine = composeFXString( event );
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

	// Fee line: force negative sign, drop the currency-code suffix
	// (formatCurrency vs. formatExplicitCurrency) to match the legacy
	// "-$1.27" style.
	const feeAmountText =
		formatCurrency(
			-Math.abs( breakdown.totals.fee.amount ),
			breakdown.totals.fee.currency,
			storeCurrency
		) + currencySuffix;
	const totalRateText = formatRateText(
		breakdown.totals.fee.rate,
		storeCurrency
	);
	// When the cumulative rate has a fixed part we'd also suffix the
	// currency code after it, matching "(2.9% + $0.30 USD): -$1.27 USD".
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
	// and fixed components separated — matching the legacy discount-split
	// rendering. Skip when there's just one fee row.
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

					// Discount rows: render "Discount" with a nested
					// variable / fixed split when both are non-zero.
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
							{ rateText
								? `${ label }: ${ rateText }`
								: label }
						</li>
					);
				} ) }
			</ul>
		);
	}

	if ( breakdown.totals.tax.amount !== 0 ) {
		// Match the legacy "Tax IT VAT (22.00%): -$X.XX" format by pulling
		// the description + percentage off the tax row (which the builder
		// populates from the Transaction_Fee_Detail tax record).
		const taxRow = breakdown.rows.find(
			( row ) => row.kind === 'tax'
		);
		const taxDescription =
			taxRow && taxRow.label
				? ` ${ getLocalizedTaxDescription( taxRow.label ) }`
				: '';
		const taxPercentageRate = taxRow?.rate?.percentage;
		const taxPercentage = taxPercentageRate
			? ` (${ ( taxPercentageRate * 100 ).toFixed( 2 ) }%)`
			: '';
		const taxAmountText = formatCurrency(
			-Math.abs( breakdown.totals.tax.amount ),
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

export const composeFeeString = ( event ) => {
	if ( ! event.fee_rates ) {
		return sprintf(
			/* translators: %s is a monetary amount */
			__( 'Fee: %s', 'woocommerce-payments' ),
			formatCurrency( event.fee, event.currency )
		);
	}

	const {
		percentage,
		fixed,
		fixed_currency: fixedCurrency,
		history,
	} = event.fee_rates;

	const baseFeeLabel = isBaseFeeOnly( event )
		? __( 'Base fee', 'woocommerce-payments' )
		: __( 'Fee', 'woocommerce-payments' );

	// Get the appropriate fee amounts and currencies
	let feeAmount, feeCurrency, baseFee, baseFeeCurrency;
	if ( isFXEvent( event ) ) {
		feeAmount =
			event.fee_rates?.before_tax?.amount ||
			event.transaction_details.customer_fee;
		feeCurrency =
			event.fee_rates?.before_tax?.currency ||
			event.transaction_details.customer_currency;
		baseFee = fixed || 0;
		baseFeeCurrency = fixedCurrency || feeCurrency;
	} else {
		feeAmount = event.fee_rates.before_tax
			? event.fee_rates.before_tax.amount
			: event.fee;
		feeCurrency = event.fee_rates.before_tax
			? event.fee_rates.before_tax.currency
			: event.currency;
		baseFee = fixed;
		baseFeeCurrency = fixedCurrency;
	}

	const formattedFeeAmount = convertAndFormatFeeAmount(
		feeAmount,
		feeCurrency,
		event
	);

	const storeCurrency = event.transaction_details?.store_currency;

	if ( isBaseFeeOnly( event ) && history[ 0 ]?.capped ) {
		return sprintf(
			'%1$s (capped at %2$s): %3$s',
			baseFeeLabel,
			formatCurrency( baseFee, baseFeeCurrency, storeCurrency ),
			formattedFeeAmount
		);
	}

	const hasIdenticalSymbol = hasSameSymbol(
		event.transaction_details.store_currency,
		event.transaction_details.customer_currency
	);

	return sprintf(
		'%1$s (%2$f%% + %3$s%4$s): %5$s%6$s',
		baseFeeLabel,
		formatFee( percentage ),
		formatCurrency( baseFee, baseFeeCurrency, storeCurrency ),
		hasIdenticalSymbol
			? ` ${ event.transaction_details.customer_currency }`
			: '',
		formattedFeeAmount,
		hasIdenticalSymbol
			? ` ${ event.transaction_details.store_currency }`
			: ''
	);
};

export const composeFXString = ( event ) => {
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

// Conditionally adds the ARN details to the timeline in case they're available.
const getRefundTrackingDetails = ( event ) => {
	return event.acquirer_reference_number_status === 'available'
		? sprintf(
				/* translators: %s is a trcking reference number */
				__(
					'Acquirer Reference Number (ARN) %s',
					'woocommerce-payments'
				),
				event.acquirer_reference_number
		  )
		: '';
};

// Converts the failure reason enums to error messages.
const getRefundFailureReason = ( event ) => {
	switch ( event.failure_reason ) {
		case 'expired_or_canceled_card':
			return __(
				'the card being expired or canceled.',
				'woocommerce-payments'
			);
		case 'lost_or_stolen_card':
			return __(
				'the card being lost or stolen.',
				'woocommerce-payments'
			);
		case 'unknown':
			return __(
				'the card being lost or stolen.',
				'woocommerce-payments'
			);
	}
};

/**
 * Returns an object containing fee breakdown.
 * Keys are fee types such as base, additional-fx, etc, except for "discount" that is an object including more discount details.
 *
 * @param {Object} event Event object
 *
 * @return {Object<string, string|{ label: string, variable: string, fixed: string }>|undefined} Object containing
 * 		formatted fee strings keyed by fee type, or undefined when no breakdown is available.
 */
export const feeBreakdown = ( event ) => {
	if ( ! event?.fee_rates?.history ) {
		return undefined;
	}

	// hide breakdown when there's only a base fee
	if ( isBaseFeeOnly( event ) ) {
		return undefined;
	}

	const {
		fee_rates: { history },
	} = event;

	const feeLabelMapping = ( fixedRate, isCapped ) => ( {
		base: ( () => {
			if ( isCapped ) {
				/* translators: %2$s is the capped fee */
				return __( 'Base fee: capped at %2$s', 'woocommerce-payments' );
			}

			if ( fixedRate !== 0 ) {
				/* translators: %1$s% is the fee percentage and %2$s is the fixed rate */
				return __( 'Base fee: %1$s%% + %2$s', 'woocommerce-payments' );
			}

			/* translators: %1$s% is the fee percentage */
			return __( 'Base fee: %1$s%%', 'woocommerce-payments' );
		} )(),

		'additional-international':
			fixedRate !== 0
				? __(
						/* translators: %1$s% is the fee percentage and %2$s is the fixed rate */
						'International card fee: %1$s%% + %2$s',
						'woocommerce-payments'
				  )
				: __(
						/* translators: %1$s% is the fee percentage */
						'International card fee: %1$s%%',
						'woocommerce-payments'
				  ),
		'additional-fx':
			fixedRate !== 0
				? __(
						/* translators: %1$s% is the fee percentage and %2$s is the fixed rate */
						'Currency conversion fee: %1$s%% + %2$s',
						'woocommerce-payments'
				  )
				: __(
						/* translators: %1$s% is the fee percentage */
						'Currency conversion fee: %1$s%%',
						'woocommerce-payments'
				  ),
		'additional-wcpay-subscription':
			fixedRate !== 0
				? __(
						/* translators: %1$s% is the fee amount and %2$s is the fixed rate */
						'Subscription transaction fee: %1$s%% + %2$s',
						'woocommerce-payments'
				  )
				: __(
						/* translators: %1$s% is the fee amount */
						'Subscription transaction fee: %1$s%%',
						'woocommerce-payments'
				  ),
		'additional-device':
			fixedRate !== 0
				? __(
						/* translators: %1$s% is the fee amount and %2$s is the fixed rate */
						'Tap to pay transaction fee: %1$s%% + %2$s',
						'woocommerce-payments'
				  )
				: __(
						/* translators: %1$s% is the fee amount */
						'Tap to pay transaction fee: %1$s%%',
						'woocommerce-payments'
				  ),
		discount: __( 'Discount', 'woocommerce-payments' ),
	} );

	const storeCurrency = event.transaction_details?.store_currency;
	const feeHistoryStrings = {};
	history.forEach( ( fee ) => {
		let labelType = fee.type;
		if ( fee.additional_type ) {
			labelType += `-${ fee.additional_type }`;
		}

		const {
			percentage_rate: percentageRate,
			fixed_rate: fixedRate,
			currency,
			capped: isCapped,
		} = fee;

		const percentageRateFormatted = formatFee( percentageRate );
		const fixedRateFormatted = `${ formatCurrency(
			fixedRate,
			currency,
			storeCurrency
		) }${
			hasSameSymbol(
				event.transaction_details.store_currency,
				event.transaction_details.customer_currency
			)
				? ` ${ currency.toUpperCase() }`
				: ''
		}`;

		const label = sprintf(
			feeLabelMapping( fixedRate, isCapped )[ labelType ],
			percentageRateFormatted,
			fixedRateFormatted
		);

		if ( labelType === 'discount' ) {
			feeHistoryStrings[ labelType ] = {
				label,
				variable:
					sprintf(
						/* translators: %s is a percentage number */
						__( 'Variable fee: %s', 'woocommerce-payments' ),
						percentageRateFormatted
					) + '%',
				fixed: sprintf(
					/* translators: %s is a monetary amount */
					__( 'Fixed fee: %s', 'woocommerce-payments' ),
					fixedRateFormatted
				),
			};
		} else {
			feeHistoryStrings[ labelType ] = label;
		}
	} );

	return feeHistoryStrings;
};

export const composeFeeBreakdown = ( event ) => {
	const feeHistoryStrings = feeBreakdown( event );

	if ( typeof feeHistoryStrings !== 'object' ) {
		return;
	}

	const renderDiscountSplit = ( discount ) => {
		return (
			<ul className="discount-split-list">
				<li key="variable">{ discount.variable }</li>
				<li key="fixed">{ discount.fixed }</li>
			</ul>
		);
	};

	const list = Object.keys( feeHistoryStrings ).map( ( labelType ) => {
		const fee = feeHistoryStrings[ labelType ];
		return (
			<li key={ labelType }>
				{ labelType === 'discount' ? fee.label : fee }

				{ labelType === 'discount' && renderDiscountSplit( fee ) }
			</li>
		);
	} );

	return <ul className="fee-breakdown-list"> { list } </ul>;
};

const getManualFraudOutcomeTimelineItem = ( event, status ) => {
	const isBlock = status === 'block';

	const headline = isBlock
		? // translators: %s: the username that approved the payment, <a> - link to the user
		  __( 'Payment was blocked by <a>%s</a>', 'woocommerce-payments' )
		: // translators: %s: the username that approved the payment, <a> - link to the user
		  __( 'Payment was approved by <a>%s</a>', 'woocommerce-payments' );

	const icon = isBlock ? (
		<CrossIcon className="is-error" />
	) : (
		<CheckmarkIcon className="is-success" />
	);

	return [
		getMainTimelineItem(
			event,
			createInterpolateElement(
				sprintf( headline, event.user.username ),
				{
					a: (
						// eslint-disable-next-line jsx-a11y/anchor-has-content
						<a
							href={ addQueryArgs( 'user-edit.php', {
								user_id: event.user.id,
							} ) }
							tabIndex={ -1 }
						/>
					),
				}
			),
			icon
		),
	];
};

const buildAutomaticFraudOutcomeRuleset = ( event ) => {
	const rulesetResults = Object.entries( event.ruleset_results || {} );

	return rulesetResults
		.filter( ( [ , status ] ) => status !== 'allow' )
		.map( ( [ rule, status ] ) => (
			<p key={ rule } className="fraud-outcome-ruleset-item">
				{ fraudOutcomeRulesetMapping[ status ][ rule ] }
			</p>
		) );
};

const getAutomaticFraudOutcomeTimelineItem = ( event, status ) => {
	const isBlock = status === 'block';

	const headline = isBlock
		? __(
				'Payment was screened by your fraud filters and blocked.',
				'woocommerce-payments'
		  )
		: __(
				'Payment was screened by your fraud filters and placed in review.',
				'woocommerce-payments'
		  );

	const icon = isBlock ? (
		<CrossIcon className="is-error" />
	) : (
		<ShieldIcon className="is-fraud-outcome-review" />
	);

	return [
		getMainTimelineItem(
			event,
			headline,
			icon,
			buildAutomaticFraudOutcomeRuleset( event )
		),
	];
};

/**
 * Formats an event into one or more payment timeline items
 *
 * @param {Object}        event    An event data
 * @param {string | null} bankName The name of the bank
 *
 * @return {Array} Payment timeline items
 */
const mapEventToTimelineItems = ( event, bankName = null ) => {
	const { type } = event;

	const stringWithAmount = ( headline, amount, explicit = false ) =>
		sprintf(
			headline,
			explicit
				? formatExplicitCurrency( amount, event.currency )
				: formatCurrency( amount, event.currency )
		);

	switch ( type ) {
		case 'started':
			return [
				getStatusChangeTimelineItem(
					event,
					__( 'Started', 'woocommerce-payments' )
				),
			];
		case 'authorized':
			return [
				getStatusChangeTimelineItem(
					event,
					__( 'Authorized', 'woocommerce-payments' )
				),
				getMainTimelineItem(
					event,
					stringWithAmount(
						/* translators: %s is a monetary amount */
						__(
							'A payment of %s was successfully authorized.',
							'woocommerce-payments'
						),
						event.amount,
						true
					),
					<CheckmarkIcon className="is-warning" />
				),
			];
		case 'authorization_voided':
			return [
				getStatusChangeTimelineItem(
					event,
					__( 'Authorization voided', 'woocommerce-payments' )
				),
				getMainTimelineItem(
					event,
					stringWithAmount(
						__(
							/* translators: %s is a monetary amount */
							'Authorization for %s was voided.',
							'woocommerce-payments'
						),
						event.amount,
						true
					),
					<CheckmarkIcon className="is-warning" />
				),
			];
		case 'authorization_expired':
			return [
				getStatusChangeTimelineItem(
					event,
					__( 'Authorization expired', 'woocommerce-payments' )
				),
				getMainTimelineItem(
					event,
					stringWithAmount(
						__(
							/* translators: %s is a monetary amount */
							'Authorization for %s expired.',
							'woocommerce-payments'
						),
						event.amount,
						true
					),
					<CrossIcon className="is-error" />
				),
			];
		case 'captured':
			const formattedNet = formatNetString( event );
			const body = event.fee_breakdown
				? composeCapturedBodyFromBreakdown( event )
				: [
						composeFXString( event ),
						composeFeeString( event ),
						composeFeeBreakdown( event ),
						event?.fee_rates?.tax?.amount !== 0
							? composeTaxString( event )
							: null,
						composeNetString( event ),
				  ].filter( Boolean );
			return [
				getStatusChangeTimelineItem(
					event,
					__( 'Paid', 'woocommerce-payments' )
				),
				getDepositTimelineItem( event, formattedNet, true ),
				getMainTimelineItem(
					event,
					stringWithAmount(
						/* translators: %s is a monetary amount */
						__(
							'A payment of %s was successfully charged.',
							'woocommerce-payments'
						),
						event.amount_captured,
						true
					),
					<CheckmarkIcon className="is-success" />,
					body
				),
			];
		case 'partial_refund':
		case 'full_refund':
			const formattedAmount = formatExplicitCurrency(
				event.amount_refunded,
				event.currency
			);
			const depositAmount = isFXEvent( event )
				? formatExplicitCurrency(
						event.transaction_details.store_amount,
						event.transaction_details.store_currency
				  )
				: formattedAmount;
			return [
				getStatusChangeTimelineItem(
					event,
					type === 'full_refund'
						? __( 'Refunded', 'woocommerce-payments' )
						: __( 'Partial refund', 'woocommerce-payments' )
				),
				getDepositTimelineItem( event, depositAmount, false ),
				getMainTimelineItem(
					event,
					sprintf(
						__(
							/* translators: %s is a monetary amount */
							'A payment of %s was successfully refunded.',
							'woocommerce-payments'
						),
						formattedAmount
					),
					<CheckmarkIcon className="is-success" />,
					[
						composeFXString( event ),
						getRefundTrackingDetails( event ),
					]
				),
			];
		case 'refund_failed':
			const formattedRefundFailureAmount = formatExplicitCurrency(
				event.amount_refunded,
				event.currency
			);
			return [
				getMainTimelineItem(
					event,
					sprintf(
						__(
							/* translators: %s is a monetary amount */
							'%s refund was attempted but failed due to %s',
							'woocommerce-payments'
						),
						formattedRefundFailureAmount,
						getRefundFailureReason( event )
					),
					<NoticeOutlineIcon className="is-error" />,
					[ getRefundTrackingDetails( event ) ]
				),
			];
		case 'failed':
			const paymentFailureMessage =
				paymentFailureMapping[ event.reason ] ||
				paymentFailureMapping.default;

			return [
				getStatusChangeTimelineItem(
					event,
					__( 'Failed', 'woocommerce-payments' )
				),
				getMainTimelineItem(
					event,
					sprintf(
						/* translators: %1$s is the payment amount, %2$s is the failure reason message */
						__(
							'A payment of %1$s failed: %2$s.',
							'woocommerce-payments'
						),
						formatExplicitCurrency( event.amount, event.currency ),
						paymentFailureMessage
					),
					<CrossIcon className="is-error" />
				),
			];
		case 'dispute_needs_response':
			let reasonHeadline = __(
				'Payment disputed',
				'woocommerce-payments'
			);
			if ( disputeReasons[ event.reason ] ) {
				reasonHeadline = sprintf(
					/* translators: %s is a monetary amount */
					__( 'Payment disputed as %s.', 'woocommerce-payments' ),
					disputeReasons[ event.reason ].display
				);
			}

			let depositTimelineItem;
			if ( event.amount === null ) {
				depositTimelineItem = {
					date: new Date( event.datetime * 1000 ),
					icon: <InfoOutlineIcon />,
					headline: __(
						'No funds have been withdrawn yet.',
						'woocommerce-payments'
					),
					body: [
						__(
							// eslint-disable-next-line max-len
							"The cardholder's bank is requesting more information to decide whether to return these funds to the cardholder.",
							'woocommerce-payments'
						),
					],
				};
			} else {
				// Prefer the envelope's authoritative deposit impact
				// (totals.net, signed); fall back to |amount|+|fee|.
				const depositImpact =
					event.fee_breakdown?.totals?.net?.amount !== undefined
						? Math.abs( event.fee_breakdown.totals.net.amount )
						: Math.abs( event.amount ) + Math.abs( event.fee );
				const formattedExplicitTotal = formatExplicitCurrency(
					depositImpact,
					event.fee_breakdown?.totals?.net?.currency ||
						event.currency
				);
				const disputedAmount = isFXEvent( event )
					? formatCurrency(
							event.transaction_details.customer_amount,
							event.transaction_details.customer_currency
					  )
					: formatCurrency( event.amount, event.currency );
				depositTimelineItem = getDepositTimelineItem(
					event,
					formattedExplicitTotal,
					false,
					[
						sprintf(
							/* translators: %s is a monetary amount */
							__( 'Disputed amount: %s', 'woocommerce-payments' ),
							disputedAmount
						),
						composeFXString( event ),
						sprintf(
							/* translators: %s is a monetary amount */
							__( 'Fee: %s', 'woocommerce-payments' ),
							formatCurrency( event.fee, event.currency )
						),
					]
				);
			}

			return [
				getStatusChangeTimelineItem(
					event,
					__( 'Disputed: Needs response', 'woocommerce-payments' )
				),
				depositTimelineItem,
				getMainTimelineItem(
					event,
					reasonHeadline,
					<CrossIcon className="is-error" />
				),
			];
		case 'dispute_in_review':
			return [
				getStatusChangeTimelineItem(
					event,
					__( 'Disputed: In review', 'woocommerce-payments' )
				),
				getMainTimelineItem(
					event,
					__(
						'Challenge evidence submitted.',
						'woocommerce-payments'
					),
					<CheckmarkIcon className="is-success" />
				),
			];
		case 'dispute_won':
			// Prefer the envelope's authoritative deposit impact (totals.net,
			// signed); fall back to |amount|+|fee|.
			const depositImpactWon =
				event.fee_breakdown?.totals?.net?.amount !== undefined
					? Math.abs( event.fee_breakdown.totals.net.amount )
					: Math.abs( event.amount ) + Math.abs( event.fee );
			const formattedExplicitTotal = formatExplicitCurrency(
				depositImpactWon,
				event.fee_breakdown?.totals?.net?.currency || event.currency
			);
			return [
				getStatusChangeTimelineItem(
					event,
					__( 'Disputed: Won', 'woocommerce-payments' )
				),
				getDepositTimelineItem( event, formattedExplicitTotal, true, [
					sprintf(
						/* translators: %s is a monetary amount */
						__( 'Dispute reversal: %s', 'woocommerce-payments' ),
						formatCurrency( event.amount, event.currency )
					),
					sprintf(
						/* translators: %s is a monetary amount */
						__( 'Fee refund: %s', 'woocommerce-payments' ),
						formatCurrency( Math.abs( event.fee ), event.currency )
					),
				] ),
				getMainTimelineItem(
					event,
					__(
						'Dispute won! The bank ruled in your favor.',
						'woocommerce-payments'
					),
					<NoticeOutlineIcon className="is-success" />
				),
			];
		case 'dispute_lost': {
			const networkCost = event?.network_cost;
			const formattedNetworkCost =
				networkCost?.amount != null && networkCost?.currency
					? formatExplicitCurrency(
							networkCost.amount,
							networkCost.currency.toUpperCase(),
							false,
							event?.fee?.currency?.toUpperCase()
					  )
					: '';
			const isCrossCurrencyNetworkCost =
				networkCost &&
				event.currency?.toLowerCase() !==
					networkCost.currency?.toLowerCase();
			const networkCostAmount = isCrossCurrencyNetworkCost
				? sprintf(
						// translators: %s - formatted network cost amount with currency code
						__(
							'%s in your account currency',
							'woocommerce-payments'
						),
						formattedNetworkCost
				  )
				: formattedNetworkCost;
			const networkCostItem =
				networkCost?.amount != null && networkCost?.currency
					? getDepositTimelineItem( event, networkCostAmount, false, [
							event?.reason === 'noncompliant'
								? __(
										'Network costs associated with resolving Visa compliance disputes.',
										'woocommerce-payments'
								  )
								: __(
										'Network cost for the dispute.',
										'woocommerce-payments'
								  ),
					  ] )
					: null;

			let headlineText;
			if ( event.reason === 'noncompliant' ) {
				headlineText = __(
					// eslint-disable-next-line max-len
					"<strong>Dispute lost.</strong> Visa reviewed the evidence and decided in the customer's favor.",
					'woocommerce-payments'
				);
			} else {
				headlineText = bankName
					? sprintf(
							__(
								// eslint-disable-next-line max-len
								"<strong>Dispute lost.</strong> Your customer's bank, <strong>%s</strong>, reviewed the evidence and decided in the customer's favor.",
								'woocommerce-payments'
							),
							bankName
					  )
					: __(
							// eslint-disable-next-line max-len
							"<strong>Dispute lost.</strong> Your customer's bank reviewed the evidence and decided in the customer's favor.",
							'woocommerce-payments'
					  );
			}

			return [
				networkCostItem,
				getStatusChangeTimelineItem(
					event,
					__( 'Disputed: Lost', 'woocommerce-payments' )
				),
				getMainTimelineItem(
					event,
					createInterpolateElement( headlineText, {
						strong: <strong />,
					} ),
					<CrossIcon className="is-error" />
				),
			];
		}
		case 'dispute_warning_closed':
			return [
				getMainTimelineItem(
					event,
					__(
						'Dispute inquiry closed. The bank chose not to pursue this dispute.',
						'woocommerce-payments'
					),
					<NoticeOutlineIcon className="is-success" />
				),
			];
		case 'dispute_charge_refunded':
			return [
				getMainTimelineItem(
					event,
					__(
						'The disputed charge has been refunded.',
						'woocommerce-payments'
					),
					<NoticeOutlineIcon className="is-success" />
				),
			];
		case 'financing_paydown':
			return [
				getFinancingPaydownTimelineItem(
					event,
					formatCurrency( Math.abs( event.amount ) ),
					[
						createInterpolateElement(
							sprintf(
								__(
									'Loan repayment: <a>Loan %s</a>',
									'woocommerce-payments'
								),
								event.loan_id
							),
							{
								a: (
									<Link
										href={ getAdminUrl( {
											page: 'wc-admin',
											path: '/payments/transactions',
											type: 'charge',
											filter: 'advanced',
											loan_id_is: event.loan_id,
										} ) }
									/>
								),
							}
						),
					]
				),
			];
		case 'fraud_outcome_manual_approve':
			return getManualFraudOutcomeTimelineItem( event, 'allow' );
		case 'fraud_outcome_manual_block':
			return getManualFraudOutcomeTimelineItem( event, 'block' );
		case 'fraud_outcome_review':
			return getAutomaticFraudOutcomeTimelineItem( event, 'review' );
		case 'fraud_outcome_block':
			return getAutomaticFraudOutcomeTimelineItem( event, 'block' );
		default:
			return [];
	}
};

/**
 * Maps the timeline events coming from the server to items that can be used in Timeline component
 *
 * @param {Array}         timelineEvents array of events
 * @param {string | null} bankName       The name of the bank
 *
 * @return {Array} Array of view items
 */
export default ( timelineEvents, bankName = null ) => {
	if ( ! timelineEvents ) {
		return [];
	}

	return flatMap( timelineEvents, ( event ) =>
		mapEventToTimelineItems( event, bankName )
	).filter( Boolean );
};
