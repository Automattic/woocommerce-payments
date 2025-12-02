/**
 * External dependencies
 */
import React from 'react';
import { __ } from '@wordpress/i18n';
import clsx from 'clsx';

/**
 * Internal dependencies
 */
import JCBAsset from 'assets/images/payment-methods/jcb.svg?asset';
import VisaAsset from 'assets/images/cards/visa.svg?asset';
import MasterCardAsset from 'assets/images/cards/mastercard.svg?asset';
import AmexAsset from 'assets/images/cards/amex.svg?asset';
import WooAsset from 'assets/images/payment-methods/woo.svg?asset';
import WooAssetShort from 'assets/images/payment-methods/woo-short.svg?asset';
import DinersClubAsset from 'assets/images/cards/diners.svg?asset';
import DiscoverAsset from 'assets/images/cards/discover.svg?asset';
import CBAsset from 'assets/images/cards/cb.svg?asset';
import UnionPayAsset from 'assets/images/cards/unionpay.svg?asset';
import LinkAsset from 'assets/images/payment-methods/link.svg?asset';
import AmazonPayAsset from 'assets/images/payment-methods/amazon-pay.svg?asset';
import './style.scss';

const iconComponent = (
	src: string,
	alt: string,
	border = true
): ReactImgFuncComponent => ( { className, ...props } ) => (
	<img
		className={ clsx(
			'payment-method__icon',
			border ? '' : 'no-border',
			className
		) }
		src={ src }
		alt={ alt }
		{ ...props }
	/>
);

export const AmazonPayIcon = iconComponent(
	AmazonPayAsset,
	__( 'Amazon Pay', 'woocommerce-payments' )
);
export const AmericanExpressIcon = iconComponent(
	AmexAsset,
	__( 'American Express', 'woocommerce-payments' )
);
export const CBIcon = iconComponent(
	CBAsset,
	__( 'Cartes Bancaires', 'woocommerce-payments' )
);
export const DinersClubIcon = iconComponent(
	DinersClubAsset,
	__( 'Diners Club', 'woocommerce-payments' )
);
export const DiscoverIcon = iconComponent(
	DiscoverAsset,
	__( 'Discover', 'woocommerce-payments' )
);
export const JCBIcon = iconComponent(
	JCBAsset,
	__( 'JCB', 'woocommerce-payments' )
);
export const LinkIcon = iconComponent(
	LinkAsset,
	__( 'Link', 'woocommerce-payments' )
);
export const MastercardIcon = iconComponent(
	MasterCardAsset,
	__( 'Mastercard', 'woocommerce-payments' )
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
	__( 'WooPay', 'woocommerce-payments' ),
	false
);
export const WooIconShort = iconComponent(
	WooAssetShort,
	__( 'WooPay', 'woocommerce-payments' ),
	false
);
