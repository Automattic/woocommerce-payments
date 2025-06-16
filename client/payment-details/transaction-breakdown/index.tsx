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
import { TimelineItem } from 'wcpay/data/timeline/types';
import Loadable, { LoadableBlock } from 'components/loadable';
import { formatCurrency } from 'multi-currency/interface/functions';
import { useTransactionAmounts } from './hooks';
import FeesBreakdown from './fees-breakdown';
import './style.scss';

interface PaymentTransactionBreakdownProps {
	paymentIntentId: string;
}

/**
 * Temporary flag to disable transaction breakdown.
 *
 * Switch to `false` while testing.
 */
const disableTransactionBreakdown = true;

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

	if ( disableTransactionBreakdown ) {
		return null;
	}

	if (
		! captureEvent?.transaction_details ||
		! captureEvent?.fee_rates ||
		! transactionAmounts
	) {
		return null;
	}

	const { formattedAmount, isMultiCurrency } = transactionAmounts;
	// const feeExchangeRate = captureEvent.fee_rates.fee_exchange_rate?.rate || 1;
	const paymentExchangeRate =
		captureEvent.transaction_details.store_amount > 0
			? captureEvent.transaction_details.customer_amount /
			  captureEvent.transaction_details.store_amount
			: 0;

	const conversionRate =
		isMultiCurrency && paymentExchangeRate > 0 ? (
			<FlexItem className="wcpay-transaction-breakdown__conversion_rate">
				{ ' @ 1 ' }
				{ captureEvent.transaction_details.customer_currency }
				{ ' → ' }
				{ Math.round( 1000000 / paymentExchangeRate ) / 1000000 }
				{ '	' }
				{ captureEvent.transaction_details.store_currency }
			</FlexItem>
		) : (
			''
		);

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
						<Flex align="top" wrap={ true }>
							<FlexItem>
								{ __(
									'Authorized payment',
									'woocommerce-payments'
								) }
							</FlexItem>
							<FlexItem className="wcpay-transaction-breakdown__authorized_payment_amount">
								<Flex direction="column" align="end">
									<FlexItem>{ formattedAmount }</FlexItem>
									{ conversionRate }
								</Flex>
							</FlexItem>
						</Flex>
						<Flex
							className="wcpay-transaction-breakdown__fees"
							direction="column"
						>
							<FeesBreakdown event={ captureEvent } />
						</Flex>
						<Flex
							className="wcpay-transaction-breakdown__total_transaction_fee"
							wrap={ true }
						>
							<FlexItem>
								{ __(
									'Total transaction fee',
									'woocommerce-payments'
								) }
							</FlexItem>
							<FlexItem className="wcpay-transaction-breakdown__total_transaction_fee_amount">
								-&nbsp;
								{ formatCurrency(
									captureEvent.transaction_details.store_fee,
									captureEvent.transaction_details
										.store_currency
								) }
								&nbsp;
								{
									captureEvent.transaction_details
										.store_currency
								}
							</FlexItem>
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
							&nbsp;
							{ captureEvent.transaction_details.store_currency }
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
