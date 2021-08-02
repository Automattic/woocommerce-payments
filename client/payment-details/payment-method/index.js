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
import BancontactDetails from './bancontact';
import GiropayDetails from './giropay';
import SepaDetails from './sepa';
import SofortDetails from './sofort';

const detailsComponentMap = {
	card: CardDetails,
	card_present: CardPresentDetails,
	bancontact: BancontactDetails,
	giropay: GiropayDetails,
	sepa_debit: SepaDetails,
	sofort: SofortDetails,
};

const PaymentDetailsPaymentMethod = ( { charge = {}, isLoading } ) => {
	if (
		! charge.payment_method_details ||
		! charge.payment_method_details.type
	) {
		// Gracefully degrade for malformed charge objects
		return null;
	}

	const type = charge.payment_method_details.type;
	if ( ! ( type in detailsComponentMap ) ) {
		// Gracefully degrade for unrecognized payment method types
		return null;
	}

	const PaymentMethodDetails = detailsComponentMap[ type ];

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
