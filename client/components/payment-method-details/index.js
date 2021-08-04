/** @format **/

/**
 * Internal dependencies
 */
import { Fragment } from 'react';
import './style.scss';
import p24BankList from '../../payment-details/payment-method/p24/bank-list';
/**
 *
 * @param {Object} payment Payment charge object
 * @return {ReactNode} Fragment containing formatted summary detail
 */
const formatDetails = ( payment ) => {
	const paymentMethod = payment[ payment.type ];
	switch ( payment.type ) {
		case 'card':
		case 'sepa':
			return (
				<Fragment>
					&nbsp;&bull;&bull;&bull;&bull;&nbsp;{ paymentMethod.last4 }
				</Fragment>
			);
		case 'p24':
			return (
				<Fragment>{ p24BankList[ paymentMethod.bank ] ?? '' }</Fragment>
			);
		case 'giropay':
			return <Fragment>{ paymentMethod.bank_code }</Fragment>;
		case 'sofort':
			return (
				<Fragment>
					&nbsp;&bull;&bull;&bull;&bull;&nbsp;
					{ paymentMethod.iban_last4 }
				</Fragment>
			);
		default:
			return <Fragment />;
	}
};

const PaymentMethodDetails = ( props ) => {
	const { payment } = props;
	const paymentMethod = payment ? payment[ payment.type ] : null;

	if ( ! paymentMethod ) {
		return <span>&ndash;</span>;
	}

	const brand = paymentMethod.brand ? paymentMethod.brand : payment.type;
	const details = formatDetails( payment );
	return (
		<span className="payment-method-details">
			<span
				className={ `payment-method__brand payment-method__brand--${ brand }` }
			/>
			{ details }
		</span>
	);
};

export default PaymentMethodDetails;
