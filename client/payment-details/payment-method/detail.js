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
		<div className="payment-method-detail">
			<h4 className="payment-method-detail__label">{ label }</h4>
			<p className="payment-method-detail__value">{ children }</p>
		</div>
	);
};

export default PaymentDetailsPaymentMethodDetail;
