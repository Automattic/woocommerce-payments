/** @format **/

/**
 * External dependencies
 */
import React from 'react';
import { __ } from '@wordpress/i18n';
import { filter, flatten, join } from 'lodash';

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
import './style.scss';

interface PaymentTransactionBreakdownProps {
	paymentIntentId: string;
}

const PaymentTransactionBreakdown: React.FC< PaymentTransactionBreakdownProps > = ( {
	paymentIntentId,
} ) => {
	const { timeline, timelineError, isLoading } = useTimeline(
		paymentIntentId
	);

	let captureEvents: TimelineItem[] = [];

	if ( timeline ) {
		captureEvents = filter( timeline, function ( item: TimelineItem ) {
			return item.type === 'captured';
		} );
	}

	let captureEvent: TimelineItem | undefined;
	if ( captureEvents.length > 0 ) {
		captureEvent = captureEvents[ 0 ];
	}

	if (
		undefined === captureEvent ||
		undefined === captureEvent.transaction_details ||
		undefined === captureEvent.fee_rates
	) {
		return <div />;
	}

	const formattedStoreAmount =
		formatCurrency(
			captureEvent.transaction_details.store_amount,
			captureEvent.transaction_details.store_currency
		) +
		' ' +
		captureEvent.transaction_details.store_currency;

	const formattedCustomerAmount =
		formatCurrency(
			captureEvent.transaction_details.customer_amount,
			captureEvent.transaction_details.customer_currency,
			captureEvent.transaction_details.store_currency
		) +
		' ' +
		captureEvent.transaction_details.customer_currency;

	const isMultiCurrency =
		captureEvent.transaction_details.store_currency !==
		captureEvent.transaction_details.customer_currency;

	const formattedAmount =
		formattedCustomerAmount +
		( isMultiCurrency ? ` → ${ formattedStoreAmount }` : '' );

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

	function formatFeeType(
		type: string,
		additionalType: string | undefined
	): string {
		if ( 'total' === type ) {
			return __( 'Total transaction fee', 'woocommerce-payments' );
		}
		if ( 'base' === type ) {
			return __( 'Base fee', 'woocommerce-payments' );
		}
		if ( 'additional' === type && 'international' === additionalType ) {
			return __( 'International card fee', 'woocommerce-payments' );
		}
		if ( 'additional' === type && 'fx' === additionalType ) {
			return __( 'Currency conversion fee', 'woocommerce-payments' );
		}
		return __( 'Fee', 'woocommerce-payments' );
	}

	function formatFeeRate(
		percentage: number,
		fixed: number,
		currency: string,
		storeCurrency: string
	): string {
		const formattedPercentage = percentage
			? Math.round( percentage * 10000 ) / 100 + '%'
			: '';
		const formattedFixed = fixed
			? formatCurrency( fixed, currency, storeCurrency )
			: '';
		return join(
			filter( [ formattedPercentage, formattedFixed ], Boolean ),
			' + '
		);
	}

	function formatFee(
		type: string,
		additionalType: string | undefined,
		percentage: number,
		fixed: number,
		currency: string,
		storeCurrency: string,
		amount?: number
	): JSX.Element[] {
		const formattedFeeType = formatFeeType( type, additionalType );
		const formattedFeeRate = formatFeeRate(
			percentage,
			fixed,
			currency,
			storeCurrency
		);
		const formattedFeeAmount = amount
			? ' - ' + formatCurrency( amount, storeCurrency, storeCurrency )
			: '';

		return [
			<Flex
				key="{ type }_fee_info"
				className={ `wcpay-transaction-breakdown__fee_info wcpay-transaction-breakdown__${ type }_fee_info ` }
			>
				<FlexItem
					key="{ type }"
					className="wcpay-transaction-breakdown__fee_name"
				>
					{ formattedFeeType }
				</FlexItem>
				<FlexItem
					key="{ type }_fee"
					className="wcpay-transaction-breakdown__fee_rate"
				>
					{ formattedFeeRate }
				</FlexItem>
				<FlexItem
					key="{ type }_amount"
					className="wcpay-transaction-breakdown__fee_amount"
				>
					{ formattedFeeAmount }
				</FlexItem>
			</Flex>,
		];
	}

	function formatFees( event: TimelineItem ): JSX.Element[] {
		if (
			undefined === event.fee_rates ||
			undefined === event.transaction_details
		) {
			return [];
		}

		const storeCurrency = event.transaction_details.store_currency;

		const fees = [];

		if ( undefined === event.fee_rates.history ) {
			fees.push(
				formatFee(
					'base',
					'',
					event.fee_rates.percentage,
					event.fee_rates.fixed,
					event.fee_rates.fixed_currency,
					storeCurrency
				)
			);
		} else {
			event.fee_rates.history.map( ( fee: TimelineFeeRate ) =>
				fees.push(
					formatFee(
						fee.type,
						fee.additional_type,
						fee.percentage_rate,
						fee.fixed_rate,
						fee.currency,
						storeCurrency
					)
				)
			);
		}

		fees.push(
			formatFee(
				'total',
				'',
				event.fee_rates.percentage,
				event.fee_rates.fixed / feeExchangeRate,
				storeCurrency,
				storeCurrency,
				event.transaction_details.store_fee
			)
		);

		return flatten( fees );
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
					{ timelineError instanceof Error ? (
						[
							__(
								'Error while loading transaction breakdown',
								'woocommerce-payments'
							),
						]
					) : (
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
					) }
				</LoadableBlock>
			</CardBody>
			<CardFooter>
				<LoadableBlock isLoading={ isLoading } numLines={ 1 }>
					{ timelineError instanceof Error ? (
						[]
					) : (
						<Flex className="wcpay-transaction-breakdown__footer">
							<FlexItem>
								{ __( 'Net deposit', 'woocommerce-payments' ) }
							</FlexItem>
							<FlexItem className="wcpay-transaction-breakdown__footer_amount">
								{ formatCurrency(
									captureEvent.transaction_details
										.store_amount_captured -
										captureEvent.transaction_details
											.store_fee,
									captureEvent.transaction_details
										.store_currency
								) }
							</FlexItem>
						</Flex>
					) }
				</LoadableBlock>
			</CardFooter>
		</Card>
	) : (
		<span />
	);
};

export default PaymentTransactionBreakdown;
