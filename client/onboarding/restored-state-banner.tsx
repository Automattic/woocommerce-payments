/**
 * External dependencies
 */
import React from 'react';
import { info } from '@wordpress/icons';

/**
 * Internal dependencies
 */
import strings from './strings';
import BannerNotice from 'components/banner-notice';

const RestoredStateBanner: React.FC = () => {
	const [ hidden, setHidden ] = React.useState( false );
	if ( hidden || ! wcpaySettings.onboardingFlowState ) return null;
	return (
		<BannerNotice
			className="restored-state-banner"
			status="info"
			icon={ info }
			isDismissible={ true }
			onRemove={ () => setHidden( true ) }
		>
			{ strings.restoredState }
		</BannerNotice>
	);
};

export default RestoredStateBanner;
