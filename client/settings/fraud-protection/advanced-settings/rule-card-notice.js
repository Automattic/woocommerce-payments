/**
 * External dependencies
 */
import React from 'react';
import NoticeOutlineIcon from 'gridicons/dist/notice-outline';

/**
 * Internal dependencies
 */
import './../style.scss';
import BannerNotice from 'wcpay/components/banner-notice';
import { TipIcon } from 'wcpay/icons';

const FraudProtectionRuleCardNotice = ( { type, children } ) => {
	const supportedTypes = [ 'error', 'warning', 'info' ];

	if ( ! supportedTypes.includes( type ) ) {
		return null;
	}

	// The default icon for these notices are the (!) icon.
	let icon = <NoticeOutlineIcon />;

	// If the type is info, we want to use the info icon instead.
	if ( 'info' === type ) {
		icon = <TipIcon />;
	}

	return (
		<BannerNotice
			status={ type }
			icon={ icon }
			className={
				'fraud-protection-rule-card-notice fraud-protection-rule-card-notice-' +
				type
			}
			children={ children }
			isDismissible={ false }
		/>
	);
};

export default FraudProtectionRuleCardNotice;
