/** @format **/

/**
 * External dependencies
 */
import { Card } from '@woocommerce/components';

/**
 * Internal dependencies.
 */

const TransactionPaymentDetails = ( props ) => {
	const { transaction } = props;
	return (
		<Card title="Payment">
			Payment details for transaction { transaction.id }.
		</Card>
	);
};

export default TransactionPaymentDetails;
