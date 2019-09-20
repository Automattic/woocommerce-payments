/** @format **/

/**
 * External dependencies
 */
/**
 * Internal dependencies.
 */
import Chip from '../chip';

/* TODO: implement other payment statuses */
/* TODO: implement support for different dispute messages - check https://stripe.com/docs/api/disputes/object */
const PaymentStatusChip = ( props ) => {
	const { transaction, style } = props;
	const message = transaction.dispute ? 'Disputed: Needs Response' : 'Paid';
	const type = transaction.dispute ? 'warning' : 'primary';
    return (
		<Chip message={ message } type={ type } style={ style } />
	);
};

export default PaymentStatusChip;
