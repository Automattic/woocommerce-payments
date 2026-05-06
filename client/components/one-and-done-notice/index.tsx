/**
 * External dependencies
 */
import React from 'react';
import { __ } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
import BannerNotice from '../banner-notice';

const OneAndDoneNotice: React.FC = () => {
	const ctaUrl = window.wcpayOneAndDoneNoticeSettings?.ctaUrl ?? '';
	const dismissUrl = window.wcpayOneAndDoneNoticeSettings?.dismissUrl ?? '';
	const snoozeUrl = window.wcpayOneAndDoneNoticeSettings?.snoozeUrl ?? '';

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
					label: __( 'Promote my store', 'woocommerce-payments' ),
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
			<strong>
				{ __(
					'Your store made its first sale.',
					'woocommerce-payments'
				) }
			</strong>{ ' ' }
			{ __(
				"Now bring more shoppers in with Woo's marketing tools.",
				'woocommerce-payments'
			) }
		</BannerNotice>
	);
};

export default OneAndDoneNotice;
