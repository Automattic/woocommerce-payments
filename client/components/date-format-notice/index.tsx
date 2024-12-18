/**
 * External dependencies
 */
import React, { useState } from 'react';
import { __ } from '@wordpress/i18n';
import { useDispatch } from '@wordpress/data';

/**
 * Internal dependencies
 */
import BannerNotice from 'components/banner-notice';
import interpolateComponents from '@automattic/interpolate-components';
import { Link } from '@woocommerce/components';
import './style.scss';

const optionName = 'wcpay_date_format_notice_dismissed';

const DateFormatNotice: React.FC = () => {
	const { updateOptions } = useDispatch( 'wc/admin/options' );
	const [ isBannerVisible, setIsBannerVisible ] = useState(
		! wcpaySettings.isDateFormatNoticeDismissed
	);

	const handleDismiss = () => {
		setIsBannerVisible( false );
		wcpaySettings.isDateFormatNoticeDismissed = true;
		updateOptions( {
			[ optionName ]: true,
		} );
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
