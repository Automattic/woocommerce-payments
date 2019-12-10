/** @format **/

/**
 * External dependencies
 */
/**
 * Internal dependencies.
 */
import './style.scss';

const PaymentMethodDetails = ( props ) => {
	const { payment } = props;
	const paymentMethod = ( payment ) ? payment[ payment.type ] : null;
	return paymentMethod
		? <span className="payment-method-details">
			{ /* TODO: deal with other payment methods. Currently this assumes payment type is card */ }
			<span className={ `payment-method__brand payment-method__brand--${ paymentMethod.brand }` }></span>
			{ ' •••• ' }
			{ paymentMethod.last4 }
		</span>
		: <span>&ndash;</span>;
};

export default PaymentMethodDetails;
