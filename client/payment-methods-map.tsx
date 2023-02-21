/**
 * External dependencies
 */
import React from 'react';
import { __ } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
import CreditCardIcon from 'assets/images/payment-methods/cc.svg';
import BancontactIcon from 'assets/images/payment-methods/bancontact.svg';
import EpsIcon from 'assets/images/payment-methods/eps.svg';
import GiropayIcon from 'assets/images/payment-methods/giropay.svg';
import SofortIcon from 'assets/images/payment-methods/sofort.svg';
import SepaIcon from 'assets/images/payment-methods/sepa-debit.svg';
import P24Icon from 'assets/images/payment-methods/p24.svg';
import IdealIcon from 'assets/images/payment-methods/ideal.svg';
import BankDebitIcon from 'assets/images/payment-methods/bank-debit.svg';

export interface PaymentMethodMapEntry {
	id: string;
	label: string;
	description: string;
	icon: ( { className }: { className: string } ) => JSX.Element;
	currencies: string[];
	stripe_key: string;
	allows_manual_capture: boolean;
}

const PaymentMethodInformationObject: Record<
	string,
	PaymentMethodMapEntry
> = {
	card: {
		id: 'card',
		label: __( 'Credit card / debit card', 'woocommerce-payments' ),
		description: __(
			'Let your customers pay with major credit and debit cards without leaving your store.',
			'woocommerce-payments'
		),
		icon: ( props ) => (
			<img src={ CreditCardIcon } alt="Credit Card" { ...props } />
		),
		currencies: [],
		stripe_key: 'card_payments',
		allows_manual_capture: true,
	},
	au_becs_debit: {
		id: 'au_becs_debit',
		label: __( 'BECS Direct Debit', 'woocommerce-payments' ),
		description: __(
			'Bulk Electronic Clearing System — Accept secure bank transfer from Australia.',
			'woocommerce-payments'
		),
		icon: ( props ) => (
			<img src={ BankDebitIcon } alt="BECS Direct Debit" { ...props } />
		),
		currencies: [ 'AUD' ],
		stripe_key: 'au_becs_debit_payments',
		allows_manual_capture: false,
	},
	bancontact: {
		id: 'bancontact',
		label: __( 'Bancontact', 'woocommerce-payments' ),
		description: __(
			'Bancontact is a bank redirect payment method offered by more than 80% of online businesses in Belgium.',
			'woocommerce-payments'
		),
		icon: ( props ) => (
			<img src={ BancontactIcon } alt="Bancontact" { ...props } />
		),
		currencies: [ 'EUR' ],
		stripe_key: 'bancontact_payments',
		allows_manual_capture: false,
	},
	eps: {
		id: 'eps',
		label: __( 'EPS', 'woocommerce-payments' ),
		description: __(
			'Accept your payment with EPS — a common payment method in Austria.',
			'woocommerce-payments'
		),
		icon: ( props ) => <img src={ EpsIcon } alt="EPS" { ...props } />,
		currencies: [ 'EUR' ],
		stripe_key: 'eps_payments',
		allows_manual_capture: false,
	},
	giropay: {
		id: 'giropay',
		label: __( 'giropay', 'woocommerce-payments' ),
		description: __(
			'Expand your business with giropay — Germany’s second most popular payment system.',
			'woocommerce-payments'
		),
		icon: ( props ) => (
			<img src={ GiropayIcon } alt="giropay" { ...props } />
		),
		currencies: [ 'EUR' ],
		stripe_key: 'giropay_payments',
		allows_manual_capture: false,
	},
	ideal: {
		id: 'ideal',
		label: __( 'iDEAL', 'woocommerce-payments' ),
		description: __(
			'Expand your business with iDEAL — Netherlands’s most popular payment method.',
			'woocommerce-payments'
		),
		icon: ( props ) => <img src={ IdealIcon } alt="iDEAL" { ...props } />,
		currencies: [ 'EUR' ],
		stripe_key: 'ideal_payments',
		allows_manual_capture: false,
	},
	p24: {
		id: 'p24',
		label: __( 'Przelewy24 (P24)', 'woocommerce-payments' ),
		description: __(
			'Accept payments with Przelewy24 (P24), the most popular payment method in Poland.',
			'woocommerce-payments'
		),
		icon: ( props ) => (
			<img src={ P24Icon } alt="Przelewy24 (P24)" { ...props } />
		),
		currencies: [ 'EUR', 'PLN' ],
		stripe_key: 'p24_payments',
		allows_manual_capture: false,
	},
	sepa_debit: {
		id: 'sepa_debit',
		label: __( 'SEPA Direct Debit', 'woocommerce-payments' ),
		description: __(
			'Reach 500 million customers and over 20 million businesses across the European Union.',
			'woocommerce-payments'
		),
		icon: ( props ) => (
			<img src={ SepaIcon } alt="SEPA Direct Debit" { ...props } />
		),
		currencies: [ 'EUR' ],
		stripe_key: 'sepa_debit_payments',
		allows_manual_capture: false,
	},
	sofort: {
		id: 'sofort',
		label: __( 'Sofort', 'woocommerce-payments' ),
		description: __(
			'Accept secure bank transfers from Austria, Belgium, Germany, Italy, Netherlands, and Spain.',
			'woocommerce-payments'
		),
		icon: ( props ) => <img src={ SofortIcon } alt="Sofort" { ...props } />,
		currencies: [ 'EUR' ],
		stripe_key: 'sofort_payments',
		allows_manual_capture: false,
	},
};

export default PaymentMethodInformationObject;
