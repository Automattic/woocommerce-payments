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

const PaymentDetailsPaymentMethod = ( { charge = {}, isLoading } ) => {
	let PaymentMethodDetails = SepaDetails;
	if (
		charge.payment_method_details &&
		'card' === charge.payment_method_details.type
	) {
		PaymentMethodDetails = CardDetails;
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
