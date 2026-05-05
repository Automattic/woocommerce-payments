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
					label: __( 'Marketing tools', 'woocommerce-payments' ),
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
					'Want to get your next customer?',
					'woocommerce-payments'
				) }
			</strong>{ ' ' }
			{ __(
				'You took your first payment. Here are some tips to get the next one.',
				'woocommerce-payments'
			) }
		</BannerNotice>
	);
};

export default OneAndDoneNotice;
