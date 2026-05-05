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
			'Your account is live — time for your first sale',
			'woocommerce-payments'
		),
		body: __(
			"Your payments are set up and ready. Now it's about getting eyes on your store — share your link, tell your network, and make your first sale.",
			'woocommerce-payments'
		),
	},
	14: {
		heading: __(
			'Two weeks in — have you shared your store yet?',
			'woocommerce-payments'
		),
		body: __(
			'Your account is fully approved and accepting payments. Share your store with your first potential customers to get that first sale.',
			'woocommerce-payments'
		),
	},
	30: {
		heading: __(
			'Your payments are ready — your first sale can be too',
			'woocommerce-payments'
		),
		body: __(
			'Everything on the payments side is ready. The next step is getting your first customer through the door — share your store link and start spreading the word.',
			'woocommerce-payments'
		),
	},
};

const PostKycActivationNotice: React.FC = () => {
	const { stage, dismissUrl } =
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
		>
			<strong>{ content.heading }</strong> { content.body }
		</BannerNotice>
	);
};

export default PostKycActivationNotice;
