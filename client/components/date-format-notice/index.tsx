/**
 * External dependencies
 */
import React, { useState } from 'react';
import { __ } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
import BannerNotice from 'components/banner-notice';

const DateFormatNotice: React.FC = () => {
	const [ isBannerVisible, setIsBannerVisible ] = useState( true );

	if ( ! isBannerVisible ) {
		return null;
	}

	return (
		<BannerNotice
			status="info"
			isDismissible={ true }
			onRemove={ () => setIsBannerVisible( false ) }
			actions={ [
				{
					label: __(
						'Configure date settings',
						'woocommerce-payments'
					),
					url: '/wp-admin/options-general.php',
				},
			] }
		>
			{ __(
				'The date and time formats now follow your preferences. You can customize these formats in the settings.',
				'woocommerce-payments'
			) }
		</BannerNotice>
	);
};

export default DateFormatNotice;
