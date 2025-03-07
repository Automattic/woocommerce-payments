/**
 * External dependencies
 */
import React from 'react';
import { __ } from '@wordpress/i18n';
import classNames from 'classnames';

/**
 * Internal dependencies
 */

import GrabPayAsset from 'assets/images/payment-methods/grabpay.svg?asset';
import AffirmAsset from 'assets/images/payment-methods/affirm-badge.svg?asset';
import AlipayAsset from 'assets/images/payment-methods/alipay-logo.svg';
import BankDebitAsset from 'assets/images/payment-methods/bank-debit.svg?asset';
import AfterpayAsset from 'assets/images/payment-methods/afterpay-logo.svg?asset';
import ClearpayAsset from 'assets/images/payment-methods/clearpay.svg?asset';
import BancontactAsset from 'assets/images/payment-methods/bancontact.svg?asset';
import WeChatPayAsset from 'assets/images/payment-methods/wechat-pay.svg?asset';
import SofortAsset from 'assets/images/payment-methods/sofort.svg?asset';
import SepaAsset from 'assets/images/payment-methods/sepa-debit.svg?asset';
import P24Asset from 'assets/images/payment-methods/p24.svg?asset';
import IdealAsset from 'assets/images/payment-methods/ideal.svg?asset';
import KlarnaAsset from 'assets/images/payment-methods/klarna.svg?asset';
import EpsAsset from 'assets/images/payment-methods/eps.svg?asset';
import GiropayAsset from 'assets/images/payment-methods/giropay.svg?asset';
import JCBAsset from 'assets/images/payment-methods/jcb.svg?asset';
import CreditCardAsset from 'assets/images/payment-methods/cc.svg?asset';

const accountCountry = window.wcpaySettings?.accountStatus?.country || 'US';

import type { PaymentMethodMapEntry } from './types/payment-methods';

// Get any payment method definitions from the client.
const PaymentMethodDefinitions =
	typeof woopaymentsPaymentMethodDefinitions !== 'undefined'
		? woopaymentsPaymentMethodDefinitions
		: {};

const convertedPaymentMethodDefinitions = Object.fromEntries<
	PaymentMethodMapEntry
>(
	Object.entries( PaymentMethodDefinitions ).map( ( [ key, value ] ) => [
		key,
		{
			id: value.id,
			label: value.title,
			description: value.description,
			icon: ( { className } ) => (
				<img
					src={ value.settings_icon_url }
					alt={ value.title }
					className={ classNames(
						'payment-method__icon',
						className
					) }
				/>
			),
			currencies: value.currencies,
			stripe_key: value.stripe_key,
			allows_manual_capture: value.allows_manual_capture,
			allows_pay_later: value.allows_pay_later,
			accepts_only_domestic_payment: value.accepts_only_domestic_payment,
		},
	] )
);

const PaymentMethodInformationObject: Record<
	string,
	PaymentMethodMapEntry
