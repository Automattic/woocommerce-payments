/**
 * External dependencies
 */
import React from 'react';
import { __ } from '@wordpress/i18n';
import { createInterpolateElement } from '@wordpress/element';

/**
 * Internal dependencies
 */
import BannerNotice from 'components/banner-notice';

const stageContent: Record< number, string > = {
	7: __(
		// translators: <strong>...</strong> wraps the lead sentence; preserve the tags.
		'<strong>Your store is open. Now bring in your first customer.</strong> Share your store link with your network, on social, or by email to spread the word.',
		'woocommerce-payments'
	),
	14: __(
		// translators: <strong>...</strong> wraps the lead sentence; preserve the tags.
		"<strong>Two weeks on, still no first sale?</strong> Most first sales come from word of mouth or social shares. If you've already tried those, marketing tools can help reach a wider audience.",
		'woocommerce-payments'
	),
	30: __(
		// translators: <strong>...</strong> wraps the lead sentence; preserve the tags.
		"<strong>A month in. Let's get your first sale.</strong> If organic sharing hasn't worked, marketing tools can help: paid ads, email campaigns, or SEO improvements usually move the needle.",
		'woocommerce-payments'
	),
};

const PostKycActivationNotice: React.FC = () => {
	const { stage, ctaUrl, dismissUrl } =
		window.wcpayPostKycActivationNoticeSettings ?? {};

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
			{ createInterpolateElement( content, { strong: <strong /> } ) }
		</BannerNotice>
	);
};

export default PostKycActivationNotice;
