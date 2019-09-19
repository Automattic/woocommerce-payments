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
	const { getTransactions } = select( 'wc-payments-api' );
	// TODO: Create selector for fetching a single transaction and use it here, instead of
	// using getTransactions() and then filtering the result
	const transactions = getTransactions().data || [];
	const transaction = transactions.filter( txn => txn.id === ownProps.query.id )[ 0 ] || {};

	return { transaction };
} )( TransactionDetails );
