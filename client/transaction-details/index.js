/** @format **/

/**
 * External dependencies
 */
/**
 * Internal dependencies.
 */
import TransactionSummaryDetails from './summary';
import TransactionTimelineDetails from './timeline';
import TransactionPaymentDetails from './payment';
import TransactionPaymentMethodDetails from './payment-method';
import TransactionSessionDetails from './session';

 const TransactionDetails = ( props ) => {
	const transaction = { id: props.query.id };
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

export default TransactionDetails;
