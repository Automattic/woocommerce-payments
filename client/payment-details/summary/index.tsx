/** @format **/

/**
 * External dependencies
 */
import { __ } from '@wordpress/i18n';
import { moreVertical } from '@wordpress/icons';
import moment from 'moment';
import React, { useEffect, useState } from 'react';
import { createInterpolateElement } from '@wordpress/element';
import HelpOutlineIcon from 'gridicons/dist/help-outline';
import _ from 'lodash';
import {
	CardDivider,
	DropdownMenu,
	MenuGroup,
	MenuItem,
	Card,
	CardBody,
	Flex,
	ExternalLink,
} from '@wordpress/components';

/**
 * Internal dependencies.
 */
import {
	canUseFeeBreakdownData,
	getChargeAmounts,
	getChargeStatus,
	getChargeChannel,
	isOnHoldByFraudTools,
	getBankName,
} from 'utils/charge';
import CardNotice from 'wcpay/components/card-notice';
import isValueTruthy from 'utils/is-value-truthy';
import PaymentStatusChip from 'components/payment-status-chip';
import PaymentMethodDetails from 'components/payment-method-details';
import { HorizontalList, HorizontalListItem } from 'components/horizontal-list';
import Loadable, { LoadableBlock } from 'components/loadable';
import riskMappings from 'components/risk-level/strings';
import OrderLink from 'components/order-link';
import {
	formatCurrency,
	formatExplicitCurrency,
} from 'multi-currency/interface/functions';
import CustomerLink from 'components/customer-link';
import { ClickTooltip } from 'components/tooltip';
import DisputeStatusChip from 'components/dispute-status-chip';
import {
	getDisputeFeeFormatted,
	isAwaitingResponse,
	isRefundable,
} from 'wcpay/disputes/utils';
import { useAuthorization } from 'wcpay/data/authorizations';
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
import DisputeRecommendationsCard from '../dispute-recommendations';
import { getDisputeRecommendations } from '../dispute-recommendations/utils';
import { recordOutcomeViewOnce } from '../dispute-outcome/tracks';
import { resolveProductType } from 'wcpay/disputes/new-evidence/resolve-product-type';
import ErrorBoundary from 'components/error-boundary';
import RefundModal from 'wcpay/payment-details/summary/refund-modal';
import {
	formatDateTimeFromString,
	formatDateTimeFromTimestamp,
} from 'wcpay/utils/date-time';

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
	return model === 'COTS_DEVICE' || model === 'TAP_TO_PAY_DEVICE';
};

