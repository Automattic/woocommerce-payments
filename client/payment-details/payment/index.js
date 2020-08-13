/** @format **/

/**
 * External dependencies
 */
import { Card } from '@woocommerce/components';

/**
 * Internal dependencies.
 */

const PaymentDetailsPayment = ( props ) => {
	const { charge } = props;
	// TODO: this is a placeholder card and does not require translation
	return (
		<Card title="Payment">Payment details for charge { charge.id }.</Card>
	);
};

export default PaymentDetailsPayment;
