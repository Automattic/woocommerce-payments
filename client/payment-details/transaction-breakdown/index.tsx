/** @format **/

/**
 * External dependencies
 */
import React from 'react';
import { __ } from '@wordpress/i18n';
import { find } from 'lodash';

/**
 * Internal dependencies
 */
import { useTimeline } from 'wcpay/data';
import {
	Card,
	CardBody,
	CardHeader,
	CardFooter,
	Flex,
	FlexItem,
} from '@wordpress/components';
import { TimelineItem, TimelineFeeRate } from 'wcpay/data/timeline/types';
import Loadable, { LoadableBlock } from 'components/loadable';
import { formatCurrency } from 'multi-currency/interface/functions';
import { formatFeeType, formatFeeRate } from './utils';
import { useTransactionAmounts } from './hooks';
import './style.scss';

interface PaymentTransactionBreakdownProps {
	paymentIntentId: string;
}

const PaymentTransactionBreakdown: React.FC< PaymentTransactionBreakdownProps > = ( {
	paymentIntentId,
} ) => {
	const { timeline, isLoading } = useTimeline( paymentIntentId );

	/**
	 * Right now there is no support for multi-capture in the WooPayments and
	 * we retrieve information about fees from the first available capture
	 * event. This should be updated if multi capture becomes reality.
	 */
	const captureEvent: TimelineItem | undefined = find(
		timeline,
		( item: TimelineItem ) => item.type === 'captured'
	);

	const transactionAmounts = useTransactionAmounts( captureEvent );

	if (
		! captureEvent?.transaction_details ||
		! captureEvent?.fee_rates ||
		! transactionAmounts
	) {
		return null;
	}

	const { formattedAmount, isMultiCurrency } =  useTransactionAmounts( captureEvent );

	const feeExchangeRate = captureEvent.fee_rates.fee_exchange_rate?.rate || 1;

	const conversionRate = isMultiCurrency ? (
		<FlexItem className="wcpay-transaction-breakdown__conversion_rate">
			{ ' @ 1 ' }
			{ captureEvent.transaction_details.customer_currency }
			{ ' → ' }
			{ Math.round( ( 1 / feeExchangeRate ) * 1000000 ) / 1000000 }
			{ '	' }
			{ captureEvent.transaction_details.store_currency }
		</FlexItem>
	) : (
		''
	);

	const FeeRow: React.FC< {
		type: string;
		additionalType?: string;
		percentage: number;
		fixed: number;
		currency: string;
		storeCurrency: string;
		amount?: number;
	} > = ( {
		type,
		additionalType,
		percentage,
		fixed,
		currency,
		storeCurrency,
		amount,
	} ) => {
		const formattedFeeType = formatFeeType( type, additionalType );
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

	function formatFees( event: TimelineItem ): JSX.Element[] {
		if ( ! event.fee_rates || ! event.transaction_details ) {
			return [];
		}

		const storeCurrency = event.transaction_details.store_currency;

		const fees = [];

		if ( ! event.fee_rates.history ) {
			fees.push(
				<FeeRow
					key="base"
					type="base"
					percentage={ event.fee_rates.percentage }
					fixed={ event.fee_rates.fixed }
					currency={ event.fee_rates.fixed_currency }
					storeCurrency={ storeCurrency }
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
						storeCurrency={ storeCurrency }
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
				storeCurrency={ storeCurrency }
				amount={ event.transaction_details.store_fee }
			/>
		);

		return fees;
	}

	return captureEvent ? (
		<Card size="large">
			<CardHeader>
				<Loadable
					isLoading={ isLoading }
					value={ __(
						'Transaction breakdown',
						'woocommerce-payments'
					) }
				/>
			</CardHeader>
			<CardBody className="wcpay-transaction-breakdown">
				<LoadableBlock isLoading={ isLoading } numLines={ 3 }>
					<Flex direction="column">
						<Flex align="top">
							<FlexItem>
								{ __(
									'Authorized payment',
									'woocommerce-payments'
								) }
							</FlexItem>
							<FlexItem>
								<Flex direction="column">
									<FlexItem>{ formattedAmount }</FlexItem>
									{ conversionRate }
								</Flex>
							</FlexItem>
						</Flex>
						<Flex>
							<FlexItem>
								{ __(
									'Transaction fee',
									'woocommerce-payments'
								) }
							</FlexItem>
						</Flex>
						<Flex
							className="wcpay-transaction-breakdown__fees"
							direction="column"
						>
							{ formatFees( captureEvent ) }
						</Flex>
					</Flex>
				</LoadableBlock>
			</CardBody>
			<CardFooter>
				<LoadableBlock isLoading={ isLoading } numLines={ 1 }>
					<Flex className="wcpay-transaction-breakdown__footer">
						<FlexItem>
							{ __( 'Net deposit', 'woocommerce-payments' ) }
						</FlexItem>
						<FlexItem className="wcpay-transaction-breakdown__footer_amount">
							{ formatCurrency(
								captureEvent.transaction_details
									.store_amount_captured -
									captureEvent.transaction_details.store_fee,
								captureEvent.transaction_details.store_currency
							) }
						</FlexItem>
					</Flex>
				</LoadableBlock>
			</CardFooter>
		</Card>
	) : (
		<span />
	);
};

export default PaymentTransactionBreakdown;