> = {
	card: {
		id: 'card',
		label: __( 'Credit / Debit Cards', 'woocommerce-payments' ),
		description: __(
			'Let your customers pay with major credit and debit cards without leaving your store.',
			'woocommerce-payments'
		),
		icon: ( { className, ...props } ) => (
			<img
				className={ classNames(
					'payment-method__icon no-border',
					className
				) }
				src={ CreditCardAsset }
				alt={ __( 'Credit card / Debit card', 'woocommerce-payments' ) }
				{ ...props }
			/>
		),
		currencies: [],
		stripe_key: 'card_payments',
		allows_manual_capture: true,
		allows_pay_later: false,
		accepts_only_domestic_payment: false,
	},
	alipay: {
		id: 'alipay',
		label: __( 'Alipay', 'woocommerce-payments' ),
		description: __(
			'Alipay is a popular wallet in China, operated by Ant Financial Services Group, a financial services provider affiliated with Alibaba.',
			'woocommerce-payments'
		),
		icon: ( { className, ...props } ) => (
			<img
				className={ classNames( 'payment-method__icon', className ) }
				src={ AlipayAsset }
				alt={ __( 'Alipay', 'woocommerce-payments' ) }
				{ ...props }
			/>
		),
		currencies: [],
		stripe_key: 'alipay_payments',
		allows_manual_capture: false,
		allows_pay_later: false,
		accepts_only_domestic_payment: false,
	},
	au_becs_debit: {
		id: 'au_becs_debit',
		label: __( 'BECS Direct Debit', 'woocommerce-payments' ),
		description: __(
			'Bulk Electronic Clearing System — Accept secure bank transfer from Australia.',
			'woocommerce-payments'
		),
		icon: ( { className, ...props } ) => (
			<img
				className={ classNames( 'payment-method__icon', className ) }
				src={ BankDebitAsset }
				alt={ __( 'BECS Direct Debit', 'woocommerce-payments' ) }
				{ ...props }
			/>
		),
		currencies: [ 'AUD' ],
		stripe_key: 'au_becs_debit_payments',
		allows_manual_capture: false,
		allows_pay_later: false,
		accepts_only_domestic_payment: false,
	},
	bancontact: {
		id: 'bancontact',
		label: __( 'Bancontact', 'woocommerce-payments' ),
		description: __(
			'Bancontact is a bank redirect payment method offered by more than 80% of online businesses in Belgium.',
			'woocommerce-payments'
		),
		icon: ( { className, ...props } ) => (
			<img
				className={ classNames( 'payment-method__icon', className ) }
				src={ BancontactAsset }
				alt={ __( 'Bancontact', 'woocommerce-payments' ) }
				{ ...props }
			/>
		),
		currencies: [ 'EUR' ],
		stripe_key: 'bancontact_payments',
		allows_manual_capture: false,
		allows_pay_later: false,
		accepts_only_domestic_payment: false,
	},
	eps: {
		id: 'eps',
		label: __( 'EPS', 'woocommerce-payments' ),
		description: __(
			'Accept your payment with EPS — a common payment method in Austria.',
			'woocommerce-payments'
		),
		icon: ( { className, ...props } ) => (
			<img
				className={ classNames( 'payment-method__icon', className ) }
				src={ EpsAsset }
				alt={ __( 'EPS', 'woocommerce-payments' ) }
				{ ...props }
			/>
		),
		currencies: [ 'EUR' ],
		stripe_key: 'eps_payments',
		allows_manual_capture: false,
		allows_pay_later: false,
		accepts_only_domestic_payment: false,
	},
	giropay: {
		id: 'giropay',
		label: __( 'giropay', 'woocommerce-payments' ),
		description: __(
			'Expand your business with giropay — Germany’s second most popular payment system.',
			'woocommerce-payments'
		),
		icon: ( { className, ...props } ) => (
			<img
				className={ classNames( 'payment-method__icon', className ) }
				src={ GiropayAsset }
				alt={ __( 'Giropay', 'woocommerce-payments' ) }
				{ ...props }
			/>
		),
		currencies: [ 'EUR' ],
		stripe_key: 'giropay_payments',
		allows_manual_capture: false,
		allows_pay_later: false,
		accepts_only_domestic_payment: false,
	},
	ideal: {
		id: 'ideal',
		label: __( 'iDEAL', 'woocommerce-payments' ),
		description: __(
			'Expand your business with iDEAL — Netherlands’s most popular payment method.',
			'woocommerce-payments'
		),
		icon: ( { className, ...props } ) => (
			<img
				className={ classNames( 'payment-method__icon', className ) }
				src={ IdealAsset }
				alt={ __( 'iDEAL', 'woocommerce-payments' ) }
				{ ...props }
			/>
		),
		currencies: [ 'EUR' ],
		stripe_key: 'ideal_payments',
		allows_manual_capture: false,
		allows_pay_later: false,
		accepts_only_domestic_payment: false,
	},
	p24: {
		id: 'p24',
		label: __( 'Przelewy24 (P24)', 'woocommerce-payments' ),
		description: __(
			'Accept payments with Przelewy24 (P24), the most popular payment method in Poland.',
			'woocommerce-payments'
		),
		icon: ( { className, ...props } ) => (
			<img
				className={ classNames( 'payment-method__icon', className ) }
				src={ P24Asset }
				alt={ __( 'Przelewy24 (P24)', 'woocommerce-payments' ) }
				{ ...props }
			/>
		),
		currencies: [ 'EUR', 'PLN' ],
		stripe_key: 'p24_payments',
		allows_manual_capture: false,
		allows_pay_later: false,
		accepts_only_domestic_payment: false,
	},
	sepa_debit: {
		id: 'sepa_debit',
		label: __( 'SEPA Direct Debit', 'woocommerce-payments' ),
		description: __(
			'Reach 500 million customers and over 20 million businesses across the European Union.',
			'woocommerce-payments'
		),
		icon: ( { className, ...props } ) => (
			<img
				className={ classNames( 'payment-method__icon', className ) }
				src={ SepaAsset }
				alt={ __( 'SEPA Direct Debit', 'woocommerce-payments' ) }
				{ ...props }
			/>
		),
		currencies: [ 'EUR' ],
		stripe_key: 'sepa_debit_payments',
		allows_manual_capture: false,
		allows_pay_later: false,
		accepts_only_domestic_payment: false,
	},
	sofort: {
		id: 'sofort',
		label: __( 'Sofort', 'woocommerce-payments' ),
		description: __(
			'Accept secure bank transfers from Austria, Belgium, Germany, Italy, Netherlands, and Spain.',
			'woocommerce-payments'
		),
		icon: ( { className, ...props } ) => (
			<img
				className={ classNames( 'payment-method__icon', className ) }
				src={ SofortAsset }
				alt={ __( 'Sofort', 'woocommerce-payments' ) }
				{ ...props }
			/>
		),
		currencies: [ 'EUR' ],
		stripe_key: 'sofort_payments',
		allows_manual_capture: false,
		allows_pay_later: false,
		accepts_only_domestic_payment: false,
	},
	affirm: {
		id: 'affirm',
		label: __( 'Affirm', 'woocommerce-payments' ),
		description: __(
			'Allow customers to pay over time with Affirm.',
			'woocommerce-payments'
		),
		icon: ( { className, ...props } ) => (
			<img
				className={ classNames( 'payment-method__icon', className ) }
				src={ AffirmAsset }
				alt={ __( 'Affirm', 'woocommerce-payments' ) }
				{ ...props }
			/>
		),
		currencies: [ 'USD', 'CAD' ],
		stripe_key: 'affirm_payments',
		allows_manual_capture: false,
		allows_pay_later: true,
		accepts_only_domestic_payment: true,
	},
	afterpay_clearpay: {
		id: 'afterpay_clearpay',
		label:
			'GB' === accountCountry
				? __( 'Clearpay', 'woocommerce-payments' )
				: __( 'Afterpay', 'woocommerce-payments' ),
		description:
			'GB' === accountCountry
				? __(
						'Allow customers to pay over time with Clearpay.',
						'woocommerce-payments'
				  )
				: __(
						'Allow customers to pay over time with Afterpay.',
						'woocommerce-payments'
				  ),
		icon: ( { className, ...props } ) => (
			<img
				className={ classNames( 'payment-method__icon', className ) }
				src={ 'GB' === accountCountry ? ClearpayAsset : AfterpayAsset }
				alt={
					'GB' === accountCountry
						? __( 'Clearpay', 'woocommerce-payments' )
						: __( 'Afterpay', 'woocommerce-payments' )
				}
				{ ...props }
			/>
		),
		currencies: [ 'USD', 'AUD', 'CAD', 'NZD', 'GBP' ],
		stripe_key: 'afterpay_clearpay_payments',
		allows_manual_capture: false,
		allows_pay_later: true,
		accepts_only_domestic_payment: true,
	},
	jcb: {
		id: 'jcb',
		label: __( 'JCB', 'woocommerce-payments' ),
		description: __(
			'Let your customers pay with JCB, the only international payment brand based in Japan.',
			'woocommerce-payments'
		),
		icon: ( { className, ...props } ) => (
			<img
				className={ classNames( 'payment-method__icon', className ) }
				src={ JCBAsset }
				alt={ __( 'JCB', 'woocommerce-payments' ) }
				{ ...props }
			/>
		),
		currencies: [ 'JPY' ],
		stripe_key: 'jcb_payments',
		allows_manual_capture: false,
		allows_pay_later: false,
		accepts_only_domestic_payment: false,
	},
	klarna: {
		id: 'klarna',
		label: __( 'Klarna', 'woocommerce-payments' ),
		description: __(
			'Allow customers to pay over time or pay now with Klarna.',
			'woocommerce-payments'
		),
		icon: ( { className, ...props } ) => (
			<img
				className={ classNames( 'payment-method__icon', className ) }
				src={ KlarnaAsset }
				alt={ __( 'Klarna', 'woocommerce-payments' ) }
				{ ...props }
			/>
		),
		currencies: [ 'EUR', 'GBP', 'USD', 'DKK', 'NOK', 'SEK' ],
		stripe_key: 'klarna_payments',
		allows_manual_capture: false,
		allows_pay_later: true,
		accepts_only_domestic_payment: true,
	},
	grabpay: {
		id: 'grabpay',
		label: __( 'GrabPay', 'woocommerce-payments' ),
		description: __(
			'A popular digital wallet for cashless payments in Singapore.',
			'woocommerce-payments'
		),
		icon: ( { className, ...props } ) => (
			<img
				className={ classNames( 'payment-method__icon', className ) }
				src={ GrabPayAsset }
				alt={ __( 'GrabPay', 'woocommerce-payments' ) }
				{ ...props }
			/>
		),
		currencies: [ 'SGD' ],
		stripe_key: 'grabpay_payments',
		allows_manual_capture: false,
		allows_pay_later: false,
		accepts_only_domestic_payment: false,
	},
	wechat_pay: {
		id: 'wechat_pay',
		label: __( 'WeChat Pay', 'woocommerce-payments' ),
		description: __(
			'A digital wallet popular with customers from China.',
			'woocommerce-payments'
		),
		icon: ( { className, ...props } ) => (
			<img
				className={ classNames( 'payment-method__icon', className ) }
				src={ WeChatPayAsset }
				alt={ __( 'WeChat Pay', 'woocommerce-payments' ) }
				{ ...props }
			/>
		),
		currencies: [
			'USD',
			'CNY',
			'AUD',
			'CAD',
			'EUR',
			'GBP',
			'HKD',
			'JPY',
			'SGD',
			'DKK',
			'NOK',
			'SEK',
			'CHF',
		],
		stripe_key: 'wechat_pay_payments',
		allows_manual_capture: false,
		allows_pay_later: false,
		accepts_only_domestic_payment: false,
	},
	...convertedPaymentMethodDefinitions,
};

export default PaymentMethodInformationObject;
