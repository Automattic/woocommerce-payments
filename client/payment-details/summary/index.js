/** @format **/

/**
 * External dependencies
 */
import { __ } from '@wordpress/i18n';
import { dateI18n } from '@wordpress/date';
import { Card } from '@woocommerce/components';
import Currency from '@woocommerce/currency';
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
import './style.scss';

const currency = new Currency();

const placeholderValues = {
	net: 0,
	fee: 0,
	refunded: null,
};

const composePaymentSummaryItems = ( { charge } ) =>
	[
		{
			title: __( 'Date', 'woocommerce-payments' ),
			content: charge.created
				? dateI18n( 'M j, Y, g:ia', moment( charge.created * 1000 ) )
				: '–',
		},
		{
			title: __( 'Customer', 'woocommerce-payments' ),
			content: get( charge, 'billing_details.name' ) || '–',
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
	const { net, fee, refunded } = charge.amount
		? getChargeAmounts( charge )
		: placeholderValues;

	return (
		<Card className="payment-details-summary-details">
			<div className="payment-details-summary">
				<div className="payment-details-summary__section">
					<p className="payment-details-summary__amount">
						<Loadable
							isLoading={ isLoading }
							placeholder="Amount placeholder"
						>
							{ currency.formatCurrency(
								( charge.amount || 0 ) / 100
							) }
							<span className="payment-details-summary__amount-currency">
								{ charge.currency || 'cur' }
							</span>
							<PaymentStatusChip
								status={ getChargeStatus( charge ) }
							/>
						</Loadable>
					</p>
					<div className="payment-details-summary__breakdown">
						{ refunded ? (
							<p>
								{ `${ __(
									'Refunded',
									'woocommerce-payments'
								) }: ` }
								{ currency.formatCurrency( -refunded / 100 ) }
							</p>
						) : (
							''
						) }
						<p>
							<Loadable
								isLoading={ isLoading }
								placeholder="Fee amount"
							>
								{ `${ __( 'Fee', 'woocommerce-payments' ) }: ` }
								{ currency.formatCurrency( -fee / 100 ) }
							</Loadable>
						</p>
						<p>
							<Loadable
								isLoading={ isLoading }
								placeholder="Net amount"
							>
								{ `${ __( 'Net', 'woocommerce-payments' ) }: ` }
								{ currency.formatCurrency( net / 100 ) }
							</Loadable>
						</p>
					</div>
				</div>
				<div className="payment-details-summary__section">
					<div className="payment-details-summary__id">
						<Loadable
							isLoading={ isLoading }
							placeholder="Payment ID: ch_xxxxxxxxxxxxxxxxxxxxxxxx"
						>
							{ `${ __(
								'Payment ID',
								'woocommerce-payments'
							) }: ` }
							{ charge.id }
						</Loadable>
					</div>
				</div>
			</div>
			<hr className="full-width" />
			<LoadableBlock isLoading={ isLoading } numLines={ 4 }>
				<HorizontalList
					items={ composePaymentSummaryItems( { charge } ) }
				/>
			</LoadableBlock>
		</Card>
	);
};

export default PaymentDetailsSummary;
