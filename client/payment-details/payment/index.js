/**
 * External dependencies
 */
import { Card, CardBody } from '@wordpress/components';

const PaymentDetailsPayment = ( props ) => {
	const { charge } = props;
	// TODO: this is a placeholder card and does not require translation
	return (
		<Card title="Payment">
			<CardBody>Payment details for charge { charge.id }.</CardBody>
		</Card>
	);
};

export default PaymentDetailsPayment;
