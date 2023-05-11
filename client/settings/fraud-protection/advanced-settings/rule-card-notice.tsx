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

const supportedTypes = [ 'error', 'warning', 'info' ] as const;

type NoticeType = typeof supportedTypes[ number ];

interface FraudProtectionRuleCardNoticeProps {
	type: NoticeType;
}

const FraudProtectionRuleCardNotice: React.FC< FraudProtectionRuleCardNoticeProps > = ( {
	type,
	children,
} ) => {
	if ( ! supportedTypes.includes( type ) ) {
		return null;
	}

	// If the type is info, we want to use the info icon, otherwise, the default icon for these notices is the (!) icon.
	const icon = 'info' === type ? <TipIcon /> : <NoticeOutlineIcon />;

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
