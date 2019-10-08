/** @format **/

/**
 * External dependencies
 */
import { Card } from '@woocommerce/components';

/**
 * Internal dependencies.
 */

const TransactionPaymentMethodDetails = ( props ) => {
	const { transaction } = props;
	// TODO: this is a placeholder card and does not require translation
	return (
		<Card title="Payment method">
			Payment method details for transaction { transaction.id }.
		</Card>
	);
};

export default TransactionPaymentMethodDetails;
