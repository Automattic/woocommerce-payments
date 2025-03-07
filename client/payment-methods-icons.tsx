/**
 * External dependencies
 */
import React from 'react';
import { __ } from '@wordpress/i18n';
import classNames from 'classnames';

/**
 * Internal dependencies
 */
import WooAsset from 'assets/images/payment-methods/woo.svg?asset';
import WooAssetShort from 'assets/images/payment-methods/woo-short.svg?asset';
import ApplePayAsset from 'assets/images/cards/apple-pay.svg?asset';
import GooglePayAsset from 'assets/images/cards/google-pay.svg?asset';
import LinkAsset from 'assets/images/payment-methods/link.svg?asset';
import './style.scss';

const iconComponent = ( src: string, alt: string ): ReactImgFuncComponent => ( {
	className,
	...props
} ) => (
	<img
		className={ classNames( 'payment-method__icon', className ) }
		src={ src }
		alt={ alt }
		{ ...props }
	/>
);

export const ApplePayIcon = iconComponent(
	ApplePayAsset,
	__( 'Apple Pay', 'woocommerce-payments' )
);
export const GooglePayIcon = iconComponent(
	GooglePayAsset,
	__( 'Google Pay', 'woocommerce-payments' )
);
export const LinkIcon = iconComponent(
	LinkAsset,
	__( 'Link', 'woocommerce-payments' )
);
export const WooIcon = iconComponent(
	WooAsset,
	__( 'WooPay', 'woocommerce-payments' )
);
export const WooIconShort = iconComponent(
	WooAssetShort,
	__( 'WooPay', 'woocommerce-payments' )
);
