/**
 * External dependencies
 */
import React from 'react';
import { __ } from '@wordpress/i18n';
import classNames from 'classnames';

/**
 * Internal dependencies
 */
import BancontactAsset from 'assets/images/payment-methods/bancontact.svg?asset';
import EpsAsset from 'assets/images/payment-methods/eps.svg?asset';
import GiropayAsset from 'assets/images/payment-methods/giropay.svg?asset';
import SofortAsset from 'assets/images/payment-methods/sofort.svg?asset';
import SepaAsset from 'assets/images/payment-methods/sepa-debit.svg?asset';
import P24Asset from 'assets/images/payment-methods/p24.svg?asset';
import IdealAsset from 'assets/images/payment-methods/ideal.svg?asset';
import BankDebitAsset from 'assets/images/payment-methods/bank-debit.svg?asset';
import AffirmAsset from 'assets/images/payment-methods/affirm.svg?asset';
import AfterpayAsset from 'assets/images/payment-methods/afterpay.svg?asset';
import JCBAsset from 'assets/images/payment-methods/jcb.svg?asset';
import KlarnaAsset from 'assets/images/payment-methods/klarna.svg?asset';
import VisaAsset from 'assets/images/cards/visa.svg?asset';
import MasterCardAsset from 'assets/images/cards/mastercard.svg?asset';
import AmexAsset from 'assets/images/cards/amex.svg?asset';
import WooAsset from 'assets/images/payment-methods/woo.svg?asset';
import ApplePayAsset from 'assets/images/cards/apple-pay.svg?asset';
import GooglePayAsset from 'assets/images/cards/google-pay.svg?asset';
import DinersClubAsset from 'assets/images/cards/diners.svg?asset';
import UnionPayAsset from 'assets/images/cards/unionpay.svg?asset';
import LinkAsset from 'assets/images/payment-methods/link.svg?asset';
import CreditCardAsset from 'assets/images/payment-methods/cc.svg?asset';
import './style.scss';

const iconComponent = (
	src: string,
	alt: string,
	outline = true
): ReactImgFuncComponent => ( { className, ...props } ) => (
	<img
		className={ classNames(
			'payment-method__icon',
			outline ? '' : 'no-outline',
			className
		) }
		src={ src }
		alt={ alt }
		{ ...props }
	/>
);

export const AffirmIcon = iconComponent(
	AffirmAsset,
	__( 'Affirm', 'woocommerce-payments' )
);
export const AfterpayIcon = iconComponent(
	AfterpayAsset,
	__( 'Afterpay', 'woocommerce-payments' )
);
export const AmericanExpressIcon = iconComponent(
	AmexAsset,
	__( 'American Express', 'woocommerce-payments' )
);
export const ApplePayIcon = iconComponent(
	ApplePayAsset,
	__( 'Apple Pay', 'woocommerce-payments' )
);
export const BancontactIcon = iconComponent(
	BancontactAsset,
	__( 'Bancontact', 'woocommerce-payments' )
);
export const BankDebitIcon = iconComponent(
	BankDebitAsset,
	__( 'BECS Direct Debit', 'woocommerce-payments' )
);
export const CreditCardIcon = iconComponent(
	CreditCardAsset,
	__( 'Credit card / Debit card', 'woocommerce-payments' ),
	false
);
export const DinersClubIcon = iconComponent(
	DinersClubAsset,
	__( 'Diners Club', 'woocommerce-payments' )
);
export const EpsIcon = iconComponent(
	EpsAsset,
	__( 'BECS Direct Debit', 'woocommerce-payments' )
);
export const GiropayIcon = iconComponent(
	GiropayAsset,
	__( 'Giropay', 'woocommerce-payments' )
);
export const GooglePayIcon = iconComponent(
	GooglePayAsset,
	__( 'Google Pay', 'woocommerce-payments' )
);
export const IdealIcon = iconComponent(
	IdealAsset,
	__( 'iDEAL', 'woocommerce-payments' )
);
export const JCBIcon = iconComponent(
	JCBAsset,
	__( 'JCB', 'woocommerce-payments' )
);
export const KlarnaIcon = iconComponent(
	KlarnaAsset,
	__( 'Klarna', 'woocommerce-payments' )
);
export const LinkIcon = iconComponent(
	LinkAsset,
	__( 'Link', 'woocommerce-payments' )
);
export const MastercardIcon = iconComponent(
	MasterCardAsset,
	__( 'Mastercard', 'woocommerce-payments' )
);
export const P24Icon = iconComponent(
	P24Asset,
	__( 'Przelewy24 (P24)', 'woocommerce-payments' )
);
export const SepaIcon = iconComponent(
	SepaAsset,
	__( 'SEPA Direct Debit', 'woocommerce-payments' )
);
export const SofortIcon = iconComponent(
	SofortAsset,
	__( 'Sofort', 'woocommerce-payments' )
);
export const UnionPayIcon = iconComponent(
	UnionPayAsset,
	__( 'UnionPay', 'woocommerce-payments' )
);
export const VisaIcon = iconComponent(
	VisaAsset,
	__( 'Visa', 'woocommerce-payments' )
);
export const WooIcon = iconComponent(
	WooAsset,
	__( 'WooPay', 'woocommerce-payments' )
);
