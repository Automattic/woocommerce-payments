/** @format **/

/**
 * Internal dependencies
 */
import { Fragment } from 'react';
import './style.scss';
import p24BankList from '../../payment-details/payment-method/p24/bank-list';
import { HoverTooltip } from '../tooltip';
import { PAYMENT_METHOD_TITLES } from 'payment-methods/constants';
/**
 *
 * @param {Object} payment Payment charge object
 * @return {ReactNode} Fragment containing formatted summary detail
 */
const formatDetails = ( payment ) => {
	const paymentMethod = payment[ payment.type ];
	switch ( payment.type ) {
		case 'card':
		case 'au_becs_debit':
		case 'sepa_debit':
		case 'card_present':
		case 'interac_present':
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
		case 'bancontact':
		case 'ideal':
		case 'eps':
		case 'sofort':
			return (
				<Fragment>
					&nbsp;&bull;&bull;&bull;&bull;&nbsp;
					{ paymentMethod.iban_last4 }
				</Fragment>
			);
		case 'affirm':
		case 'afterpay_clearpay':
		default:
			return <Fragment />;
	}
};

const PaymentMethodDetails = ( props ) => {
	const { payment } = props;
	const paymentMethod = payment ? payment[ payment.type ] : null;

	if ( ! paymentMethod && ( ! payment || payment.type !== 'link' ) ) {
		return <span>&ndash;</span>;
	}

	const brand =
		paymentMethod && paymentMethod.brand
			? paymentMethod.brand
			: payment.type;
	const details = formatDetails( payment );
	return (
		<span className="payment-method-details">
			<HoverTooltip
				isVisible={ false }
				content={ PAYMENT_METHOD_TITLES[ brand ] }
				className="payment-method-details__brand-tooltip"
			>
				<span
					className={ `payment-method__brand payment-method__brand--${ brand }` }
					aria-label={ PAYMENT_METHOD_TITLES[ brand ] }
				/>
			</HoverTooltip>
			{ details }
		</span>
	);
};

export default PaymentMethodDetails;
