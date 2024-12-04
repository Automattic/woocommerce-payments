/**
 * External dependencies
 */
import React, { useState } from 'react';
import { __ } from '@wordpress/i18n';
import interpolateComponents from '@automattic/interpolate-components';

/**
 * Internal dependencies
 */
import InlineNotice from 'components/inline-notice';

// eslint-disable-next-line @typescript-eslint/naming-convention
const STORAGE_KEY = 'wcpay_date_format_notice_dismissed';

const DateFormatNotice: React.FC = () => {
	const [ isNoticeVisible, setIsNoticeVisible ] = useState( () => {
		// Initialize state from localStorage
		return localStorage.getItem( STORAGE_KEY ) !== 'true';
	} );

	const handleDismiss = () => {
		setIsNoticeVisible( false );
		localStorage.setItem( STORAGE_KEY, 'true' );
	};

	const handleSettingsClick = () => {
		// Optionally dismiss the notice when clicking settings
		handleDismiss();
	};

	if ( ! isNoticeVisible ) {
		return null;
	}

	return (
		<InlineNotice
			status="info"
			icon={ true }
			isDismissible={ true }
			onRemove={ handleDismiss }
		>
			{ interpolateComponents( {
				mixedString: __(
					'The date and time formats now match your preferences. You can update them anytime in the {{settingsLink}}settings{{/settingsLink}}.',
					'woocommerce-payments'
				),
				components: {
					settingsLink: (
						// eslint-disable-next-line jsx-a11y/anchor-has-content
						<a
							title={ __( 'Settings', 'woocommerce-payments' ) }
							href="/wp-admin/options-general.php"
							target="_blank"
							rel="noreferrer"
							onClick={ handleSettingsClick }
						/>
					),
				},
			} ) }
		</InlineNotice>
	);
};

export default DateFormatNotice;
