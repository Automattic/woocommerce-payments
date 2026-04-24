/**
 * External dependencies
 */
import React from 'react';
import { __ } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
import BannerNotice from '../banner-notice';

const TestToLiveNotice: React.FC = () => {
	const ctaUrl = window.wcpayTestToLiveNoticeSettings?.ctaUrl ?? '';
	const dismissUrl = window.wcpayTestToLiveNoticeSettings?.dismissUrl ?? '';

	return (
		<BannerNotice
			status="success"
			isDismissible={ true }
			onRemove={ () => {
				window.location.href = dismissUrl;
			} }
			actions={ [
				{
					label: __( 'Go live', 'woocommerce-payments' ),
					variant: 'primary',
					url: ctaUrl,
				},
			] }
		>
			<strong>
				{ __(
					'Ready for your first real sale?',
					'woocommerce-payments'
				) }
			</strong>{ ' ' }
			{ __(
				'Your WooPayments setup is complete. Activate your live account to start accepting real payments.',
				'woocommerce-payments'
			) }
		</BannerNotice>
	);
};

export default TestToLiveNotice;
