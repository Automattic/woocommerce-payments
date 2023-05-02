/**
 * External dependencies
 */
import React from 'react';
import { __ } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
import { TestModeNotice, topics } from '../../components/test-mode-notice';
import Page from '../../components/page';
import { Card, CardBody } from '@wordpress/components';
import ErrorBoundary from '../../components/error-boundary';
import PaymentDetailsSummary from '../summary';
import PaymentDetailsTimeline from '../timeline';
import PaymentDetailsPaymentMethod from '../payment-method';
import { ApiError } from '../../types/errors';
import { Charge } from '../../types/charges';
import { PaymentIntent } from '../../types/payment-intents';

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
	const testModeNotice = <TestModeNotice topic={ topics.paymentDetails } />;

	// Check instance of error because its default value is empty object
	if ( ! isLoading && error instanceof Error ) {
		return (
			<Page maxWidth={ 1032 } className="wcpay-payment-details">
				{ testModeNotice }
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

	return (
		<Page maxWidth={ 1032 } className="wcpay-payment-details">
			{ testModeNotice }

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
					<PaymentDetailsTimeline paymentIntentId={ id } />
				</ErrorBoundary>
			) }

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
