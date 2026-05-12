/** @format **/

/**
 * External dependencies
 */
import React, { Fragment, ReactNode } from 'react';

/**
 * Internal dependencies
 */
import './style.scss';
import p24BankList from '../../payment-details/payment-method/p24/bank-list';
import { HoverTooltip } from '../tooltip';
import { getTransactionPaymentMethodTitle } from 'wcpay/transactions/utils/getTransactionPaymentMethodTitle';
import paymentMethodsMap from 'wcpay/payment-methods-map';

interface Payment {
	type: string;
	[ key: string ]: any;
}

const shouldDisplayNetworkOverBrand = ( network?: string ) =>
	[ 'cartes_bancaires', 'cb', 'eftpos', 'eftpos_au' ].includes(
		network ?? ''
	);

/**
 *
 * @param payment Payment charge object
 * @return Fragment containing formatted summary detail
 */
const formatDetails = ( payment: Payment ): ReactNode => {
	const paymentMethod = payment[ payment.type ];
	/**
	 * FLAG: PAYMENT_METHODS_LIST
	 *
	 * When adding a payment method, if you need to display a specific detail, you can
	 * add it here. If not, you don't need to list it here.
	 *
	 * If you're removing a payment method, you'll probably want to leave this section
	 * alone because we still need to display the details of existing transactions.
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
		case 'amazon_pay':
			return paymentMethod.funding?.card?.last4 ? (
				<Fragment>
					&nbsp;&bull;&bull;&bull;&bull;&nbsp;
					{ paymentMethod.funding.card.last4 }
				</Fragment>
			) : (
				<Fragment />
			);
		default:
			return <Fragment />;
	}
};

const WalletIcon = ( { payment }: PaymentMethodDetailsProps ) => {
	// Amazon Pay is itself the wallet — no card.wallet wrapper.
	if ( payment?.type === 'amazon_pay' ) {
		const amazonPayMethod = paymentMethodsMap.amazon_pay;
		if ( ! amazonPayMethod ) return null;
		const { icon: AmazonPayIcon, label: amazonPayLabel } = amazonPayMethod;
		return (
			<HoverTooltip
				isVisible={ false }
				content={ amazonPayLabel }
				className="payment-method-details__brand-tooltip"
			>
				<AmazonPayIcon />
			</HoverTooltip>
		);
	}

	const wallet = payment[ payment.type ]?.wallet;
	if ( ! wallet ) return null;

	if ( ! wallet.type ) return null;

	const paymentMethod = paymentMethodsMap[ wallet.type ];
	if ( ! paymentMethod ) return null;

	const { icon: Icon, label } = paymentMethod;

	return (
		<HoverTooltip
			isVisible={ false }
			content={ label }
			className="payment-method-details__brand-tooltip"
		>
			<Icon />
		</HoverTooltip>
	);
};
interface PaymentMethodDetailsProps {
	payment: Payment;
}

const PaymentMethodDetails = ( { payment }: PaymentMethodDetailsProps ) => {
	const paymentMethod = payment ? payment[ payment.type ] : null;

	if ( ! paymentMethod && ( ! payment || payment.type !== 'link' ) ) {
		return <span>&ndash;</span>;
	}

	const details = formatDetails( payment );

	const accountCountry = wcpaySettings?.accountStatus?.country || 'US';
	const fundingCardBrand = paymentMethod?.funding?.card?.brand?.toLowerCase();
	const brand = shouldDisplayNetworkOverBrand( paymentMethod?.network )
		? paymentMethod.network
		: paymentMethod?.brand ||
		  paymentMethod?.network ||
		  fundingCardBrand ||
		  payment?.type;

	// When the wallet icon already identifies the payment method (Amazon Pay
	// paid with a non-card instrument), the brand sprite would just duplicate it.
	const isAmazonPayWithoutFundingCard =
		payment?.type === 'amazon_pay' && ! fundingCardBrand;

	return (
		<span className="payment-method-details">
			<WalletIcon payment={ payment } />
			{ ! isAmazonPayWithoutFundingCard && (
				<HoverTooltip
					isVisible={ false }
					content={ getTransactionPaymentMethodTitle( brand ) }
					className="payment-method-details__brand-tooltip"
				>
					<span
						className={
							`payment-method__brand payment-method__brand--${ brand } ` +
							`account-country--${ accountCountry.toLowerCase() }`
						}
						aria-label={ getTransactionPaymentMethodTitle( brand ) }
					/>
				</HoverTooltip>
			) }
			{ details }
		</span>
	);
};

export default PaymentMethodDetails;
