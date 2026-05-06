/**
 * External dependencies
 */
import React from 'react';
import { __ } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
import BannerNotice from '../banner-notice';

const stageContent: Record< number, { heading: string; body: string } > = {
	7: {
		heading: __(
			'Your store is open. Now bring in your first customer.',
			'woocommerce-payments'
		),
		body: __(
			'Share your store link with your network, on social, or by email to spread the word.',
			'woocommerce-payments'
		),
	},
	14: {
		heading: __(
			'Two weeks on, still no first sale?',
			'woocommerce-payments'
		),
		body: __(
			"Most first sales come from word of mouth or social shares. If you've already tried those, marketing tools can help reach a wider audience.",
			'woocommerce-payments'
		),
	},
	30: {
		heading: __(
			"A month in. Let's get your first sale.",
			'woocommerce-payments'
		),
		body: __(
			"If organic sharing hasn't worked, marketing tools can help: paid ads, email campaigns, or SEO improvements usually move the needle.",
			'woocommerce-payments'
		),
	},
};

const PostKycActivationNotice: React.FC = () => {
	const { stage, ctaUrl, dismissUrl } =
		window?.wcpayPostKycActivationNoticeSettings ?? {};

	const content = stage ? stageContent[ stage ] : null;
	if ( ! content ) {
		return null;
	}

	return (
		<BannerNotice
			status="info"
			isDismissible={ true }
			onRemove={ () => {
				window.location.href = dismissUrl ?? '';
			} }
			actions={ [
				{
					label: __( 'Promote my store', 'woocommerce-payments' ),
					variant: 'primary',
					url: ctaUrl ?? '',
				},
			] }
		>
			<strong>{ content.heading }</strong> { content.body }
		</BannerNotice>
	);
};

export default PostKycActivationNotice;
