/** @format **/

/**
 * External dependencies
 */
import { __ } from '@wordpress/i18n';
import { dateI18n } from '@wordpress/date';
import {
	Card,
	CardBody,
	CardDivider,
	Flex,
	DropdownMenu,
	MenuGroup,
	MenuItem,
} from '@wordpress/components';
import { moreVertical } from '@wordpress/icons';
import moment from 'moment';
import React, { useContext, useState } from 'react';
import { createInterpolateElement } from '@wordpress/element';
import HelpOutlineIcon from 'gridicons/dist/help-outline';
import _ from 'lodash';

/**
 * Internal dependencies.
 */
import {
	getChargeAmounts,
	getChargeStatus,
	getChargeChannel,
	isOnHoldByFraudTools,
} from 'utils/charge';
import isValueTruthy from 'utils/is-value-truthy';
import PaymentStatusChip from 'components/payment-status-chip';
import PaymentMethodDetails from 'components/payment-method-details';
import { HorizontalList, HorizontalListItem } from 'components/horizontal-list';
import Loadable, { LoadableBlock } from 'components/loadable';
import riskMappings from 'components/risk-level/strings';
import OrderLink from 'components/order-link';
import { formatCurrency, formatExplicitCurrency } from 'utils/currency';
import CustomerLink from 'components/customer-link';
import { ClickTooltip } from 'components/tooltip';
import DisputeStatusChip from 'components/dispute-status-chip';
import {
	getDisputeFeeFormatted,
	isAwaitingResponse,
	isRefundable,
} from 'wcpay/disputes/utils';
import { useAuthorization } from 'wcpay/data';
import CaptureAuthorizationButton from 'wcpay/components/capture-authorization-button';
import './style.scss';
import { Charge } from 'wcpay/types/charges';
import { recordEvent } from 'tracks';
import WCPaySettingsContext from '../../settings/wcpay-settings-context';
import { FraudOutcome } from '../../types/fraud-outcome';
import CancelAuthorizationButton from '../../components/cancel-authorization-button';
import { PaymentIntent } from '../../types/payment-intents';
import MissingOrderNotice from 'wcpay/payment-details/summary/missing-order-notice';
import DisputeAwaitingResponseDetails from '../dispute-details/dispute-awaiting-response-details';
import DisputeResolutionFooter from '../dispute-details/dispute-resolution-footer';
import ErrorBoundary from 'components/error-boundary';
import RefundModal from 'wcpay/payment-details/summary/refund-modal';
import CardNotice from 'wcpay/components/card-notice';

declare const window: any;

interface PaymentDetailsSummaryProps {
	isLoading: boolean;
	charge?: Charge;
	metadata?: Record< string, any >;
	fraudOutcome?: FraudOutcome;
	paymentIntent?: PaymentIntent;
}

const placeholderValues = {
	amount: 0,
	currency: 'USD',
	net: 0,
	fee: 0,
	refunded: null,
};

const isTapToPay = ( model: string ) => {
	if ( model === 'COTS_DEVICE' ) {
		return true;
	}

	return false;
};

const getTapToPayChannel = ( platform: string ) => {
	if ( platform === 'ios' ) {
		return __( 'Tap to Pay on iPhone', 'woocommerce-payments' );
	}

	if ( platform === 'android' ) {
		return __( 'Tap to Pay on Android', 'woocommerce-payments' );
	}

	return __( 'Tap to Pay', 'woocommerce-payments' );
};

const composePaymentSummaryItems = ( {
	charge = {} as Charge,
	metadata = {},
}: {
	charge: Charge;
	metadata: Record< string, any >;
} ): HorizontalListItem[] =>
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
					{ isTapToPay( metadata?.reader_model )
						? getTapToPayChannel( metadata?.platform )
						: getChargeChannel(
								charge.payment_method_details?.type
						  ) }
				</span>
			),
		},
		{
			title: __( 'Customer', 'woocommerce-payments' ),
			content: (
				<CustomerLink
					billing_details={ charge.billing_details }
					order_details={ charge.order }
				/>
			),
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
	].filter( isValueTruthy );

