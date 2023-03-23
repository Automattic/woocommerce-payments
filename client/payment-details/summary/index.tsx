/** @format **/

/**
 * External dependencies
 */
import { __ } from '@wordpress/i18n';
import { dateI18n } from '@wordpress/date';
import {
	Card,
	CardBody,
	CardFooter,
	CardDivider,
	Button,
} from '@wordpress/components';
import moment from 'moment';
import React, { useContext } from 'react';

/**
 * Internal dependencies.
 */
import {
	getChargeAmounts,
	getChargeStatus,
	getChargeChannel,
} from 'utils/charge';
import PaymentStatusChip from 'components/payment-status-chip';
import PaymentMethodDetails from 'components/payment-method-details';
import HorizontalList from 'components/horizontal-list';
import Loadable, { LoadableBlock } from 'components/loadable';
import riskMappings from 'components/risk-level/strings';
import OrderLink from 'components/order-link';
import { formatCurrency, formatExplicitCurrency } from 'utils/currency';
import CustomerLink from 'components/customer-link';
import { useAuthorization } from 'wcpay/data';
import CaptureAuthorizationButton from 'wcpay/components/capture-authorization-button';
import './style.scss';
import { Charge } from 'wcpay/types/charges';
import wcpayTracks from 'tracks';
import WCPaySettingsContext from '../../settings/wcpay-settings-context';

declare const window: any;

const placeholderValues = {
	amount: 0,
	currency: 'USD',
	net: 0,
	fee: 0,
	refunded: null,
};

const composePaymentSummaryItems = ( { charge }: { charge: Charge } ) =>
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
			title: __( 'Channel', 'woocommerce-payments' ),
			content: (
				<span>
					{ getChargeChannel( charge.payment_method_details?.type ) }
				</span>
			),
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
			content: charge.order?.subscriptions?.length ? (
				charge.order.subscriptions.map( ( subscription, i, all ) => [
					<OrderLink key={ i } order={ subscription } />,
					i !== all.length - 1 && ', ',
				] )
			) : (
				<OrderLink order={ null } />
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
			content: charge.outcome?.risk_level
				? riskMappings[ charge.outcome.risk_level ]
				: '–',
		},
	].filter( Boolean );

const PaymentDetailsSummary = ( {
	charge,
	isLoading,
}: {
	charge: Charge;
	isLoading: boolean;
} ): JSX.Element => {
	const balance = charge.amount
		? getChargeAmounts( charge )
		: placeholderValues;
	const renderStorePrice =
		charge.currency && balance.currency !== charge.currency;

	const {
		featureFlags: { isAuthAndCaptureEnabled },
	} = useContext( WCPaySettingsContext );

	// We should only fetch the authorization data if the payment is marked for manual capture and it is not already captured.
	// We also need to exclude failed payments and payments that have been refunded, because capture === false in those cases, even
	// if the capture is automatic.
	const shouldFetchAuthorization =
		! charge.captured &&
		charge.status !== 'failed' &&
		charge.amount_refunded === 0 &&
		isAuthAndCaptureEnabled;

	const { authorization } = useAuthorization(
		charge.payment_intent as string,
		charge.order?.number as number,
		shouldFetchAuthorization
	);

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
							{ charge.paydown ? (
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
										charge.paydown
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
						<div className="payment-details-summary__fraud-outcome-action">
							<Button isDestructive>
								{ __( 'Block transaction' ) }
							</Button>
							<Button isPrimary>
								{ __( 'Approve Transaction' ) }
							</Button>
						</div>
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
			<CardDivider />
			<CardBody>
				<LoadableBlock isLoading={ isLoading } numLines={ 4 }>
					<HorizontalList
						items={ composePaymentSummaryItems( { charge } ) }
					/>
				</LoadableBlock>
			</CardBody>
			{ isAuthAndCaptureEnabled &&
				authorization &&
				! authorization.captured && (
					<Loadable isLoading={ isLoading } placeholder="">
						<CardFooter className="payment-details-capture-notice">
							<div className="payment-details-capture-notice__section">
								<div className="payment-details-capture-notice__text">
									{ `${ __(
										'You need to capture this charge before',
										'woocommerce-payments'
									) } ` }
									<b>
										{ dateI18n(
											'M j, Y / g:iA',
											moment
												.utc( authorization.created )
												.add( 7, 'days' )
										) }
									</b>
								</div>
								<div className="payment-details-capture-notice__button">
									<CaptureAuthorizationButton
										orderId={ charge.order?.number || 0 }
										paymentIntentId={
											charge.payment_intent || ''
										}
										buttonIsPrimary={ true }
										buttonIsSmall={ false }
										onClick={ () => {
											wcpayTracks.recordEvent(
												'payments_transactions_details_capture_charge_button_click',
												{
													payment_intent_id:
														charge.payment_intent,
												}
											);
										} }
									/>
								</div>
							</div>
						</CardFooter>
					</Loadable>
				) }
		</Card>
	);
};

export default ( props: {
	charge: Charge;
	isLoading: boolean;
} ): JSX.Element => {
	return (
		<WCPaySettingsContext.Provider value={ window.wcpaySettings }>
			<PaymentDetailsSummary { ...props } />
		</WCPaySettingsContext.Provider>
	);
};
