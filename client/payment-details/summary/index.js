/** @format **/

/**
 * External dependencies
 */
import { __ } from '@wordpress/i18n';
import { dateI18n } from '@wordpress/date';
import { Card, CardBody, CardFooter } from '@wordpress/components';
import moment from 'moment';
import { get } from 'lodash';

/**
 * Internal dependencies.
 */
import { getChargeAmounts, getChargeStatus } from 'utils/charge';
import PaymentStatusChip from 'components/payment-status-chip';
import PaymentMethodDetails from 'components/payment-method-details';
import HorizontalList from 'components/horizontal-list';
import Loadable, { LoadableBlock } from 'components/loadable';
import riskMappings from 'components/risk-level/strings';
import OrderLink from 'components/order-link';
import { formatCurrency, formatExplicitCurrency } from 'utils/currency';
import CustomerLink from 'components/customer-link';
import './style.scss';

const placeholderValues = {
	amount: 0,
	currency: 'USD',
	net: 0,
	fee: 0,
	refunded: null,
};

const composePaymentSummaryItems = ( { charge } ) =>
	[
		{
			title: __( 'Date', 'woocommerce-payments' ),
			content: charge.created
				? dateI18n(
						'M j, Y, g:ia',
						moment( charge.created * 1000 ).toISOString()
				  )
				: '–',
		},
		{
			title: __( 'Customer', 'woocommerce-payments' ),
			content: <CustomerLink customer={ charge.billing_details } />,
		},
		{
			title: __( 'Order', 'woocommerce-payments' ),
			content: <OrderLink order={ charge.order } />,
		},
		wcpaySettings.isSubscriptionsActive && {
			title: __( 'Subscription', 'woocommerce-payments' ),
			content:
				charge.order && charge.order.subscriptions.length ? (
					charge.order.subscriptions.map(
						( subscription, i, all ) => [
							<OrderLink key={ i } order={ subscription } />,
							i !== all.length - 1 && ', ',
						]
					)
				) : (
					<OrderLink />
				),
		},
		{
			title: __( 'Payment method', 'woocommerce-payments' ),
			content: (
				<PaymentMethodDetails
					payment={ charge.payment_method_details }
				/>
			),
		},
		{
			title: __( 'Risk evaluation', 'woocommerce-payments' ),
			content: riskMappings[ get( charge, 'outcome.risk_level' ) ] || '–',
		},
	].filter( Boolean );

const PaymentDetailsSummary = ( { charge = {}, isLoading } ) => {
	const balance = charge.amount
		? getChargeAmounts( charge )
		: placeholderValues;
	const renderStorePrice =
		charge.currency && balance.currency !== charge.currency;

	return (
		<Card>
			<CardBody>
				<div className="payment-details-summary">
					<div className="payment-details-summary__section">
						<p className="payment-details-summary__amount">
							<Loadable
								isLoading={ isLoading }
								placeholder="Amount placeholder"
							>
								{ formatCurrency(
									charge.amount,
									charge.currency,
									balance.currency
								) }
								<span className="payment-details-summary__amount-currency">
									{ charge.currency || 'USD' }
								</span>
								<PaymentStatusChip
									status={ getChargeStatus( charge ) }
								/>
							</Loadable>
						</p>
						<div className="payment-details-summary__breakdown">
							{ renderStorePrice ? (
								<p>
									{ formatExplicitCurrency(
										balance.amount,
										balance.currency
									) }
								</p>
							) : null }
							{ balance.refunded ? (
								<p>
									{ `${ __(
										'Refunded',
										'woocommerce-payments'
									) }: ` }
									{ formatExplicitCurrency(
										-balance.refunded,
										balance.currency
									) }
								</p>
							) : (
								''
							) }
							<p>
								<Loadable
									isLoading={ isLoading }
									placeholder="Fee amount"
								>
									{ `${ __(
										'Fee',
										'woocommerce-payments'
									) }: ` }
									{ formatCurrency(
										-balance.fee,
										balance.currency
									) }
								</Loadable>
							</p>
							{ charge.paydown &&
							wcpaySettings.featureFlags.capital ? (
								<p>
									{ `${ __(
										'Loan repayment',
										'woocommerce-payments'
									) }: ` }
									{ formatExplicitCurrency(
										charge.paydown.amount,
										balance.currency
									) }
								</p>
							) : (
								''
							) }
							<p>
								<Loadable
									isLoading={ isLoading }
									placeholder="Net amount"
								>
									{ `${ __(
										'Net',
										'woocommerce-payments'
									) }: ` }
									{ formatExplicitCurrency(
										charge.paydown &&
											wcpaySettings.featureFlags.capital
											? balance.net -
													Math.abs(
														charge.paydown.amount
													)
											: balance.net,
										balance.currency
									) }
								</Loadable>
							</p>
						</div>
					</div>
					<div className="payment-details-summary__section">
						<div className="payment-details-summary__id">
							<Loadable
								isLoading={ isLoading }
								placeholder="Payment ID: pi_xxxxxxxxxxxxxxxxxxxxxxxx"
							>
								{ `${ __(
									'Payment ID',
									'woocommerce-payments'
								) }: ` }
								{ charge.payment_intent
									? charge.payment_intent
									: charge.id }
							</Loadable>
						</div>
					</div>
				</div>
			</CardBody>
			<CardFooter>
				<LoadableBlock isLoading={ isLoading } numLines={ 4 }>
					<HorizontalList
						items={ composePaymentSummaryItems( { charge } ) }
					/>
				</LoadableBlock>
			</CardFooter>
		</Card>
	);
};

export default PaymentDetailsSummary;
