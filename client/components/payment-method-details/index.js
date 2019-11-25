/** @format **/

/**
 * External dependencies
 */
/**
 * Internal dependencies.
 */

const PaymentMethodDetails = ( props ) => {
	const { payment } = props;
	const paymentMethod = ( payment ) ? payment[ payment.type ] : null;
	return paymentMethod
	?	<span>
			{ /* TODO: deal with other payment methods. Currently this assumes payment type is card */ }
			{ /* TODO: use paymentMethod brand image instead of wrapping its name in a code tag*/ }
			<code>{ paymentMethod.brand }</code>
			{ ' •••• ' }
			{ paymentMethod.last4 }
		</span>
	: 	<span>&ndash;</span>;
};

export default PaymentMethodDetails;
