/** @format **/

/**
 * External dependencies
 */
import React from 'react';
import { find } from 'lodash';

/** Internal dependencies */
import { formatCurrency } from 'multi-currency/interface/functions';
import { formatFeeType, formatFeeRate } from '../utils';
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

	const FeeRow: React.FC< {
		type: string;
		additionalType?: string;
		percentage: number;
		fixed: number;
		currency: string;
		amount?: number;
	} > = ( { type, additionalType, percentage, fixed, currency, amount } ) => {
		if ( 'discount' === type ) {
			return null;
		}

		if ( 'base' === type && discountFee ) {
			percentage = percentage + discountFee.percentage_rate;
			fixed = fixed + discountFee.fixed_rate;
		}

		const formattedFeeType = formatFeeType(
			type,
			additionalType,
			'base' === type ? discountFee : undefined
		);
		const formattedFeeRate = formatFeeRate(
			percentage,
			fixed,
			currency,
			storeCurrency
		);
		const formattedFeeAmount = amount
			? ` - ${ formatCurrency( amount, storeCurrency, storeCurrency ) }`
			: '';
		const feeType = type + ( additionalType ? `_${ additionalType }` : '' );

		return (
			<Flex
				className={ `wcpay-transaction-breakdown__fee_info wcpay-transaction-breakdown__${ feeType }_fee_info` }
			>
				<FlexItem className="wcpay-transaction-breakdown__fee_name">
					{ formattedFeeType }
				</FlexItem>
				<FlexItem className="wcpay-transaction-breakdown__fee_rate">
					{ formattedFeeRate }
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
			const feeType =
				fee.type +
				( fee.additional_type ? `_${ fee.additional_type }` : '' );
			fees.push(
				<FeeRow
					key={ feeType }
					type={ fee.type }
					additionalType={ fee.additional_type }
					percentage={ fee.percentage_rate }
					fixed={ fee.fixed_rate }
					currency={ fee.currency }
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
		/>
	);

	return <>{ fees }</>;
};

export default FeesBreakdown;
