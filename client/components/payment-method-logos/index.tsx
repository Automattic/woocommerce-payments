/**
 * External dependencies
 */
import React from 'react';
import { Fragment } from '@wordpress/element';

/**
 * Internal dependencies
 */
import {
	Visa,
	MasterCard,
	Amex,
	WooPay,
	ApplePay,
	GooglePay,
	Discover,
	JCB,
	Afterpay,
	Affirm,
	Klarna,
} from './Icons';

import './style.scss';

const Payments = [
	{
		name: 'visa',
		component: <Visa key="visa" />,
	},
	{
		name: 'mastercard',
		component: <MasterCard key="mastercard" />,
	},
	{
		name: 'amex',
		component: <Amex key="amex" />,
	},
	{
		name: 'discover',
		component: <Discover key="discover" />,
	},
	{
		name: 'woopay',
		component: <WooPay key="woopay" />,
	},
	{
		name: 'applepay',
		component: <ApplePay key="applepay" />,
	},
	{
		name: 'googlepay',
		component: <GooglePay key="googlepay" />,
	},
	{
		name: 'afterpay',
		component: <Afterpay key="afterpay" />,
	},
	{
		name: 'affirm',
		component: <Affirm key="affirm" />,
	},
	{
		name: 'klarna',
		component: <Klarna key="klarna" />,
	},
	{
		name: 'jcb',
		component: <JCB key="jcb" />,
	},
];

export const WooPaymentMethodLogos: React.VFC< {
	maxNrElements: number;
} > = ( { maxNrElements = 10 } ) => {
	let i = 0;
	let j = 0;
	return (
		<>
			<div className="woocommerce-payments-method-logos">
				{ Payments.map( ( payment ) => {
					if ( i >= maxNrElements ) {
						i++;
						return <Fragment key={ i - 1 }></Fragment>;
					}
					i++;

					return payment.component;
				} ) }
				{ i < 21 && (
					<div className="woocommerce-payments-method-logos_count">
						+ { 21 - i }
					</div>
				) }
			</div>

			<div className="woocommerce-payments-method-logos_mini">
				{ Payments.map( ( payment ) => {
					if ( j >= 5 ) {
						j++;
						return <Fragment key={ j - 1 }></Fragment>;
					}
					j++;

					return payment.component;
				} ) }
				{ j < 21 && (
					<div className="woocommerce-payments-method-logos_count">
						+ { 21 - j }
					</div>
				) }
			</div>
		</>
	);
};