const PaymentDetailsSummary: React.FC< PaymentDetailsSummaryProps > = ( {
	charge = {} as Charge,
	metadata = {},
	isLoading,
	paymentIntent,
} ) => {
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

	const isFraudOutcomeReview = isOnHoldByFraudTools( charge, paymentIntent );

	const disputeFee =
		charge.dispute && getDisputeFeeFormatted( charge.dispute );

	// If this transaction is disputed, check if it is refundable.
	const isDisputeRefundable = charge.dispute
		? isRefundable( charge.dispute.status )
		: true;

	// Partial refunds are done through the order page. If order number is not
	// present, partial refund is not possible.
	const isPartiallyRefundable = charge.order && charge.order.number;

	// Control menu only shows refund actions for now. In the future, it may show other actions.
	const showControlMenu =
		charge.captured && ! charge.refunded && isDisputeRefundable;

	// Use the balance_transaction fee if available. If not (e.g. authorized but not captured), use the application_fee_amount.
	const transactionFee = charge.balance_transaction
		? {
				fee: charge.balance_transaction.fee,
				currency: charge.balance_transaction.currency,
		  }
		: {
				fee: charge.application_fee_amount,
				currency: charge.currency,
		  };

	// WP translation strings are injected into Moment.js for relative time terms, since Moment's own translation library increases the bundle size significantly.
	moment.updateLocale( 'en', {
		relativeTime: {
			s: __( 'a second', 'woocommerce-payments' ),
			ss: __( '%d seconds', 'woocommerce-payments' ),
			m: __( 'a minute', 'woocommerce-payments' ),
			mm: __( '%d minutes', 'woocommerce-payments' ),
			h: __( 'an hour', 'woocommerce-payments' ),
			hh: __( '%d hours', 'woocommerce-payments' ),
			d: __( 'a day', 'woocommerce-payments' ),
			dd: __( '%d days', 'woocommerce-payments' ),
		},
	} );

	const formattedAmount = formatCurrency(
		charge.amount,
		charge.currency,
		balance.currency
	);

	const [ isRefundModalOpen, setIsRefundModalOpen ] = useState( false );
	return (
		<Card>
			<CardBody>
				<Flex direction="row" align="start">
					<div className="payment-details-summary">
						<div className="payment-details-summary__section">
							<p className="payment-details-summary__amount">
								<Loadable
									isLoading={ isLoading }
									placeholder="Amount placeholder"
								>
									{ formattedAmount }
									<span className="payment-details-summary__amount-currency">
										{ charge.currency || 'USD' }
									</span>
									{ charge.dispute ? (
										<DisputeStatusChip
											status={ charge.dispute.status }
											dueBy={
												charge.dispute.evidence_details
													?.due_by
											}
											prefixDisputeType={ true }
										/>
									) : (
										<PaymentStatusChip
											status={ getChargeStatus(
												charge,
												paymentIntent
											) }
										/>
									) }
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
										{ `${
											disputeFee
												? __(
														'Deducted',
														'woocommerce-payments'
												  )
												: __(
														'Refunded',
														'woocommerce-payments'
												  )
										}: ` }
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
											'Fees',
											'woocommerce-payments'
										) }: ` }
										{ formatCurrency(
											-balance.fee,
											balance.currency
										) }
										{ disputeFee && (
											<ClickTooltip
												className="payment-details-summary__breakdown__fee-tooltip"
												buttonIcon={
													<HelpOutlineIcon />
												}
												buttonLabel={ __(
													'Fee breakdown',
													'woocommerce-payments'
												) }
												content={
													<>
														<Flex>
															<label>
																{ __(
																	'Transaction fee',
																	'woocommerce-payments'
																) }
															</label>
															<span aria-label="Transaction fee">
																{ formatCurrency(
																	transactionFee.fee,
																	transactionFee.currency
																) }
															</span>
														</Flex>
														<Flex>
															<label>
																{ __(
																	'Dispute fee',
																	'woocommerce-payments'
																) }
															</label>
															<span aria-label="Dispute fee">
																{ disputeFee }
															</span>
														</Flex>
														<Flex>
															<label>
																{ __(
																	'Total fees',
																	'woocommerce-payments'
																) }
															</label>
															<span aria-label="Total fees">
																{ formatCurrency(
																	balance.fee,
																	balance.currency
																) }
															</span>
														</Flex>
													</>
												}
											/>
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
															charge.paydown
																.amount
														)
												: balance.net,
											balance.currency
										) }
									</Loadable>
								</p>
							</div>
						</div>
						<div className="payment-details-summary__section">
							{ ! isLoading && isFraudOutcomeReview && (
								<div className="payment-details-summary__fraud-outcome-action">
									<CancelAuthorizationButton
										orderId={ charge.order?.number || 0 }
										paymentIntentId={
											charge.payment_intent || ''
										}
										onClick={ () => {
											recordEvent(
												'wcpay_fraud_protection_transaction_reviewed_merchant_blocked',
												{
													payment_intent_id:
														charge.payment_intent,
												}
											);
											recordEvent(
												'payments_transactions_details_cancel_charge_button_click',
												{
													payment_intent_id:
														charge.payment_intent,
												}
											);
										} }
									>
										{ __( 'Block transaction' ) }
									</CancelAuthorizationButton>

									<CaptureAuthorizationButton
										buttonIsPrimary
										orderId={ charge.order?.number || 0 }
										paymentIntentId={
											charge.payment_intent || ''
										}
										buttonIsSmall={ false }
										onClick={ () => {
											recordEvent(
												'wcpay_fraud_protection_transaction_reviewed_merchant_approved',
												{
													payment_intent_id:
														charge.payment_intent,
												}
											);
											recordEvent(
												'payments_transactions_details_capture_charge_button_click',
												{
													payment_intent_id:
														charge.payment_intent,
												}
											);
										} }
									>
										{ __( 'Approve Transaction' ) }
									</CaptureAuthorizationButton>
								</div>
							) }
							<div className="payment-details-summary__id">
								<Loadable
									isLoading={ isLoading }
									placeholder="Payment ID: pi_xxxxxxxxxxxxxxxxxxxxxxxx"
								>
									{ charge.payment_intent && (
										<div className="payment-details-summary__id_wrapper">
											<span className="payment-details-summary__id_label">
												{ `${ __(
													'Payment ID',
													'woocommerce-payments'
												) }: ` }
											</span>
											<span className="payment-details-summary__id_value">
												{ charge.payment_intent }
											</span>
										</div>
									) }
									{ charge.id && (
										<div className="payment-details-summary__id_wrapper">
											<span className="payment-details-summary__id_label">
												{ `${ __(
													'Charge ID',
													'woocommerce-payments'
												) }: ` }
											</span>
											<span className="payment-details-summary__id_value">
												{ charge.id }
											</span>
										</div>
									) }
								</Loadable>
							</div>
						</div>
					</div>
					<div className="payment-details__refund-controls">
						{ showControlMenu && (
							<Loadable
								isLoading={ isLoading }
								placeholder={ moreVertical }
							>
								<DropdownMenu
									icon={ moreVertical }
									label={ __(
										'Transaction actions',
										'woocommerce-payments'
									) }
									popoverProps={ {
										position: 'bottom left',
									} }
									className="refund-controls__dropdown-menu"
								>
									{ ( { onClose } ) => (
										<MenuGroup>
											<MenuItem
												onClick={ () => {
													setIsRefundModalOpen(
														true
													);
													recordEvent(
														'payments_transactions_details_refund_modal_open',
														{
															payment_intent_id:
																charge.payment_intent,
														}
													);
													onClose();
												} }
											>
												{ __(
													'Refund in full',
													'woocommerce-payments'
												) }
											</MenuItem>
											{ isPartiallyRefundable && (
												<MenuItem
													onClick={ () => {
														recordEvent(
															'payments_transactions_details_partial_refund',
															{
																payment_intent_id:
																	charge.payment_intent,
																order_id:
																	charge.order
																		?.number,
															}
														);
														window.location =
															charge.order?.url;
													} }
												>
													{ __(
														'Partial refund',
														'woocommerce-payments'
													) }
												</MenuItem>
											) }
										</MenuGroup>
									) }
								</DropdownMenu>
							</Loadable>
						) }
					</div>
				</Flex>
			</CardBody>
			<CardDivider />
			<CardBody>
				<LoadableBlock isLoading={ isLoading } numLines={ 4 }>
					<HorizontalList
						items={ composePaymentSummaryItems( {
							charge,
							metadata,
						} ) }
					/>
				</LoadableBlock>
			</CardBody>

			{ charge.dispute && (
				<ErrorBoundary>
					{ isAwaitingResponse( charge.dispute.status ) ? (
						<DisputeAwaitingResponseDetails
							dispute={ charge.dispute }
							customer={ charge.billing_details }
							chargeCreated={ charge.created }
							orderUrl={ charge.order?.url }
						/>
					) : (
						<DisputeResolutionFooter dispute={ charge.dispute } />
					) }
				</ErrorBoundary>
			) }
			{ isRefundModalOpen && (
				<RefundModal
					charge={ charge }
					formattedAmount={ formattedAmount }
					onModalClose={ () => {
						setIsRefundModalOpen( false );
						recordEvent(
							'payments_transactions_details_refund_modal_close',
							{
								payment_intent_id: charge.payment_intent,
							}
						);
					} }
				/>
			) }
			{ ! _.isEmpty( charge ) && ! charge.order && ! isLoading && (
				<MissingOrderNotice
					charge={ charge }
					isLoading={ isLoading }
					onButtonClick={ () => setIsRefundModalOpen( true ) }
				/>
			) }
			{ isAuthAndCaptureEnabled &&
				authorization &&
				! authorization.captured && (
					<Loadable isLoading={ isLoading } placeholder="">
						<CardNotice
							actions={
								! isFraudOutcomeReview ? (
									<CaptureAuthorizationButton
										orderId={ charge.order?.number || 0 }
										paymentIntentId={
											charge.payment_intent || ''
										}
										buttonIsPrimary={ true }
										buttonIsSmall={ false }
										onClick={ () => {
											recordEvent(
												'payments_transactions_details_capture_charge_button_click',
												{
													payment_intent_id:
														charge.payment_intent,
												}
											);
										} }
									/>
								) : (
									<></>
								)
							}
						>
							{ createInterpolateElement(
								__(
									'You must <a>capture</a> this charge within the next',
									'woocommerce-payments'
								),
								{
									a: (
										// eslint-disable-next-line jsx-a11y/anchor-has-content, react/jsx-no-target-blank
										<a
											href="https://woo.com/document/woopayments/settings-guide/authorize-and-capture/#capturing-authorized-orders"
											target="_blank"
											rel="noreferer"
										/>
									),
								}
							) }{ ' ' }
							<abbr
								title={ dateI18n(
									'M j, Y / g:iA',
									moment
										.utc( authorization.created )
										.add( 7, 'days' ),
									'UTC'
								) }
							>
								<b>
									{ moment
										.utc( authorization.created )
										.add( 7, 'days' )
										.fromNow( true ) }
								</b>
							</abbr>
							{ isFraudOutcomeReview &&
								`. ${ __(
									'Approving this transaction will capture the charge.',
									'woocommerce-payments'
								) }` }
						</CardNotice>
					</Loadable>
				) }
		</Card>
	);
};

const PaymentDetailsSummaryWrapper: React.FC< PaymentDetailsSummaryProps > = (
	props
) => (
	<WCPaySettingsContext.Provider value={ window.wcpaySettings }>
		<PaymentDetailsSummary { ...props } />
	</WCPaySettingsContext.Provider>
);

export default PaymentDetailsSummaryWrapper;
