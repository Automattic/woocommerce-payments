/** @format **/

/**
 * Internal dependencies
 */
import './style.scss';

const PaymentMethodDetails = ( props ) => {
	const { payment } = props;
	const paymentMethod = payment ? payment[ payment.type ] : null;

	if ( ! paymentMethod ) {
		return <span>&ndash;</span>;
	}
	const brand = paymentMethod.brand ? paymentMethod.brand : payment.type;
	return (
		<span className="payment-method-details">
			<span
				className={ `payment-method__brand payment-method__brand--${ brand }` }
			/>
			&nbsp;••••&nbsp;
			{ paymentMethod.last4 }
		</span>
	);
};

export default PaymentMethodDetails;
