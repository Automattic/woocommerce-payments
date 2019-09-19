/** @format **/

/**
 * External dependencies
 */
import { Card } from '@woocommerce/components';

/**
 * Internal dependencies.
 */

const TransactionTimelineDetails = ( props ) => {
	const { transaction } = props;
	return (
		<Card title="Timeline">
			Timeline details for transaction { transaction.id }.
		</Card>
	);
};

export default TransactionTimelineDetails;
