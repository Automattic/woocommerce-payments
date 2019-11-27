/** @format **/

/**
 * External dependencies
 */
import { Card } from '@woocommerce/components';

/**
 * Internal dependencies.
 */
import PaymentStatusChip from '../../components/payment-status-chip';

const TransactionSummaryDetails = ( props ) => {
	const { transaction } = props;
	return (
		<Card>
			<PaymentStatusChip transaction={ transaction } />
		</Card>
	);
};

export default TransactionSummaryDetails;
