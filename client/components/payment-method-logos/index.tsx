/**
 * External dependencies
 */
import React from 'react';

/**
 * Internal dependencies
 */
import Visa from 'assets/images/payment-method-icons/visa.svg?asset';
import Mastercard from 'assets/images/payment-method-icons/mastercard.svg?asset';
import Amex from 'assets/images/payment-method-icons/amex.svg?asset';
import Discover from 'assets/images/payment-method-icons/discover.svg?asset';
import WooPay from 'assets/images/payment-method-icons/woopay.svg?asset';
import ApplePay from 'assets/images/payment-method-icons/applepay.svg?asset';
import AfterPay from 'assets/images/payment-method-icons/afterpay.svg?asset';
import Affirm from 'assets/images/payment-method-icons/affirm.svg?asset';
import Klarna from 'assets/images/payment-method-icons/klarna.svg?asset';
import Jcb from 'assets/images/payment-method-icons/jcb.svg?asset';
import GooglePay from 'assets/images/payment-method-icons/gpay.svg?asset';

import './style.scss';

const PaymentMethods = [
	{
		name: 'visa',
		component: Visa,
	},
	{
		name: 'mastercard',
		component: Mastercard,
	},
	{
		name: 'amex',
		component: Amex,
	},
	{
		name: 'discover',
		component: Discover,
	},
	{
		name: 'woopay',
		component: WooPay,
	},
	{
		name: 'applepay',
		component: ApplePay,
	},
	{
		name: 'googlepay',
		component: GooglePay,
	},
	{
		name: 'afterpay',
		component: AfterPay,
	},
	{
		name: 'affirm',
		component: Affirm,
	},
	{
		name: 'klarna',
		component: Klarna,
	},
	{
		name: 'jcb',
		component: Jcb,
	},
];

export const WooPaymentMethodsLogos: React.VFC< {
	maxElements: number;
} > = ( { maxElements = 10 } ) => {
	const totalPaymentMethods = 21;
	const maxElementsMiniView = 5;
	return (
		<>
			<div className="woocommerce-payments-method-logos">
				{ PaymentMethods.slice( 0, maxElements ).map( ( pm ) => {
					return (
						<img
							key={ pm.name }
							alt={ pm.name }
							src={ pm.component }
							width={ 38 }
							height={ 24 }
						/>
					);
				} ) }
				{ maxElements < totalPaymentMethods && (
					<div className="woocommerce-payments-method-logos_count">
						+ { totalPaymentMethods - maxElements }
					</div>
				) }
			</div>

			<div className="woocommerce-payments-method-logos_mini">
				{ PaymentMethods.slice( 0, maxElementsMiniView ).map(
					( pm ) => {
						return (
							<img
								key={ pm.name }
								alt={ pm.name }
								src={ pm.component }
								width={ 38 }
								height={ 24 }
							/>
						);
					}
				) }
				<div className="woocommerce-payments-method-logos_count">
					+ { totalPaymentMethods - maxElementsMiniView }
				</div>
			</div>
		</>
	);
};
