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

	const brand = paymentMethod.brand ? paymentMethod.brand : payment.type;
	return (
		<span className="payment-method-details">
			<span
				className={ `payment-method__brand payment-method__brand--${ brand }` }
			/>
			{ paymentMethod.last4 ? (
				<Fragment>
					&nbsp;&bull;&bull;&bull;&bull;&nbsp;{ paymentMethod.last4 }
				</Fragment>
			) : (
				paymentMethod.bank_code
			) }
		</span>
	);
};

export default PaymentMethodDetails;
