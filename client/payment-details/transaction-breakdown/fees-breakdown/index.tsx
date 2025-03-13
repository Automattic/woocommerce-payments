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
	}: {
		percentage: number;
		fixed: number;
		currency: string;
		displayFixedPart?: boolean;
	} ) => {
		const formattedPercentage = percentage
			? `${ Number.parseFloat( ( percentage * 100 ).toFixed( 2 ) ) }%`
			: '0%';
		const formattedFixed = formatCurrency( fixed, currency, storeCurrency );

		return (
			<>
				{ formattedPercentage }
				{ ( displayFixedPart || fixed > 0 ) && (
					<>
						{ ' + ' + formattedFixed }&nbsp;{ storeCurrency }
					</>
				) }
			</>
		);
	};

	const FeeRow = ( {
		type,
		additionalType,
		percentage,
		fixed,
		currency,
		amount,
		isDiscounted,
		displayFixedPart,
	}: {
		type: string;
		additionalType?: string;
		percentage: number;
		fixed: number;
		currency: string;
		amount?: number;
		isDiscounted?: boolean;
		displayFixedPart?: boolean;
	} ) => {
		const formattedFeeType = formatFeeType(
			type,
			additionalType,
			isDiscounted
		);
		const formattedFeeAmount =
			undefined !== amount
				? ` - ${ formatCurrency(
						amount,
						storeCurrency,
						storeCurrency
				  ) } ${ storeCurrency }`
				: '';
		const feeType = type + ( additionalType ? `_${ additionalType }` : '' );

		return (
			<Flex
				className={ `wcpay-transaction-breakdown__fee_info wcpay-transaction-breakdown__${ feeType }_fee_info` }
				wrap={ true }
				justify="end"
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
					/>
				</FlexItem>
				<FlexItem className="wcpay-transaction-breakdown__fee_amount">
					{ formattedFeeAmount }
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

	fees.push(
		<FeeRow
			key="total"
			type="total"
			percentage={ event.fee_rates.percentage }
			fixed={ event.fee_rates.fixed / feeExchangeRate }
			currency={ storeCurrency }
			amount={ event.transaction_details.store_fee }
			displayFixedPart={ true }
		/>
	);

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
