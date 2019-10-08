/** @format **/

/**
 * External dependencies
 */
/**
 * Internal dependencies.
 */
import withSelect from 'payments-api/with-select';
import TransactionSummaryDetails from './summary';
import TransactionTimelineDetails from './timeline';
import TransactionPaymentDetails from './payment';
import TransactionPaymentMethodDetails from './payment-method';
import TransactionSessionDetails from './session';

const TransactionDetails = ( props ) => {
	const { transaction } = props;
	return (
		<div>
			<TransactionSummaryDetails transaction={ transaction }></TransactionSummaryDetails>
			<TransactionTimelineDetails transaction={ transaction }></TransactionTimelineDetails>
			<TransactionPaymentDetails transaction={ transaction }></TransactionPaymentDetails>
			<TransactionPaymentMethodDetails transaction={ transaction }></TransactionPaymentMethodDetails>
			<TransactionSessionDetails transaction={ transaction }></TransactionSessionDetails>
		</div>
	);
};

export default withSelect( ( select, ownProps ) => {
	// TODO: Create selector for fetching a single transaction and use it here, instead of
	// creating an empty transaction object
	const transaction = { id: ownProps.query.id };

	return { transaction };
} )( TransactionDetails );
