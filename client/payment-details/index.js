/**
 * External dependencies
 */
import { __ } from '@wordpress/i18n';
import { Card, CardBody } from '@wordpress/components';

/**
 * Internal dependencies
 */
import { useCharge } from '../data';
import PaymentDetailsSummary from './summary';
import PaymentDetailsTimeline from './timeline';
import PaymentDetailsPaymentMethod from './payment-method';
import Page from 'components/page';
import ErrorBoundary from 'components/error-boundary';
import { TestModeNotice, topics } from 'components/test-mode-notice';
import PaymentCardReaderChargeDetails from './readers';

const PaymentDetails = ( props ) => {
	if ( 'card_reader_fee' === props.query.type ) {
		return (
			<PaymentCardReaderChargeDetails
				chargeId={ props.query.id }
				transactionId={ props.query.transaction_id }
			/>
		);
	}

	return <PaymentChargeDetails chargeId={ props.query.id } />;
};

const PaymentChargeDetails = ( props ) => {
	const chargeId = props.chargeId;
	const { charge, isLoading, chargeError } = useCharge( chargeId );
	const testModeNotice = <TestModeNotice topic={ topics.paymentDetails } />;

	// Check instance of chargeError because its default value is empty object
	if ( ! isLoading && chargeError instanceof Error ) {
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
					isLoading={ isLoading }
				/>
			</ErrorBoundary>
			{ wcpaySettings.featureFlags.paymentTimeline && (
				<ErrorBoundary>
					<PaymentDetailsTimeline chargeId={ chargeId } />
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
