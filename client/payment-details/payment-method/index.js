/** @format **/

/**
 * External dependencies
 */
import { __ } from '@wordpress/i18n';
import { Card, CardBody, CardHeader } from '@wordpress/components';

/**
 * Internal dependencies.
 */
import Loadable from 'components/loadable';
import CardDetails from './card';
import SepaDetails from './sepa';
import GiropayDetails from './giropay';

const PaymentDetailsPaymentMethod = ( { charge = {}, isLoading } ) => {
	let PaymentMethodDetails = CardDetails;
	if ( charge.payment_method_details && charge.payment_method_details.type ) {
		switch ( charge.payment_method_details.type ) {
			case 'sepa_debit':
				PaymentMethodDetails = SepaDetails;
				break;
			case 'giropay':
				PaymentMethodDetails = GiropayDetails;
				break;
		}
	}
	return (
		<Card size="large">
			<CardHeader>
				<Loadable
					isLoading={ isLoading }
					value={ __( 'Payment method', 'woocommerce-payments' ) }
				/>
			</CardHeader>
			<CardBody>
				<PaymentMethodDetails
					isLoading={ isLoading }
					charge={ charge }
				/>
			</CardBody>
		</Card>
	);
};

export default PaymentDetailsPaymentMethod;
