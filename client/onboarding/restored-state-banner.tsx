/**
 * External dependencies
 */
import React from 'react';

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
			icon
			status="info"
			className="restored-state-banner"
			onRemove={ () => setHidden( true ) }
		>
			{ strings.restoredState }
		</BannerNotice>
	);
};

export default RestoredStateBanner;
