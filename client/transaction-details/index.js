/** @format **/

/**
 * External dependencies
 */
/**
 * Internal dependencies.
 */

const TransactionDetails = ( props ) => {
	const transactionId = props.query.id;
	return (
		<p>You are vieweing details for transaction { transactionId }</p>
	);
};

export default TransactionDetails;
