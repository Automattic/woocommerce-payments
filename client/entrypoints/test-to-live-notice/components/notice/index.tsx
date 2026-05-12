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

const TestToLiveNotice: React.FC = () => {
	const ctaUrl = window.wcpayTestToLiveNoticeSettings?.ctaUrl ?? '';
	const dismissUrl = window.wcpayTestToLiveNoticeSettings?.dismissUrl ?? '';
	const snoozeUrl = window.wcpayTestToLiveNoticeSettings?.snoozeUrl ?? '';

	return (
		<BannerNotice
			status="success"
			icon={ true }
			isDismissible={ true }
			onRemove={ () => {
				window.location.href = dismissUrl;
			} }
			actions={ [
				{
					label: __(
						'Turn on live payments',
						'woocommerce-payments'
					),
					variant: 'primary',
					url: ctaUrl,
				},
				{
					label: __( 'Maybe later', 'woocommerce-payments' ),
					variant: 'link',
					url: snoozeUrl,
				},
			] }
		>
			{ createInterpolateElement(
				__(
					"<strong>You're ready to take real payments.</strong> Switch from test mode to start charging customers.",
					'woocommerce-payments'
				),
				{ strong: <strong /> }
			) }
		</BannerNotice>
	);
};

export default TestToLiveNotice;
