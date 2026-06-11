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
			{ createInterpolateElement(
				__(
					"<strong>Your store made its first sale.</strong> Now bring more shoppers in with Woo's marketing tools.",
					'woocommerce-payments'
				),
				{ strong: <strong /> }
			) }
		</BannerNotice>
	);
};

export default OneAndDoneNotice;
