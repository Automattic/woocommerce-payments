/**
 * External dependencies
 */
import React from 'react';
import { __ } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
import MegaphoneIcon from './megaphone-icon';
import builtItIllustration from './illustrations/built-it.png';
import checkInIllustration from './illustrations/check-in.png';
import './illustrations/style.scss';

export interface ReviewPromptVariantContent {
	heading: string;
	description: string;
	/** Small corner icon (Spotlight `icon` prop) — control only. */
	icon?: JSX.Element;
	/** Full-width banner artwork (Spotlight `image` prop) — treatments only. */
	image?: JSX.Element;
}

const controlVariant = 'control';

const illustrationBanner = ( src: string ): JSX.Element => (
	<div className="wcpay-review-prompt__illustration">
		<img src={ src } alt="" aria-hidden="true" />
	</div>
);

const variants: Record< string, ReviewPromptVariantContent > = {
	[ controlVariant ]: {
		heading: __( 'Enjoying WooPayments so far?', 'woocommerce-payments' ),
		description: __(
			'Your feedback shapes our roadmap and supports the WooCommerce community. We are all ears!',
			'woocommerce-payments'
		),
		icon: <MegaphoneIcon />,
	},
	treatment_illustration: {
		heading: __(
			'We built it. You use it. What do you think?',
			'woocommerce-payments'
		),
		description: __(
			'Leave a quick review and help shape what WooPayments does next.',
			'woocommerce-payments'
		),
		image: illustrationBanner( builtItIllustration ),
	},
	treatment_revised: {
		heading: __( 'Quick check-in?', 'woocommerce-payments' ),
		description: __(
			'Your review helps us improve WooPayments and build a better experience for every store owner.',
			'woocommerce-payments'
		),
		image: illustrationBanner( checkInIllustration ),
	},
};

/**
 * Resolve variant content; any unknown or missing key falls back to control.
 */
export const getVariantContent = (
	variant: string | undefined
): ReviewPromptVariantContent =>
	variants[ variant ?? controlVariant ] ?? variants[ controlVariant ];
