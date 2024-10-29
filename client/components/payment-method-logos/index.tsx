/**
 * External dependencies
 */
import React, { useState, useEffect } from 'react';

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
	const totalPaymentMethods = 20;
	const [ maxShownElements, setMaxShownElements ] = useState( maxElements );

	useEffect( () => {
		const updateMaxElements = () => {
			if ( window.innerWidth <= 480 ) {
				setMaxShownElements( 5 );
			} else if ( window.innerWidth <= 768 ) {
				setMaxShownElements( 7 );
			} else {
				setMaxShownElements( maxElements );
			}
		};

		updateMaxElements();
		window.addEventListener( 'resize', updateMaxElements );
	}, [ maxElements ] );

	return (
		<>
			<div className="connect-account-page__payment-methods--logos">
				{ PaymentMethods.slice( 0, maxShownElements ).map( ( pm ) => {
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
				{ maxShownElements < totalPaymentMethods && (
					<div className="connect-account-page__payment-methods--logos-count">
						+ { totalPaymentMethods - maxShownElements }
					</div>
				) }
			</div>
		</>
	);
};
