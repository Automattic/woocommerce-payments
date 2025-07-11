/**
 * External dependencies
 */
import React from 'react';
import { __ } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
import { TestModeNotice } from '../../components/test-mode-notice';
import Page from '../../components/page';
import { Card } from 'wcpay/components/wp-components-wrapped/components/card';
import { CardBody } from 'wcpay/components/wp-components-wrapped/components/card-body';
import ErrorBoundary from '../../components/error-boundary';
import PaymentDetailsSummary from '../summary';
import PaymentDetailsTimeline from '../timeline';
import PaymentDetailsPaymentMethod from '../payment-method';
import PaymentTransactionBreakdown from '../transaction-breakdown';
import { ApiError } from '../../types/errors';
import { Charge } from '../../types/charges';
import { PaymentIntent } from '../../types/payment-intents';
import { MaybeShowMerchantFeedbackPrompt } from '../../merchant-feedback-prompt';
import { getBankName } from 'wcpay/utils/charge';

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

	return (
		<Page maxWidth={ 1032 } className="wcpay-payment-details">
			<MaybeShowMerchantFeedbackPrompt />
			<TestModeNotice currentPage="payments" isDetailsView={ true } />
			<ErrorBoundary>
				<PaymentDetailsSummary
					charge={ charge }
					metadata={ metadata }
					isLoading={ isLoading }
					paymentIntent={ paymentIntent }
				/>
			</ErrorBoundary>

			{ showTimeline && wcpaySettings.featureFlags.paymentTimeline && (
				<ErrorBoundary>
					<PaymentDetailsTimeline
						paymentIntentId={ id }
						bankName={ bankName }
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
