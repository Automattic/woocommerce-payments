/** @format **/

/**
 * External dependencies
 */
import { __ } from '@wordpress/i18n';
import { Card } from '@woocommerce/components';

/**
 * Internal dependencies
 */
import { useCharge } from '../data';
import PaymentDetailsSummary from './summary';
import PaymentDetailsTimeline from './timeline';
import PaymentDetailsPayment from './payment';
import PaymentDetailsPaymentMethod from './payment-method';
import PaymentDetailsSession from './session';
import Page from 'components/page';

const PaymentDetails = ( props ) => {
	const chargeId = props.query.id;
	const { charge, isLoading, chargeError } = useCharge( chargeId );

	// Check instance of chargeError because its default value is empty object
	if ( ! isLoading && chargeError instanceof Error ) {
		return (
			<Page maxWidth={ 1032 } className="wcpay-payment-details">
				<Card>
					<div>
						{ __(
							'Payment details not loaded',
							'woocommerce-payments'
						) }
					</div>
				</Card>
			</Page>
		);
	}

	return (
		<Page maxWidth={ 1032 } className="wcpay-payment-details">
			<PaymentDetailsSummary charge={ charge } isLoading={ isLoading } />
			<PaymentDetailsTimeline chargeId={ chargeId } />
			{
				// Hidden for the beta.
				false && <PaymentDetailsPayment charge={ charge } />
			}
			<PaymentDetailsPaymentMethod
				charge={ charge }
				isLoading={ isLoading }
			/>
			<PaymentDetailsSession charge={ charge } />
		</Page>
	);
};

export default PaymentDetails;
