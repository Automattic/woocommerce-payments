/** @format **/

/**
 * Internal dependencies
 */
import { Fragment } from 'react';
import './style.scss';
import p24BankList from '../../payment-details/payment-method/p24/bank-list';
import { HoverTooltip } from '../tooltip';
import { getTransactionPaymentMethodTitle } from 'wcpay/transactions/utils/getTransactionPaymentMethodTitle';

/**
 *
 * @param {Object} payment Payment charge object
 * @return {ReactNode} Fragment containing formatted summary detail
 */
const formatDetails = ( payment ) => {
	const paymentMethod = payment[ payment.type ];
	/**
	 * FLAG: PAYMENT_METHODS_LIST
	 *
	 * When adding a payment method, if you need to display a specific detail, you can
	 * add it here. If not, you don't need to list it here.
	 *
	 * If you're removing a payment method, you'll probably want to leave this section
	 * section alone because we still need to display the details of existing transactions.
	 */
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

	let brand = payment.type;
	if ( paymentMethod && paymentMethod.brand ) {
		brand = paymentMethod.brand;
	}
	if ( paymentMethod && paymentMethod.network ) {
		brand = paymentMethod.network;
	}

	const details = formatDetails( payment );
	return (
		<span className="payment-method-details">
			<HoverTooltip
				isVisible={ false }
				content={ getTransactionPaymentMethodTitle( brand ) }
				className="payment-method-details__brand-tooltip"
			>
				<span
					className={ `payment-method__brand payment-method__brand--${ brand }` }
					aria-label={ getTransactionPaymentMethodTitle( brand ) }
				/>
			</HoverTooltip>
			{ details }
		</span>
	);
};

export default PaymentMethodDetails;
