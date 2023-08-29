/**
 * External dependencies
 */
import React from 'react';

/**
 * Internal dependencies
 */
import strings from './strings';
import InlineNotice from 'components/inline-notice';

const RestoredStateBanner: React.FC = () => {
	const [ hidden, setHidden ] = React.useState( false );
	if ( hidden || ! wcpaySettings.onboardingFlowState ) return null;
	return (
		<InlineNotice
			className="restored-state-banner"
			status="info"
			icon
			isDismissible={ true }
			onRemove={ () => setHidden( true ) }
		>
			{ strings.restoredState }
		</InlineNotice>
	);
};

export default RestoredStateBanner;
