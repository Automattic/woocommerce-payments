/** @format **/

/**
 * External dependencies
 */
import { Card } from '@woocommerce/components';

/**
 * Internal dependencies.
 */

const PaymentDetailsPayment = ( props ) => {
	const { transaction } = props;
	// TODO: this is a placeholder card and does not require translation
	return (
		<Card title="Payment">
			Payment details for transaction { transaction.id }.
		</Card>
	);
};

export default PaymentDetailsPayment;
