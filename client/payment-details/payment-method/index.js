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
import CardPresentDetails from './card-present';
import GiropayDetails from './giropay';
import SepaDetails from './sepa';
import SofortDetails from './sofort';

const PaymentDetailsPaymentMethod = ( { charge = {}, isLoading } ) => {
	const DetailComponents = {
		card: CardDetails,
		card_present: CardPresentDetails, // eslint-disable-line camelcase
		giropay: GiropayDetails,
		sepa_debit: SepaDetails, // eslint-disable-line camelcase
		sofort: SofortDetails,
	};

	let PaymentMethodDetails = null;
	if ( charge.payment_method_details && charge.payment_method_details.type ) {
		const type = charge.payment_method_details.type;
		if ( type in DetailComponents ) {
			PaymentMethodDetails = DetailComponents[ type ];
		}
	}

	// Gracefully degrade for unrecognized payment method types
	if ( null == PaymentMethodDetails ) {
		return null;
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
