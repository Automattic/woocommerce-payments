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
import { TestModeNotice, topics } from 'components/test-mode-notice';

const PaymentDetails = ( props ) => {
	const chargeId = props.query.id;
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
			<PaymentDetailsSummary charge={ charge } isLoading={ isLoading } />
			{ wcpaySettings.featureFlags.paymentTimeline && (
				<PaymentDetailsTimeline chargeId={ chargeId } />
			) }
			<PaymentDetailsPaymentMethod
				charge={ charge }
				isLoading={ isLoading }
			/>
		</Page>
	);
};

export default PaymentDetails;
