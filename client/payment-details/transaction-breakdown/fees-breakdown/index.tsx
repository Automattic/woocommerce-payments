/** @format **/

/**
 * External dependencies
 */
import React from 'react';
import { find } from 'lodash';

/** Internal dependencies */
import { formatCurrency } from 'multi-currency/interface/functions';
import { formatFeeType } from '../utils';
import { TimelineItem, TimelineFeeRate } from 'wcpay/data/timeline/types';
import { Flex, FlexItem } from '@wordpress/components';
import { getLocalizedTaxDescription } from '../../utils/tax-descriptions';

interface FeeRowProps {
	type: string;
	additionalType?: string;
	percentage?: number;
	fixed: number;
	currency: string;
	isDiscounted?: boolean;
	displayFixedPart?: boolean;
	taxInfo?: {
		description?: string;
		percentage_rate?: number;
	};
}

interface TaxFeeRowProps {
	description?: string;
	percentageRate?: number;
}

const FeesBreakdown: React.FC< {
	event: TimelineItem;
} > = ( { event } ) => {
	if ( ! event.fee_rates || ! event.transaction_details ) {
		return null;
	}

	const storeCurrency = event.transaction_details.store_currency;
	const feeExchangeRate = event.fee_rates.fee_exchange_rate?.rate || 1;
	const discountFee = event.fee_rates.history
		? find(
				event.fee_rates.history,
				( fee: TimelineFeeRate ) => fee.type === 'discount'
		  )
		: undefined;

	let remainingPercentageDiscount = Math.abs(
		discountFee?.percentage_rate || 0
	);
	let remainingFixedDiscount = Math.abs( discountFee?.fixed_rate || 0 );

	const BreakdownFeeRate = ( {
		percentage,
		fixed,
		currency,
		displayFixedPart,
		isTaxRow,
	}: {
		percentage?: number;
		fixed: number;
		currency: string;
		displayFixedPart?: boolean;
		isTaxRow?: boolean;
	} ) => {
		if ( isTaxRow ) {
			const formattedFixed = formatCurrency(
				fixed,
				currency,
				storeCurrency
			);
			return (
				<>
					{ formattedFixed } { storeCurrency }
				</>
			);
		}

		const formattedPercentage =
			percentage !== undefined
				? `${ Number.parseFloat( ( percentage * 100 ).toFixed( 2 ) ) }%`
				: '0%';
		const formattedFixed = formatCurrency( fixed, currency, storeCurrency );

		const result = [ formattedPercentage ];
		if ( displayFixedPart || fixed > 0 ) {
			result.push( `${ formattedFixed } ${ storeCurrency }` );
		}

		return <>{ result.filter( ( s ) => s !== '' ).join( ' + ' ) }</>;
	};

	const FeeRow = ( {
		type,
		additionalType,
		percentage,
		fixed,
		currency,
		isDiscounted,
		displayFixedPart,
		taxInfo,
		isTaxRow,
	}: FeeRowProps & { isTaxRow?: boolean } ) => {
		const formattedFeeType = formatFeeType(
			type,
			additionalType,
			isDiscounted,
			taxInfo
		);
		const feeType = type + ( additionalType ? `_${ additionalType }` : '' );

		return (
			<Flex
				className={ `wcpay-transaction-breakdown__fee_info wcpay-transaction-breakdown__${ feeType }_fee_info` }
				wrap={ true }
				justify="space-between"
				align="end"
			>
				<FlexItem className="wcpay-transaction-breakdown__fee_name">
					{ formattedFeeType }
				</FlexItem>
				<FlexItem className="wcpay-transaction-breakdown__fee_rate">
					<BreakdownFeeRate
						percentage={ percentage }
						fixed={ fixed }
						currency={ currency }
						displayFixedPart={ displayFixedPart }
						isTaxRow={ isTaxRow }
					/>
				</FlexItem>
			</Flex>
		);
	};

	const TaxFeeRow: React.FC< TaxFeeRowProps > = ( {
		description,
		percentageRate,
	} ) => {
		const formattedFeeType = formatFeeType( 'tax', '', false );
		const localizedDescription = description
			? getLocalizedTaxDescription( description )
			: '';

		return (
			<Flex
				className="wcpay-transaction-breakdown__fee_info wcpay-transaction-breakdown__tax_fee_info"
				wrap={ true }
				justify="space-between"
				align="end"
			>
				<FlexItem className="wcpay-transaction-breakdown__fee_name">
					{ formattedFeeType }
				</FlexItem>
				<FlexItem className="wcpay-transaction-breakdown__fee_rate">
					{ localizedDescription }
					{ percentageRate
						? ` ${ ( percentageRate * 100 ).toFixed( 2 ) }%`
						: '' }
				</FlexItem>
			</Flex>
		);
	};

	const fees = [];

	if ( ! event.fee_rates.history ) {
		fees.push(
			<FeeRow
				key="base"
				type="base"
				percentage={ event.fee_rates.percentage }
				fixed={ event.fee_rates.fixed }
				currency={ event.fee_rates.fixed_currency }
			/>
		);
	} else {
		event.fee_rates.history.map( ( fee: TimelineFeeRate ) => {
			if ( 'discount' === fee.type ) {
				/**
				 * Skip discount fees, because we will subtract discount fees from the other fees in the fee breadown.
				 */
				return null;
			}

			let percentage = fee.percentage_rate;
			let fixed = fee.fixed_rate;
			let isDiscounted = false;
			/**
			 * If fee happens to be fully discounted, but had the fixed part
			 * before discount, we will display the fixed part in the fee
			 * breakdown.
			 */
			const displayFixedPart = fee.fixed_rate > 0;

			/**
			 * For each fee we keep subtracting discount's percentage and
			 * fixed parts until the remaining dicount parts become 0.
			 *
			 * We do this because the fee history contains fees in the
			 * specific order, i.e. base followed by additional fees, and we
			 * want to apply the discount to the fees in the correct order.
			 */
			if ( remainingPercentageDiscount > 0 ) {
				const percentageDiscount = Math.min(
					remainingPercentageDiscount,
					percentage
				);
				percentage = percentage - percentageDiscount;
				remainingPercentageDiscount =
					remainingPercentageDiscount - percentageDiscount;
				isDiscounted = true;
			}

			if ( remainingFixedDiscount > 0 ) {
				const fixedDiscount = Math.min( remainingFixedDiscount, fixed );
				fixed = fixed - fixedDiscount;
				remainingFixedDiscount = remainingFixedDiscount - fixedDiscount;
				isDiscounted = true;
			}

			const feeType =
				fee.type +
				( fee.additional_type ? `_${ fee.additional_type }` : '' );

			fees.push(
				<FeeRow
					key={ feeType }
					type={ fee.type }
					additionalType={ fee.additional_type }
					percentage={ percentage }
					fixed={ fixed }
					currency={ fee.currency }
					isDiscounted={ isDiscounted }
					displayFixedPart={ displayFixedPart }
				/>
			);
			return null;
		} );
	}

	// Calculate total percentage by summing up all non-discount fees
	const totalPercentage = event.fee_rates.percentage;

	// // Total row
	fees.push(
		<FeeRow
			key="total"
			type="total"
			percentage={ totalPercentage }
			fixed={ event.fee_rates.fixed / feeExchangeRate }
			currency={ storeCurrency }
			displayFixedPart={ true }
		/>
	);

	// Tax row.
	if ( event.fee_rates?.tax && event.fee_rates.tax.amount !== 0 ) {
		fees.push(
			<TaxFeeRow
				key="fee_tax"
				description={ event.fee_rates.tax.description }
				percentageRate={ event.fee_rates.tax.percentage_rate }
			/>
		);
	}

	return (
		<div
			className="wcpay-transaction-breakdown__fees-container"
			role="table"
			aria-label="Transaction fees breakdown"
		>
			{ fees }
		</div>
	);
};

export default FeesBreakdown;