const renderDisputeDetails = (
	dispute: NonNullable< Charge[ 'dispute' ] >,
	charge: Charge,
	bankName: string | null
) => {
	if ( isAwaitingResponse( dispute.status ) ) {
		return (
			<DisputeAwaitingResponseDetails
				dispute={ dispute }
				customer={ charge.billing_details }
				chargeCreated={ charge.created }
				orderUrl={ charge.order?.url }
				paymentMethod={ charge.payment_method_details?.type }
				bankName={ bankName }
			/>
		);
	}

	return (
		<DisputeResolutionFooter dispute={ dispute } bankName={ bankName } />
	);
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
				? formatDateTimeFromTimestamp( charge.created, {
						separator: ', ',
						includeTime: true,
				  } )
				: '–',
		},
		{
			title: __( 'Sales channel', 'woocommerce-payments' ),
			content: (
				<span>
					{ isTapToPay( metadata?.reader_model )
						? getTapToPayChannel( metadata?.platform )
						: getChargeChannel(
								charge.payment_method_details?.type,
								metadata
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

const composePaymentSummaryItemsForDispute = ( {
	charge = {} as Charge,
}: {
	charge: Charge;
} ): HorizontalListItem[] =>
	[
		{
			title: __( 'Date', 'woocommerce-payments' ),
			content: charge.created
				? formatDateTimeFromTimestamp( charge.created, {
						customFormat: 'F j, Y g:i A',
				  } )
				: '–',
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
	const displayStatus = getChargeStatus( charge, paymentIntent );

	// Authorization details are only relevant when the payment reached a capturable state.
	const shouldFetchAuthorization =
		! charge.captured &&
		[ 'authorized', 'fraud_outcome_review' ].includes( displayStatus ) &&
		charge.amount_refunded === 0;

	const { authorization } = useAuthorization(
		charge.payment_intent as string,
		charge.order?.id as number,
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

	const isPartiallyRefunded = charge.amount_refunded > 0;

	// Control menu only shows refund actions for now. In the future, it may show other actions.
	const showControlMenu =
		charge.captured && ! charge.refunded && isDisputeRefundable;

	// FEE_BREAKDOWN_FORK_PATCH: remove when envelope is the only path.
	// For the dispute-fee tooltip to reconcile ("Transaction fee" +
	// "Dispute fee" = "Total fees"), `transactionFee.fee` must be the FULL
	// Stripe deduction in store currency (pre-tax fee + tax). Older
	// envelopes may omit `fee_plus_tax`, so sum the two components.
	const breakdown = charge.fee_breakdown_v1;
	const transactionFee = ( () => {
		if ( canUseFeeBreakdownData( charge ) && breakdown?.totals?.fee ) {
			return {
				fee:
					breakdown.totals.fee_plus_tax?.amount ??
					breakdown.totals.fee.amount +
						( breakdown.totals.tax?.amount ?? 0 ),
				currency: breakdown.totals.fee.currency.toLowerCase(),
			};
		}

		if ( charge.balance_transaction ) {
			return {
				fee: charge.balance_transaction.fee,
				currency: charge.balance_transaction.currency,
			};
		}

		return {
			fee: charge.application_fee_amount,
			currency: charge.currency,
		};
	} )();

	// When the envelope is present, `balance.net` (from getChargeAmounts)
	// already reflects paydown — server folded it in. Only subtract manually
	// on the legacy path.
	const netAmount = ( () => {
		if ( charge.fee_breakdown_v1?.totals?.net ) {
			return balance.net;
		}
		if ( charge.paydown ) {
			return balance.net - Math.abs( charge.paydown.amount );
		}
		return balance.net;
	} )();

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

	const bankName = getBankName( charge );

	return (
		<Card>
			<CardBody>
				<Flex direction="row" align="start">
					<div className="payment-details-summary">
						<div className="payment-details-summary__section">
							<div className="payment-details-summary__amount-wrapper">
								<p className="payment-details-summary__amount">
									<Loadable
										isLoading={ isLoading }
										placeholder={ __(
											'Amount placeholder',
											'woocommerce-payments'
										) }
									>
										{ formattedAmount }
										<span className="payment-details-summary__amount-currency">
											{ charge.currency || 'USD' }
										</span>
									</Loadable>
								</p>
								{ charge.dispute ? (
									<DisputeStatusChip
										className="payment-details-summary__status"
										status={ charge.dispute.status }
										prefixDisputeType={ true }
									/>
								) : (
									<PaymentStatusChip
										className="payment-details-summary__status"
										status={ displayStatus }
									/>
								) }
							</div>
							<div className="payment-details-summary__breakdown">
								{ renderStorePrice ? (
									<p className="payment-details-summary__breakdown__settlement-currency">
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
										placeholder={ __(
											'Fee amount',
											'woocommerce-payments'
										) }
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
															{ /* eslint-disable-next-line jsx-a11y/label-has-associated-control */ }
															<label>
																{ __(
																	'Transaction fee',
																	'woocommerce-payments'
																) }
															</label>
															<span
																aria-label={ __(
																	'Transaction fee',
																	'woocommerce-payments'
																) }
															>
																{ formatCurrency(
																	transactionFee.fee,
																	transactionFee.currency
																) }
															</span>
														</Flex>
														<Flex>
															{ /* eslint-disable-next-line jsx-a11y/label-has-associated-control */ }
															<label>
																{ __(
																	'Dispute fee',
																	'woocommerce-payments'
																) }
															</label>
															<span
																aria-label={ __(
																	'Dispute fee',
																	'woocommerce-payments'
																) }
															>
																{ disputeFee }
															</span>
														</Flex>
														<Flex>
															{ /* eslint-disable-next-line jsx-a11y/label-has-associated-control */ }
															<label>
																{ __(
																	'Total fees',
																	'woocommerce-payments'
																) }
															</label>
															<span
																aria-label={ __(
																	'Total fees',
																	'woocommerce-payments'
																) }
															>
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
										placeholder={ __(
											'Net amount',
											'woocommerce-payments'
										) }
									>
										{ `${ __(
											'Net',
											'woocommerce-payments'
										) }: ` }
										{ /* When the envelope is present, `balance.net`
										     (from getChargeAmounts) already reflects
										     paydown — server folded it in. Only
										     subtract manually on the legacy path. */ }
										{ formatExplicitCurrency(
											netAmount,
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
										orderId={ charge.order?.id || 0 }
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
										{ __(
											'Block transaction',
											'woocommerce-payments'
										) }
									</CancelAuthorizationButton>

									<CaptureAuthorizationButton
										buttonIsPrimary
										orderId={ charge.order?.id || 0 }
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
										{ __(
											'Approve Transaction',
											'woocommerce-payments'
										) }
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
											{ ! isPartiallyRefunded && (
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
											) }
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
																		?.id,
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
						items={
							charge.dispute
								? composePaymentSummaryItemsForDispute( {
										charge,
								  } )
								: composePaymentSummaryItems( {
										charge,
										metadata,
								  } )
						}
					/>
				</LoadableBlock>
			</CardBody>

			{ charge.dispute && (
				<ErrorBoundary>
					{ renderDisputeDetails( charge.dispute, charge, bankName ) }
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
			{ authorization && ! authorization.captured && (
				<Loadable isLoading={ isLoading } placeholder="">
					<CardNotice
						actions={
							! isFraudOutcomeReview ? (
								<CaptureAuthorizationButton
									orderId={ charge.order?.id || 0 }
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
									// @ts-expect-error: children is provided when interpolating the component
									<ExternalLink href="https://woocommerce.com/document/woopayments/settings-guide/authorize-and-capture/#capturing-authorized-orders" />
								),
							}
						) }{ ' ' }
						<abbr
							title={ formatDateTimeFromString(
								// TODO: is this string?
								moment
									.utc( authorization.created )
									.add( 7, 'days' )
									.toISOString(),
								{ includeTime: true }
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
) => {
	const dispute = props.charge?.dispute;
	// Gate on won/lost specifically: DisputeRecommendationsCard has no entries
	// for warning_* inquiries (warning_closed is the one that reaches here, since
	// the Outcome View admits it). AND suppress when the merchant accepted the
	// dispute: accepting is a deliberate non-engagement, so coaching them to
	// "submit evidence next time" misreads the choice. Per RiskOps review.
	const showRecommendationsCard =
		!! dispute &&
		!! wcpaySettings?.featureFlags?.isDisputeOutcomeViewEnabled &&
		( dispute.status === 'won' || dispute.status === 'lost' ) &&
		dispute.metadata?.__closed_by_merchant !== '1';

	// Outcome View Tracks: gating mirrors the original DisputeOutcomeView
	// path (won/lost/warning_closed + flag) so the signal stays stable
	// across the component refactor. Dedup is in `recordOutcomeViewOnce`.
	const isOutcomeViewStatus =
		dispute?.status === 'won' ||
		dispute?.status === 'lost' ||
		dispute?.status === 'warning_closed';
	const shouldRecordOutcomeView =
		!! dispute &&
		!! wcpaySettings?.featureFlags?.isDisputeOutcomeViewEnabled &&
		isOutcomeViewStatus;
	// COUPLED with dispute-recommendations/index.tsx: the card filters its
	// catalog by this same productType. Keep both call sites in lockstep.
	const productType = dispute
		? resolveProductType(
				dispute.metadata,
				dispute.order?.suggested_product_type,
				wcpaySettings?.featureFlags
					?.isDisputeAdditionalEvidenceTypesEnabled ?? false
		  )
		: '';

	// Mirror the card: true only when the card actually renders entries.
	const hasRecommendations =
		showRecommendationsCard &&
		dispute !== undefined &&
		getDisputeRecommendations( dispute, productType ).length > 0;

	useEffect( () => {
		if ( shouldRecordOutcomeView && dispute ) {
			recordOutcomeViewOnce( dispute, productType, hasRecommendations );
		}
	}, [ shouldRecordOutcomeView, dispute, productType, hasRecommendations ] );

	return (
		<WCPaySettingsContext.Provider value={ window.wcpaySettings }>
			<PaymentDetailsSummary { ...props } />
			{ showRecommendationsCard && dispute && (
				<ErrorBoundary>
					<DisputeRecommendationsCard dispute={ dispute } />
				</ErrorBoundary>
			) }
		</WCPaySettingsContext.Provider>
	);
};

export default PaymentDetailsSummaryWrapper;
