/**
 * External dependencies
 */
import React, { useCallback, useRef } from 'react';
import { __ } from '@wordpress/i18n';
import { Card, CardBody } from '@wordpress/components';

/**
 * Internal dependencies
 */
import { TestModeNotice } from '../../components/test-mode-notice';
import Page from '../../components/page';
import ErrorBoundary from '../../components/error-boundary';
import PaymentDetailsSummary from '../summary';
import PaymentDetailsTimeline from '../timeline';
import PaymentDetailsPaymentMethod from '../payment-method';
import PaymentTransactionBreakdown from '../transaction-breakdown';
import { ApiError } from '../../types/errors';
import { Charge } from '../../types/charges';
import { PaymentIntent } from '../../types/payment-intents';
import { getBankName, getDisputeOrdinals } from 'wcpay/utils/charge';
import { getKlarnaLossReasons } from 'wcpay/disputes/utils';

interface PaymentDetailsProps {
	id: string;
	isLoading: boolean;
	error?: ApiError;
	charge?: Charge;
	metadata?: Record< string, any >;
	showTimeline?: boolean;
	paymentIntent?: PaymentIntent;
}

const PaymentDetails: React.FC< PaymentDetailsProps > = ( {
	id,
	error,
	charge = {} as Charge,
	metadata = {},
	isLoading,
	showTimeline = true,
	paymentIntent,
} ) => {
	// The refund modal (and the charge-derived props it needs) lives in the
	// summary card; the summary registers an opener here so the timeline's
	// early-fraud-warning "Refund this payment" CTA can trigger it.
	const refundModalOpener = useRef< () => void >();
	const registerRefundOpener = useCallback( ( open: () => void ) => {
		refundModalOpener.current = open;
	}, [] );

	// Check instance of error because its default value is empty object
	if ( ! isLoading && error instanceof Error ) {
		return (
			<Page maxWidth={ 1032 } className="wcpay-payment-details">
				<TestModeNotice currentPage="payments" isDetailsView={ true } />
				<Card>
					<CardBody>
						{ __(
							'Payment details not loaded',
							'woocommerce-payments'
						) }
					</CardBody>
				</Card>
			</Page>
		);
	}

	const bankName = charge ? getBankName( charge ) : null;

	// Shared with the summary panes so both views number multi-dispute charges
	// identically. Only meaningful when the charge carries 2+ disputes.
	const disputeOrder = charge ? getDisputeOrdinals( charge ) : undefined;

	// Klarna's reason for a lost dispute lives on the dispute, not on the
	// timeline event, so it travels alongside the events.
	const klarnaLossReasons = charge
		? getKlarnaLossReasons( charge )
		: undefined;

	return (
		<Page maxWidth={ 1032 } className="wcpay-payment-details">
			<TestModeNotice currentPage="payments" isDetailsView={ true } />
			<ErrorBoundary>
				<PaymentDetailsSummary
					charge={ charge }
					metadata={ metadata }
					isLoading={ isLoading }
					paymentIntent={ paymentIntent }
					onRegisterRefundOpener={ registerRefundOpener }
				/>
			</ErrorBoundary>

			{ showTimeline && wcpaySettings.featureFlags.paymentTimeline && (
				<ErrorBoundary>
					<PaymentDetailsTimeline
						paymentIntentId={ id }
						bankName={ bankName }
						disputeOrder={ disputeOrder }
						onRefund={ () => refundModalOpener.current?.() }
						klarnaLossReasons={ klarnaLossReasons }
					/>
				</ErrorBoundary>
			) }

			<ErrorBoundary>
				<PaymentTransactionBreakdown paymentIntentId={ id } />
			</ErrorBoundary>

			<ErrorBoundary>
				<PaymentDetailsPaymentMethod
					charge={ charge }
					isLoading={ isLoading }
				/>
			</ErrorBoundary>
		</Page>
	);
};

export default PaymentDetails;
