/** @format **/

/**
 * External dependencies
 */
import { Card } from '@woocommerce/components';

/**
 * Internal dependencies.
 */

const TransactionSummaryDetails = ( props ) => {
	const { transaction } = props;
	return (
		<Card title="Summary" action={ transaction.id }>
			Summary details for transaction { transaction.id }.
		</Card>
	);
};

export default TransactionSummaryDetails;
