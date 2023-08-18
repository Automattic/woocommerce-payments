/**
 * External dependencies
 */
import React from 'react';
import { __ } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
import CreditCardIcon from 'assets/images/payment-methods/cc.svg?asset';
import BancontactIcon from 'assets/images/payment-methods/bancontact.svg?asset';
import EpsIcon from 'assets/images/payment-methods/eps.svg?asset';
import GiropayIcon from 'assets/images/payment-methods/giropay.svg?asset';
import SofortIcon from 'assets/images/payment-methods/sofort.svg?asset';
import SepaIcon from 'assets/images/payment-methods/sepa-debit.svg?asset';
import P24Icon from 'assets/images/payment-methods/p24.svg?asset';
import IdealIcon from 'assets/images/payment-methods/ideal.svg?asset';
import BankDebitIcon from 'assets/images/payment-methods/bank-debit.svg?asset';
import AffirmIcon from 'assets/images/payment-methods/affirm.svg?asset';
import AfterpayIcon from 'assets/images/payment-methods/afterpay.svg?asset';
import JCBIcon from 'assets/images/payment-methods/jcb.svg?asset';

const iconComponent = ( src: string, alt: string ): ReactImgFuncComponent => (
	props
) => <img src={ src } alt={ alt } { ...props } />;

export interface PaymentMethodMapEntry {
	id: string;
	label: string;
	brandTitles: Record< string, string >;
	description: string;
	icon: ReactImgFuncComponent;
	currencies: string[];
	stripe_key: string;
	allows_manual_capture: boolean;
	allows_pay_later: boolean;
	setup_required?: boolean;
	setup_tooltip?: string;
}

const PaymentMethodInformationObject: Record<
	string,
	PaymentMethodMapEntry
