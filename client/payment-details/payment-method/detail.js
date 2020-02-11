/** @format **/

/**
 * External dependencies
 */

/**
 * Internal dependencies.
 */

const PaymentDetailsPaymentMethodDetail = ( props ) => {
	const { label, children } = props;

	return (
		<p>
			<strong>{ label }</strong>
			<br />
			<em>{ children }</em>
		</p>
	);
};

export default PaymentDetailsPaymentMethodDetail;
