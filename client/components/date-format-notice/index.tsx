/**
 * External dependencies
 */
import React, { useState } from 'react';
import { __ } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
import BannerNotice from 'components/banner-notice';
import interpolateComponents from '@automattic/interpolate-components';
import { Link } from '@woocommerce/components';
import './style.scss';

// eslint-disable-next-line @typescript-eslint/naming-convention
const STORAGE_KEY = 'woopayments_date_format_notice_dismissed';

const DateFormatNotice: React.FC = () => {
	const [ isBannerVisible, setIsBannerVisible ] = useState( () => {
		// Initialize state from localStorage
		return localStorage.getItem( STORAGE_KEY ) !== 'true';
	} );

	const handleDismiss = () => {
		setIsBannerVisible( false );
		localStorage.setItem( STORAGE_KEY, 'true' );
	};

	const handleSettingsClick = () => {
		handleDismiss();
	};

	if ( ! isBannerVisible ) {
		return null;
	}

	return (
		<BannerNotice
			status="info"
			icon={ true }
			isDismissible={ true }
			onRemove={ handleDismiss }
			className="date-format-notice"
		>
			{ interpolateComponents( {
				components: {
					settingsLink: (
						<Link
							href={ '/wp-admin/options-general.php' }
							onClick={ handleSettingsClick }
							type="external"
						/>
					),
				},
				mixedString: __(
					'The date and time formats now match your preferences. You can update them anytime in the {{settingsLink}}settings{{/settingsLink}}.',
					'woocommerce-payments'
				),
			} ) }
		</BannerNotice>
	);
};

export default DateFormatNotice;