> = {
	card: {
		id: 'card',
		label: __( 'Credit card / debit card', 'woocommerce-payments' ),
		brandTitles: {
			amex: __( 'American Express', 'woocommerce-payments' ),
			diners: __( 'Diners Club', 'woocommerce-payments' ),
			discover: __( 'Discover', 'woocommerce-payments' ),
			jcb: __( 'JCB', 'woocommerce-payments' ),
			mastercard: __( 'Mastercard', 'woocommerce-payments' ),
			unionpay: __( 'UnionPay', 'woocommerce-payments' ),
			visa: __( 'Visa', 'woocommerce-payments' ),
		},
		description: __(
			'Let your customers pay with major credit and debit cards without leaving your store.',
			'woocommerce-payments'
		),
		icon: iconComponent( CreditCardIcon, 'Credit Card' ),
		currencies: [],
		stripe_key: 'card_payments',
		allows_manual_capture: true,
		allows_pay_later: false,
	},
	au_becs_debit: {
		id: 'au_becs_debit',
		label: __( 'BECS Direct Debit', 'woocommerce-payments' ),
		brandTitles: {
			au_becs_debit: __( 'BECS Direct Debit', 'woocommerce-payments' ),
		},
		description: __(
			'Bulk Electronic Clearing System — Accept secure bank transfer from Australia.',
			'woocommerce-payments'
		),
		icon: iconComponent( BankDebitIcon, 'BECS Direct Debit' ),
		currencies: [ 'AUD' ],
		stripe_key: 'au_becs_debit_payments',
		allows_manual_capture: false,
		allows_pay_later: false,
	},
	bancontact: {
		id: 'bancontact',
		label: __( 'Bancontact', 'woocommerce-payments' ),
		brandTitles: {
			bancontact: __( 'Bancontact', 'woocommerce-payments' ),
		},
		description: __(
			'Bancontact is a bank redirect payment method offered by more than 80% of online businesses in Belgium.',
			'woocommerce-payments'
		),
		icon: iconComponent( BancontactIcon, 'Bancontact' ),
		currencies: [ 'EUR' ],
		stripe_key: 'bancontact_payments',
		allows_manual_capture: false,
		allows_pay_later: false,
	},
	eps: {
		id: 'eps',
		label: __( 'EPS', 'woocommerce-payments' ),
		brandTitles: {
			eps: __( 'EPS', 'woocommerce-payments' ),
		},
		description: __(
			'Accept your payment with EPS — a common payment method in Austria.',
			'woocommerce-payments'
		),
		icon: iconComponent( EpsIcon, 'EPS' ),
		currencies: [ 'EUR' ],
		stripe_key: 'eps_payments',
		allows_manual_capture: false,
		allows_pay_later: false,
	},
	giropay: {
		id: 'giropay',
		label: __( 'giropay', 'woocommerce-payments' ),
		brandTitles: {
			giropay: __( 'giropay', 'woocommerce-payments' ),
		},
		description: __(
			'Expand your business with giropay — Germany’s second most popular payment system.',
			'woocommerce-payments'
		),
		icon: iconComponent( GiropayIcon, 'giropay' ),
		currencies: [ 'EUR' ],
		stripe_key: 'giropay_payments',
		allows_manual_capture: false,
		allows_pay_later: false,
	},
	ideal: {
		id: 'ideal',
		label: __( 'iDEAL', 'woocommerce-payments' ),
		brandTitles: {
			ideal: __( 'iDEAL', 'woocommerce-payments' ),
		},
		description: __(
			'Expand your business with iDEAL — Netherlands’s most popular payment method.',
			'woocommerce-payments'
		),
		icon: iconComponent( IdealIcon, 'iDEAL' ),
		currencies: [ 'EUR' ],
		stripe_key: 'ideal_payments',
		allows_manual_capture: false,
		allows_pay_later: false,
	},
	p24: {
		id: 'p24',
		label: __( 'Przelewy24 (P24)', 'woocommerce-payments' ),
		brandTitles: {
			p24: __( 'Przelewy24 (P24)', 'woocommerce-payments' ),
		},
		description: __(
			'Accept payments with Przelewy24 (P24), the most popular payment method in Poland.',
			'woocommerce-payments'
		),
		icon: iconComponent( P24Icon, 'Przelewy24 (P24)' ),
		currencies: [ 'EUR', 'PLN' ],
		stripe_key: 'p24_payments',
		allows_manual_capture: false,
		allows_pay_later: false,
	},
	sepa_debit: {
		id: 'sepa_debit',
		label: __( 'SEPA Direct Debit', 'woocommerce-payments' ),
		brandTitles: {
			sepa_debit: __( 'SEPA Direct Debit', 'woocommerce-payments' ),
		},
		description: __(
			'Reach 500 million customers and over 20 million businesses across the European Union.',
			'woocommerce-payments'
		),
		icon: iconComponent( SepaIcon, 'SEPA Direct Debit' ),
		currencies: [ 'EUR' ],
		stripe_key: 'sepa_debit_payments',
		allows_manual_capture: false,
		allows_pay_later: false,
	},
	sofort: {
		id: 'sofort',
		label: __( 'Sofort', 'woocommerce-payments' ),
		brandTitles: {
			sofort: __( 'Sofort', 'woocommerce-payments' ),
		},
		description: __(
			'Accept secure bank transfers from Austria, Belgium, Germany, Italy, Netherlands, and Spain.',
			'woocommerce-payments'
		),
		icon: iconComponent( SofortIcon, 'Sofort' ),
		currencies: [ 'EUR' ],
		stripe_key: 'sofort_payments',
		allows_manual_capture: false,
		allows_pay_later: false,
	},
	affirm: {
		id: 'affirm',
		label: __( 'Affirm', 'woocommerce-payments' ),
		brandTitles: {
			affirm: __( 'Affirm', 'woocommerce-payments' ),
		},
		description: __(
			// translators: %s is the store currency.
			'Allow customers to pay over time with Affirm. Available to all customers paying in %s.',
			'woocommerce-payments'
		),
		icon: iconComponent( AffirmIcon, 'Affirm' ),
		currencies: [ 'USD', 'CAD' ],
		stripe_key: 'affirm_payments',
		allows_manual_capture: false,
		allows_pay_later: true,
	},
	afterpay_clearpay: {
		id: 'afterpay_clearpay',
		label: __( 'Afterpay', 'woocommerce-payments' ),
		brandTitles: {
			afterpay_clearpay: __( 'Afterpay', 'woocommerce-payments' ),
		},
		description: __(
			// translators: %s is the store currency.
			'Allow customers to pay over time with Afterpay. Available to all customers paying in %s.',
			'woocommerce-payments'
		),
		icon: iconComponent( AfterpayIcon, 'Afterpay' ),
		currencies: [ 'USD', 'AUD', 'CAD', 'NZD', 'GBP', 'EUR' ],
		stripe_key: 'afterpay_clearpay_payments',
		allows_manual_capture: false,
		allows_pay_later: true,
	},
	jcb: {
		id: 'jcb',
		label: __( 'JCB', 'woocommerce-payments' ),
		brandTitles: {
			jcb: __( 'JCB', 'woocommerce-payments' ),
		},
		description: __(
			'Let your customers pay with JCB, the only international payment brand based in Japan.',
			'woocommerce-payments'
		),
		icon: iconComponent( JCBIcon, 'JCB' ),
		currencies: [ 'JPY' ],
		stripe_key: 'jcb_payments',
		allows_manual_capture: false,
		allows_pay_later: false,
	},
};

export default PaymentMethodInformationObject;
