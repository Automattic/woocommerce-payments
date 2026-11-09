/**
 * External dependencies
 */
import React, { useContext } from 'react';
import NoticeOutlineIcon from 'gridicons/dist/notice-outline';

/**
 * Internal dependencies
 */
import InlineNotice from 'wcpay/components/inline-notice';
import { TipIcon } from 'wcpay/icons';
import RuleCardInfoNoticesContext from './rule-card-notice-context';

const supportedTypes = [ 'error', 'warning', 'info' ] as const;

type NoticeType = ( typeof supportedTypes )[ number ];

interface FraudProtectionRuleCardNoticeProps {
	type: NoticeType;
}

const FraudProtectionRuleCardNotice: React.FC<
	React.PropsWithChildren< FraudProtectionRuleCardNoticeProps >
> = ( { type, children } ) => {
	const showWarningsAsInfo = useContext( RuleCardInfoNoticesContext );
	if ( ! supportedTypes.includes( type ) ) {
		return null;
	}

	if ( showWarningsAsInfo && type === 'warning' ) {
		return (
			<InlineNotice
				status="info"
				icon
				children={ children }
				isDismissible={ false }
			/>
		);
	}

	// If the type is info, we want to use the info icon, otherwise, the default icon for these notices is the (!) icon.
	const icon = type === 'info' ? <TipIcon /> : <NoticeOutlineIcon />;

	return (
		<InlineNotice
			status={ type }
			icon={ icon }
			children={ children }
			isDismissible={ false }
		/>
	);
};

export default FraudProtectionRuleCardNotice;
