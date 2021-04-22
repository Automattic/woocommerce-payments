/** @format **/

/**
 * Internal dependencies
 */
import { Fragment } from 'react';
import './style.scss';

const PaymentMethodDetails = ( props ) => {
	const { payment } = props;
	const paymentMethod = payment ? payment[ payment.type ] : null;

	if ( ! paymentMethod ) {
		return <span>&ndash;</span>;
	}
	const paymentMethodAbbr = paymentMethod.last4
		? paymentMethod.last4
		: paymentMethod.bank_code;
	const brand = paymentMethod.brand ? paymentMethod.brand : payment.type;
	return (
		<span className="payment-method-details">
			<span
				className={ `payment-method__brand payment-method__brand--${ brand }` }
			/>
			{ paymentMethod.last4 && (
				<Fragment>&nbsp;&bull;&bull;&bull;&bull;&nbsp;</Fragment>
			) }
			{ paymentMethodAbbr }
		</span>
	);
};

export default PaymentMethodDetails;
