/**
 * External dependencies
 */
import { useEffect } from 'react';
import { __ } from '@wordpress/i18n';
import { Card, CardBody } from '@wordpress/components';

/**
 * Internal dependencies
 */
import PaymentDetailsSummary from './summary';
import PaymentDetailsTimeline from './timeline';
import PaymentDetailsPaymentMethod from './payment-method';
import Page from 'components/page';
import ErrorBoundary from 'components/error-boundary';
import { TestModeNotice, topics } from 'components/test-mode-notice';
import PaymentCardReaderChargeDetails from './readers';
import { usePaymentIntentFallback } from 'wcpay/data/payment-intents';

const PaymentDetails = ( props ) => {
	if ( 'card_reader_fee' === props.query.transaction_type ) {
		return (
			<PaymentCardReaderChargeDetails
				chargeId={ props.query.id }
				transactionId={ props.query.transaction_id }
			/>
		);
	}

	return <PaymentChargeDetails id={ props.query.id } />;
};

const PaymentChargeDetails = ( { id } ) => {
	const { data, isLoading, error, redirect } = usePaymentIntentFallback( id );
	const testModeNotice = <TestModeNotice topic={ topics.paymentDetails } />;

	useEffect( () => {
		if ( redirect ) window.location.href = redirect.url;
	}, [ data.payment_intent, redirect ] );

	// Check instance of chargeError because its default value is empty object
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
					charge={ data }
					isLoading={ isLoading }
				/>
			</ErrorBoundary>
			{ wcpaySettings.featureFlags.paymentTimeline && (
				<ErrorBoundary>
					<PaymentDetailsTimeline chargeId={ id } />
				</ErrorBoundary>
			) }
			<ErrorBoundary>
				<PaymentDetailsPaymentMethod
					charge={ data }
					isLoading={ isLoading }
				/>
			</ErrorBoundary>
		</Page>
	);
};

export default PaymentDetails;
