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
	// TODO: this is a placeholder card and does not require translation
	return (
		<Card title="Summary" action={ transaction.id }>
			Summary details for transaction { transaction.id }.
		</Card>
	);
};

export default TransactionSummaryDetails;
